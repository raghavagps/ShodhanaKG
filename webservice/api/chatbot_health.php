<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
// api/chatbot_health.php
// Graph-answer readiness; Ollama is not required for constructed evidence answers.
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
if (function_exists('api_handle_cors_preflight')) {
    api_handle_cors_preflight();
}
header('Content-Type: application/json');
header('Cache-Control: no-store, max-age=0');
try {
    $result = arcade_command('SELECT 1', 'sql', array(), 2);
    if (!isset($result['result'])) {
        throw new RuntimeException('Missing database response');
    }
    echo json_encode(['ok' => true, 'service' => 'graph-answer', 'status' => 'online', 'message' => 'Graph answers are available']);
} catch (Exception $e) {
    error_log('ShodhanaKG health failure: ' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
    http_response_code(503);
    echo json_encode(['ok' => false, 'service' => 'graph-answer', 'status' => 'offline', 'message' => 'Graph database is unavailable']);
} catch (Throwable $e) {
    error_log('ShodhanaKG health failure: ' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
    http_response_code(503);
    echo json_encode(['ok' => false, 'service' => 'graph-answer', 'status' => 'offline', 'message' => 'Graph database is unavailable']);
}
