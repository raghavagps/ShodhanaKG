<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/ChatbotOrchestrator.php';

header('Content-Type: application/json');

if (function_exists('api_handle_cors_preflight')) {
    api_handle_cors_preflight();
}

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';

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
    $result = ChatbotOrchestrator::process($question);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Chatbot processing failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
