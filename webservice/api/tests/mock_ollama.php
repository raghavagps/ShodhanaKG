<?php
$mockData = @file_get_contents(__DIR__ . '/mock_response.json');
$response = $mockData !== false ? json_decode($mockData, true) : [];
$text = isset($response['text']) ? $response['text'] : "Mocked answer";

if (isset($response['sleep'])) {
    sleep($response['sleep']);
}

$httpCode = isset($response['httpCode']) ? $response['httpCode'] : 200;
$status = isset($response['status']) ? $response['status'] : 'success';

if (isset($response['raw_body'])) {
    http_response_code($httpCode);
    echo $response['raw_body'];
    exit;
}

http_response_code($httpCode);

if ($status === 'invalid_json') {
    echo "{ invalid json";
} elseif ($status === 'empty') {
    echo json_encode(['response' => '']);
} elseif ($status === 'malformed') {
    echo json_encode(['not_response' => 'missing response field']);
} else {
    echo json_encode([
        'model' => 'llama3.2',
        'created_at' => date('c'),
        'response' => $text,
        'done' => true,
        'done_reason' => 'stop',
        'context' => [1, 2, 3],
        'total_duration' => 12345,
        'load_duration' => 123,
        'prompt_eval_count' => 10,
        'eval_count' => 20
    ]);
}
