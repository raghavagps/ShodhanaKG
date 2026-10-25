<?php
/**
 * Local development runner for the ShodhanaKG PHP API server.
 * Reads API_HOST and API_PORT from .env and starts the built-in PHP web server.
 *
 * Usage:
 *   php webservice/api/serve.php
 */
require_once __DIR__ . '/config.php';

$host = isset($API_HOST) && trim($API_HOST) !== '' ? trim($API_HOST) : '127.0.0.1';
$port = isset($API_PORT) && trim($API_PORT) !== '' ? trim($API_PORT) : '8081';
$docRoot = dirname(__DIR__);

echo "Starting ShodhanaKG PHP API server on http://{$host}:{$port} (document root: {$docRoot})\n";
passthru(sprintf('php -S %s:%s -t %s', escapeshellarg($host), escapeshellarg($port), escapeshellarg($docRoot)));
