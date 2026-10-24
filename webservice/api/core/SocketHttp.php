<?php
/** Bounded HTTP transport for hosts without cURL or allow_url_fopen. */
final class SocketHttp {
    public static function request($url, $method, $body, $timeout, array $headers = array(), $maxBytes = 4194304) {
        $parts = parse_url($url);
        if (!$parts || !isset($parts['scheme'], $parts['host']) || !in_array($parts['scheme'], ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\r\n]/', $url)
            || !in_array($method, ['GET', 'POST'], true)) throw new RuntimeException('Invalid model transport URL');
        $extraHeaders = '';
        foreach ($headers as $key => $value) {
            if (!preg_match('/^[A-Za-z0-9-]+$/', $key) || preg_match('/[\r\n]/', $value)) throw new RuntimeException('Invalid transport header');
            $extraHeaders .= $key . ': ' . $value . "\r\n";
        }
        $secure = $parts['scheme'] === 'https';
        $port = isset($parts['port']) ? $parts['port'] : ($secure ? 443 : 80);
        $path = (isset($parts['path']) ? $parts['path'] : '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $timeout = max(1, (int)$timeout);
        $deadline = microtime(true) + $timeout;
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $parts['host']]]);
        $socket = @stream_socket_client(($secure ? 'tls' : 'tcp') . '://' . $parts['host'] . ':' . $port,
            $errno, $error, min(5, $timeout), STREAM_CLIENT_CONNECT, $context);
        if (!$socket) throw new RuntimeException('Model socket connection failed');
        try {
            stream_set_timeout($socket, $timeout);
            $request = $method . ' ' . $path . " HTTP/1.1\r\nHost: " . $parts['host'] . ':' . $port
                . "\r\nConnection: close\r\nAccept: application/json\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n" . $extraHeaders . "\r\n" . $body;
            for ($sent = 0; $sent < strlen($request); $sent += $written) {
                $written = @fwrite($socket, substr($request, $sent));
                if (!$written || microtime(true) >= $deadline) throw new RuntimeException('Model socket write failed');
            }
            $raw = '';
            while (!feof($socket)) {
                $left = $deadline - microtime(true);
                if ($left <= 0) throw new RuntimeException('Model socket timeout');
                stream_set_timeout($socket, (int)$left, (int)(($left - (int)$left) * 1000000));
                $chunk = @fread($socket, 8192);
                $meta = stream_get_meta_data($socket);
                if ($chunk === false || !empty($meta['timed_out'])) throw new RuntimeException('Model socket read failed or timed out');
                $raw .= $chunk;
                if (strlen($raw) > $maxBytes) throw new RuntimeException('Model response exceeds size limit');
                if (self::complete($raw)) return self::decode($raw);
            }
            return self::decode($raw);
        } finally { fclose($socket); }
    }

    private static function complete($raw) {
        $split = strpos($raw, "\r\n\r\n");
        if ($split === false) return false;
        $head = substr($raw, 0, $split); $body = substr($raw, $split + 4);
        if (preg_match('/^Transfer-Encoding:\s*chunked\s*$/im', $head)) {
            $offset = 0;
            while (true) {
                $end = strpos($body, "\r\n", $offset);
                if ($end === false) return false;
                $line = substr($body, $offset, $end - $offset);
                if (!preg_match('/^[0-9a-f]+(?:;[^\r\n]*)?$/i', $line)) throw new RuntimeException('Malformed HTTP chunk');
                $size = hexdec(explode(';', $line)[0]); $offset = $end + 2;
                if ($size === 0) return substr($body,$offset,2) === "\r\n" || strpos($body,"\r\n\r\n",$offset) !== false;
                if (strlen($body) < $offset + $size + 2) return false;
                if (substr($body,$offset+$size,2) !== "\r\n") throw new RuntimeException('Malformed HTTP chunk boundary');
                $offset += $size + 2;
            }
        }
        return preg_match('/^Content-Length:\s*(\d+)\s*$/im', $head, $m) && strlen($body) >= (int)$m[1];
    }

    public static function decode($raw) {
        $split = strpos($raw, "\r\n\r\n");
        if ($split === false || $split > 32768) throw new RuntimeException('Malformed model HTTP headers');
        $head = substr($raw, 0, $split); $body = substr($raw, $split + 4);
        if (!preg_match('~^HTTP/1\.[01] ([0-9]{3})(?: |\r\n|$)~', $head, $m)) throw new RuntimeException('Invalid model HTTP status');
        $status = (int)$m[1];
        if (preg_match('/^Transfer-Encoding:\s*chunked\s*$/im', $head)) {
            $decoded = ''; $offset = 0;
            while (true) {
                $end = strpos($body, "\r\n", $offset);
                if ($end === false || !preg_match('/^[0-9a-f]+(?:;[^\r\n]*)?$/i', substr($body, $offset, $end - $offset))) throw new RuntimeException('Malformed model chunk size');
                $size = hexdec(explode(';', substr($body, $offset, $end - $offset))[0]);
                $offset = $end + 2;
                if ($size === 0) {
                    if (substr($body, $offset, 2) !== "\r\n" && strpos($body, "\r\n\r\n", $offset) === false) throw new RuntimeException('Truncated model chunk terminator');
                    break;
                }
                if ($size > strlen($body) - $offset || substr($body, $offset + $size, 2) !== "\r\n") throw new RuntimeException('Truncated model chunk');
                $decoded .= substr($body, $offset, $size); $offset += $size + 2;
            }
            $body = $decoded;
        } elseif (preg_match('/^Content-Length:\s*(\d+)\s*$/im', $head, $m) && strlen($body) !== (int)$m[1]) {
            throw new RuntimeException('Truncated model response');
        }
        return ['status' => $status, 'body' => $body];
    }
}
