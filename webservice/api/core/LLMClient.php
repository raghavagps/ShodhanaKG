<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . "/ModelConfig.php";
require_once __DIR__ . '/SocketHttp.php';
class LLMClient
{
    private static function keepAlive()
    {
        $value = getenv('OLLAMA_KEEP_ALIVE');
        if ($value === '-1' || $value === '0') return (int)$value;
        return $value ?: '5m';
    }
    public static function checkHealth()
    {
        $baseUrl = ModelConfig::baseUrl();
        $url = rtrim($baseUrl, '/') . '/api/tags';
        $timeout = 5;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $timeout));
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return PhpCompatibility::value($response !== false && $httpCode === 200, 'bool', false);
        } elseif (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            try { $reply = SocketHttp::request($url, 'GET', '', $timeout); return $reply['status'] === 200; }
            catch (Exception $e) { return false; }
        } else {
            $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => $timeout, 'ignore_errors' => true]]);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return PhpCompatibility::value(false, 'bool', false);
            }
            $httpCode = 200;
            $headers = (function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : @$http_response_header);
            if (count($headers) > 0 && preg_match('#HTTP/[0-9\.]+\s+([0-9]+)#', $headers[0], $m)) {
                $httpCode = intval($m[1]);
            }
            return PhpCompatibility::value($httpCode === 200, 'bool', false);
        }
    }
    public static function generateStream($prompt, callable $onToken)
    {
        $prompt = PhpCompatibility::value($prompt, 'string', false);
        $baseUrl = ModelConfig::baseUrl();
        $model = (isset($_ENV['OLLAMA_MODEL']) ? $_ENV['OLLAMA_MODEL'] : (getenv('OLLAMA_MODEL') !== false ? getenv('OLLAMA_MODEL') : 'llama3.2'));
        $timeout = 600;
        if (!function_exists('curl_init')) {
            $result = self::generate($prompt);
            if ($result['status'] === 'success') {
                $onToken($result['text']);
            }
            return PhpCompatibility::value($result, 'array', false);
        }
        $url = rtrim($baseUrl, '/') . '/api/generate';
        $payload = json_encode(['model' => $model, 'prompt' => $prompt, 'stream' => true, 'keep_alive' => self::keepAlive(), 'options' => ['temperature' => 0.0]]);
        $fullText = '';
        $carry = '';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $timeout));
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$carry, &$fullText, $onToken) {
            $data = PhpCompatibility::value($data, 'string', false);
            $carry .= $data;
            $lines = explode("\n", $carry);
            $carry = (string) array_pop($lines);
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $parsed = json_decode($line, true);
                if (is_array($parsed) && isset($parsed['response'])) {
                    $chunk = $parsed['response'];
                    $fullText .= $chunk;
                    $onToken($chunk);
                }
            }
            return PhpCompatibility::value(strlen($data), 'int', false);
        });
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            return PhpCompatibility::value(['status' => 'llm_error', 'message' => 'Ollama connection failed: ' . $error], 'array', false);
        }
        if ($httpCode !== 200) {
            return PhpCompatibility::value(['status' => 'llm_error', 'message' => "Ollama returned HTTP {$httpCode}"], 'array', false);
        }
        if (trim($carry) !== '') {
            $parsed = json_decode($carry, true);
            if (is_array($parsed) && isset($parsed['response'])) {
                $chunk = $parsed['response'];
                $fullText .= $chunk;
                $onToken($chunk);
            }
        }
        if (trim($fullText) === '') {
            return PhpCompatibility::value(['status' => 'llm_error', 'message' => 'Empty model response'], 'array', false);
        }
        return PhpCompatibility::value(['status' => 'success', 'text' => trim($fullText), 'model' => $model], 'array', false);
    }
    public static function generate($prompt, $timeoutSeconds = null, $jsonMode = false, $schema = null)
    {
        $prompt = PhpCompatibility::value($prompt, 'string', false);
        $timeoutSeconds = PhpCompatibility::value($timeoutSeconds, 'int', true);
        $jsonMode = PhpCompatibility::value($jsonMode, 'bool', false);
        $schema = PhpCompatibility::value($schema, 'array', true);
        $baseUrl = ModelConfig::baseUrl();
        $model = (isset($_ENV['OLLAMA_MODEL']) ? $_ENV['OLLAMA_MODEL'] : (getenv('OLLAMA_MODEL') !== false ? getenv('OLLAMA_MODEL') : 'llama3.2'));
        $timeout = (isset($timeoutSeconds) ? $timeoutSeconds : (isset($_ENV['OLLAMA_TIMEOUT']) ? (int) $_ENV['OLLAMA_TIMEOUT'] : 120));
        $url = rtrim($baseUrl, '/') . '/api/generate';
        $payload = json_encode(['model' => $model, 'prompt' => $prompt, 'stream' => false, 'format' => (isset($schema) ? $schema : ($jsonMode ? 'json' : '')), 'keep_alive' => self::keepAlive(), 'options' => ['temperature' => 0.0]]);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $timeout));
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($response === false) {
                return PhpCompatibility::value(['status' => 'llm_error', 'message' => 'Ollama connection failed: ' . $error], 'array', false);
            }
            if ($httpCode !== 200) {
                return PhpCompatibility::value(['status' => 'llm_error', 'message' => "Ollama returned HTTP {$httpCode}"], 'array', false);
            }
        } elseif (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            try { $reply = SocketHttp::request($url, 'POST', $payload, $timeout); }
            catch (Exception $e) { return ['status' => 'llm_error', 'message' => $e->getMessage()]; }
            if ($reply['status'] !== 200) return ['status' => 'llm_error', 'message' => 'Ollama returned HTTP ' . $reply['status']];
            $response = $reply['body'];
        } else {
            $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $payload, 'timeout' => $timeout, 'ignore_errors' => true]]);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return PhpCompatibility::value(['status' => 'llm_error', 'message' => 'Ollama connection failed via file_get_contents'], 'array', false);
            }
            $httpCode = 200;
            $headers = (function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : @$http_response_header);
            if (count($headers) > 0 && preg_match('#HTTP/[0-9\.]+\s+([0-9]+)#', $headers[0], $m)) {
                $httpCode = intval($m[1]);
            }
            if ($httpCode !== 200) {
                return PhpCompatibility::value(['status' => 'llm_error', 'message' => "Ollama returned HTTP {$httpCode}"], 'array', false);
            }
        }
        $decoded = json_decode($response, true);
        if (!$decoded || !isset($decoded['response'])) {
            return PhpCompatibility::value(['status' => 'llm_error', 'message' => 'Malformed response from Ollama'], 'array', false);
        }
        if (trim($decoded['response']) === '') {
            return PhpCompatibility::value(['status' => 'llm_error', 'message' => 'Empty model response'], 'array', false);
        }
        return PhpCompatibility::value(['status' => 'success', 'text' => trim($decoded['response']), 'model' => $model], 'array', false);
    }
}
