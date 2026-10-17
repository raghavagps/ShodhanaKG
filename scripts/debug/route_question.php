<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/QuestionRouter.php';

header('Content-Type: application/json');

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';

// Quick exit for preflight if implemented in cors.php
if (function_exists('api_handle_cors_preflight')) {
    api_handle_cors_preflight();
}

if ($method !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Use POST']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$question = isset($input['question']) ? trim($input['question']) : '';

if ($question === '') {
    echo json_encode(['status' => 'error', 'message' => 'Question is required']);
    exit;
}

try {
    $result = QuestionRouter::route($question);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Routing failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
