<?php

require_once __DIR__ . '/core/PhpCompatibility.php';
require_once __DIR__ . '/core/SemanticQuery.php';
require_once __DIR__ . '/cors.php';
api_handle_cors_preflight();
header('Content-Type: application/json');
try {
    if ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') !== 'GET') {
        throw new InvalidArgumentException('Use GET');
    }
    foreach (['source_type', 'entity', 'result_type'] as $key) {
        if (!is_string((isset($_GET[$key]) ? $_GET[$key] : null)) || trim($_GET[$key]) === '') {
            throw new InvalidArgumentException('Missing ' . $key);
        }
    }
    $constraints = PhpCompatibility::jsonDecode((isset($_GET['constraints']) ? $_GET['constraints'] : '[]'), true, 8, 0);
    if (!is_array($constraints) || count($constraints) > 3) {
        throw new InvalidArgumentException('Invalid constraints');
    }
    $data = SemanticQuery::run($_GET['source_type'], $_GET['entity'], $_GET['result_type'], $constraints, (int) (isset($_GET['offset']) ? $_GET['offset'] : 0), (int) (isset($_GET['limit']) ? $_GET['limit'] : 1000));
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid query parameters']);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid query parameters']);
}
