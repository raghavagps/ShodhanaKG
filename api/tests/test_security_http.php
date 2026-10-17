<?php
require_once __DIR__ . '/http_test_helper.php';

$base = rtrim(getenv('SHODHANA_TEST_API_BASE') ?: 'http://127.0.0.1:8081/api', '/');
$apiUrl = $base . '/chatbot_response.php';
$health = @file_get_contents($base . '/health.php', false, stream_context_create(['http' => ['timeout' => 5, 'follow_location' => 0]]));
if ($health === false) throw new RuntimeException('Start the test API before running HTTP checks; this script never manages servers.');

echo "=== Actual HTTP Security Tests ===\n";

function runSecurityTest($caseName, $payload) {
    global $apiUrl;
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($payload),
            'ignore_errors' => true, // Inspect validation errors as well as successful responses.
            'timeout' => 30,
            'follow_location' => 0
        ]
    ]);
    
    $response = @file_get_contents($apiUrl, false, $context);
    try {
        test_http_json_response($response, $http_response_header ?? []);
    } catch (Throwable $error) {
        echo "[FAIL] $caseName - Invalid HTTP response: " . $error->getMessage() . "\n";
        return false;
    }
    
    // Check for leakage
    $leakIndicators = [
        '<?php',
        'function ',
        'public static',
        'Fatal error:',
        'Warning:',
        'Notice:',
        'Stack trace:',
        'root:x:0:0:'
    ];
    
    foreach ($leakIndicators as $indicator) {
        if (stripos($response, $indicator) !== false) {
            echo "[FAIL] $caseName - Leaked sensitive information or PHP error.\n";
            echo "RESPONSE LEAK: $response\n";
            return false;
        }
    }
    
    echo "[PASS] $caseName - No arbitrary execution or leakage detected.\n";
    return true;
}

$allPass = true;

// 1. Path traversal in endpoint (if the API were to foolishly use it)
$allPass = runSecurityTest("Arbitrary endpoint path traversal", [
    'question' => 'What is Copper?',
    'endpoint' => '../../core/LLMClient.php'
]) && $allPass;

// 2. Path traversal in question
$allPass = runSecurityTest("Path traversal in question", [
    'question' => '../chatbot_response.php'
]) && $allPass;

$allPass = runSecurityTest("PHP Wrapper in question", [
    'question' => 'php://filter/read=convert.base64-encode/resource=index.php'
]) && $allPass;

$allPass = runSecurityTest("File wrapper in question", [
    'question' => 'file:///etc/passwd'
]) && $allPass;

$allPass = runSecurityTest("PHP include syntax", [
    'question' => 'include("../../core/LLMClient.php")'
]) && $allPass;

if ($allPass) {
    echo "\n=== ALL HTTP SECURITY TESTS PASSED ===\n";
    exit(0);
} else {
    echo "\n=== SOME HTTP SECURITY TESTS FAILED ===\n";
    exit(1);
}
