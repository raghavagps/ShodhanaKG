<?php

require_once __DIR__ . '/PhpCompatibility.php';
final class ModelConfig
{
    public static function baseUrl()
    {
        // Explicit in-process overrides support isolated tests; deployment uses getenv.
        foreach (array('OLLAMA_URL', 'OLLAMA_BASE_URL') as $key) {
            if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
                return PhpCompatibility::value(rtrim($_ENV[$key], '/'), 'string', false);
            }
        }
        foreach (array('OLLAMA_URL', 'OLLAMA_BASE_URL') as $key) {
            $value = getenv($key);
            if ($value !== false && trim($value) !== '') {
                return PhpCompatibility::value(rtrim($value, '/'), 'string', false);
            }
        }
        $host = isset($_ENV['OLLAMA_HOST']) ? $_ENV['OLLAMA_HOST'] : getenv('OLLAMA_HOST');
        $port = isset($_ENV['OLLAMA_PORT']) ? $_ENV['OLLAMA_PORT'] : getenv('OLLAMA_PORT');
        if (($host !== false && $host !== null && trim((string)$host) !== '') || ($port !== false && $port !== null && trim((string)$port) !== '')) {
            $h = ($host !== false && $host !== null && trim((string)$host) !== '') ? trim((string)$host) : '127.0.0.1';
            $p = ($port !== false && $port !== null && trim((string)$port) !== '') ? trim((string)$port) : '11434';
            return PhpCompatibility::value('http://' . $h . ':' . $p, 'string', false);
        }
        return PhpCompatibility::value('http://127.0.0.1:11434', 'string', false);
    }
}
