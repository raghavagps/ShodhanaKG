<?php
require_once __DIR__ . "/endpoint_test_helper.php";
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
require_once dirname(__DIR__) . '/config.php';

function call_api($disease, $debug = false) {
    $scriptPath = dirname(__DIR__) . '/disease_therapy.php';
    $output = test_endpoint_output($scriptPath, ['disease' => $disease, 'debug' => $debug ? '1' : '0']);
    
    $jsonStart = strpos($output, '{');
    if ($jsonStart !== false) {
        $output = substr($output, $jsonStart);
    }
    
    return json_decode($output, true);
}

echo "=== TEST: DP-04 Disease -> Therapy ===\n";
$passed = true;

// 1. Valid Disease Test
echo "Running test: Valid Disease (Alopecia)\n";
$json = call_api('Alopecia', true);

if (!$json || $json['status'] !== 'success') {
    echo "FAIL: API did not return success status.\n";
    if ($json) echo "Response: " . json_encode($json) . "\n";
    $passed = false;
} else {
    echo "SUCCESS: Retrieved " . count($json['results']) . " therapies for Alopecia.\n";
    if (isset($json['debug'])) {
        echo "  Raw records: {$json['debug']['raw_records']}\n";
        echo "  Duplicates filtered: {$json['debug']['duplicates_filtered']}\n";
    }
}

// 2. Another Disease Test
echo "Running test: Valid Disease (Ankylosing Spondylitis)\n";
$json2 = call_api('Ankylosing Spondylitis');

if (!$json2 || $json2['status'] !== 'success') {
    echo "FAIL: API did not return success status.\n";
    if ($json2) echo "Response: " . json_encode($json2) . "\n";
    $passed = false;
} else {
    echo "SUCCESS: Retrieved " . count($json2['results']) . " therapies for Ankylosing Spondylitis.\n";
}

if ($passed) {
    echo "ALL TESTS PASSED.\n";
    exit(0);
} else {
    echo "TESTS FAILED.\n";
    exit(1);
}
