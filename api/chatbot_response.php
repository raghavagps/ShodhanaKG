<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
set_time_limit(120);
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/ChatRequest.php';
require_once __DIR__ . '/core/ChatbotOrchestrator.php';
require_once __DIR__ . '/core/ResponseGenerator.php';
require_once __DIR__ . '/core/FAQMatcher.php';
require_once __DIR__ . '/core/ChatResponseTransport.php';
header('Content-Type: application/json');
if (function_exists('api_handle_cors_preflight')) {
    api_handle_cors_preflight();
}
$method = (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '');
if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['status' => 'error', 'message' => 'Use POST']);
    exit;
}
try {
    $input = ChatRequest::decode(file_get_contents('php://input'));
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
$stream = (isset($input['stream']) ? $input['stream'] : false);
$question = $input['question'];
$history = [];
if (isset($input['history']) && is_array($input['history'])) {
    foreach (array_slice($input['history'], -20) as $turn) {
        if (!is_array($turn)) {
            continue;
        }
        $role = ((isset($turn['role']) ? $turn['role'] : '') === 'user' ? 'user' : 'bot');
        $text = (isset($turn['text']) && is_string($turn['text']) ? mb_substr(trim($turn['text']), 0, 500) : '');
        if ($text !== '') {
            $history[] = ['role' => $role, 'text' => $text];
        }
        if (count($history) >= 20) {
            break;
        }
    }
}
try {
    $faqMatch = FAQMatcher::find($question, isset($input['faq_id']) ? $input['faq_id'] : null);
    if ($faqMatch !== null) {
        $heading = $faqMatch['category'] === 'background' ? 'Background information' : 'Using ShodhanaKG';
        ChatResponseTransport::respond(['question' => $question, 'answer' => $heading . "\n\n" . $faqMatch['answer'], 'status' => 'success', 'faq' => $faqMatch, 'citations' => [], 'evidence_records' => [], 'context' => ['type' => 'faq_static_response', 'faq' => $faqMatch]], $stream);
        exit;
    }
    if ($stream) {
        ChatResponseTransport::headers();
        $orchestratorResponse = ChatbotOrchestrator::process($question, $history, (isset($input['conversation_context']) ? $input['conversation_context'] : null), max(0, (int) (isset($input['page_offset']) ? $input['page_offset'] : 0)), (isset($input['selected_entity']) ? $input['selected_entity'] : null));
        ChatResponseTransport::event('context', $orchestratorResponse);
        $generationResult = ResponseGenerator::generateStream($question, $orchestratorResponse, $history, function ($token) {
            echo "data: " . json_encode(['chunk' => $token], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        });
        $generationResult['conversation_context'] = (isset($orchestratorResponse['conversation_context']) ? $orchestratorResponse['conversation_context'] : null);
        foreach (['resolution', 'clarification', 'understanding'] as $key) {
            if (isset($orchestratorResponse[$key])) {
                $generationResult[$key] = $orchestratorResponse[$key];
            }
        }
        // The authoritative final status
        $finalStatus = $generationResult['status'];
        ChatResponseTransport::event('done', $generationResult);
        exit;
    }
    // Traditional JSON path
    $orchestratorResponse = ChatbotOrchestrator::process($question, $history, (isset($input['conversation_context']) ? $input['conversation_context'] : null), max(0, (int) (isset($input['page_offset']) ? $input['page_offset'] : 0)), (isset($input['selected_entity']) ? $input['selected_entity'] : null));
    $generationResult = ResponseGenerator::generate($question, $orchestratorResponse, $history);
    $generationResult['conversation_context'] = (isset($orchestratorResponse['conversation_context']) ? $orchestratorResponse['conversation_context'] : null);
    foreach (['resolution', 'clarification', 'understanding'] as $key) {
        if (isset($orchestratorResponse[$key])) {
            $generationResult[$key] = $orchestratorResponse[$key];
        }
    }
    // Preserve the orchestrator status if it's an error, otherwise use generation status
    $finalStatus = $generationResult['status'];
    echo json_encode(array_merge($generationResult, ['question' => $question, 'answer' => $generationResult['answer'], 'status' => $finalStatus, 'context' => $orchestratorResponse]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('ShodhanaKG chatbot failure: ' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
    ChatResponseTransport::respond(['status' => 'error', 'answer' => 'Chatbot response generation failed.'], $stream);
} catch (Throwable $e) {
    error_log('ShodhanaKG chatbot failure: ' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
    ChatResponseTransport::respond(['status' => 'error', 'answer' => 'Chatbot response generation failed.'], $stream);
}
