<?php

require_once __DIR__ . '/PhpCompatibility.php';
final class ModelConfig
{
    public static function baseUrl()
    {
        // Explicit in-process overrides support isolated tests; deployment uses getenv.
        foreach (['OLLAMA_URL', 'OLLAMA_BASE_URL'] as $key) {
            if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
                return PhpCompatibility::value(rtrim($_ENV[$key], '/'), 'string', false);
            }
        }
        foreach (['OLLAMA_URL', 'OLLAMA_BASE_URL'] as $key) {
            $value = getenv($key);
            if ($value !== false && trim($value) !== '') {
                return PhpCompatibility::value(rtrim($value, '/'), 'string', false);
            }
        }
        return PhpCompatibility::value('http://127.0.0.1:11434', 'string', false);
    }
}
