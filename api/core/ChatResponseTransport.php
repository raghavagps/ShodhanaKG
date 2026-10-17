<?php

require_once __DIR__ . '/PhpCompatibility.php';
/** JSON and SSE share the same final status and evidence context. */
class ChatResponseTransport
{
    public static function event($name, array $data)
    {
        $name = PhpCompatibility::value($name, 'string', false);
        echo 'event: ' . $name . "\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        flush();
    }
    public static function respond(array $response, $stream)
    {
        $stream = PhpCompatibility::value($stream, 'bool', false);
        if (!$stream) {
            header('Content-Type: application/json');
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        self::headers();
        if (isset($response['context'])) {
            self::event('context', $response['context']);
        }
        self::event('message', ['chunk' => (isset($response['answer']) ? $response['answer'] : (isset($response['message']) ? $response['message'] : ''))]);
        self::event('done', $response);
    }
    public static function headers()
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');
        @ini_set('zlib.output_compression', '0');
        while (PHP_SAPI !== 'cli' && ob_get_level() > 0) { if (!@ob_end_flush()) break; }
        echo ": connected\n\n";
        flush();
    }
}
