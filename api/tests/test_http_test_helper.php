<?php
require_once __DIR__ . '/http_test_helper.php';
$checks = 0;
foreach ([
    [false, ['HTTP/1.1 200 OK', 'Content-Type: application/json']],
    ['', ['HTTP/1.1 200 OK', 'Content-Type: application/json']],
    ['{"status":"success"}', []],
    ['{"status":"error"}', ['HTTP/1.1 500 Error', 'Content-Type: application/json']],
    ['{"status":"success"}', ['HTTP/1.1 302 Found', 'Content-Type: application/json']],
    ['<html>not an API</html>', ['HTTP/1.1 200 OK', 'Content-Type: text/html']],
    ['not json', ['HTTP/1.1 200 OK', 'Content-Type: application/json']],
    ['{}', ['HTTP/1.1 200 OK', 'Content-Type: application/json']],
    ['{"status":null}', ['HTTP/1.1 200 OK', 'Content-Type: application/json']],
    ['{"status":"success"}', ['HTTP/1.1 400 Bad Request', 'Content-Type: application/json']],
] as [$body, $headers]) {
    $rejected = false;
    try { test_http_json_response($body, $headers); } catch (Throwable $e) { $rejected = true; }
    if (!$rejected) throw new RuntimeException('Invalid response accepted');
    $checks++;
}
foreach ([200 => 'success', 400 => 'error'] as $code => $status) {
    $data = ['status' => $status, 'answer' => 'Test envelope'];
    if (test_http_json_response(json_encode($data), ["HTTP/1.1 $code response", 'content-type: application/json; charset=utf-8']) !== $data) throw new RuntimeException('Valid response changed');
    $checks++;
}
echo "PASS $checks HTTP harness response contracts\n";
