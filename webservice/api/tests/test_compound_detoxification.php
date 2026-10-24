<?php
require_once __DIR__."/legacy_contract_helper.php";
require_once __DIR__ . "/endpoint_test_helper.php";
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
require_once dirname(__DIR__) . '/config.php';

function call_api($compound, $debug = false) {
    $scriptPath = dirname(__DIR__) . '/compound_detoxification.php';
    $output = test_endpoint_output($scriptPath, ['compound' => $compound, 'debug' => $debug ? '1' : '0']);
    
    $jsonStart = strpos($output, '{');
    if ($jsonStart !== false) {
        $output = substr($output, $jsonStart);
    }
    
    return json_decode($output, true);
}

echo "=== TEST: CP-05 Compound -> Detoxification ===\n";
$passed = true;

// 1. Valid Compound Test
echo "Running test: Valid Compound (Croton tiglium Linn.)\n";
$json = call_api('Croton tiglium Linn.', true);

if (!$json || !in_array($json['status'], ['success', 'no_data'])) {
    echo "FAIL: API did not return success or no_data status.\n";
    if ($json) echo "Response: " . json_encode($json) . "\n";
    $passed = false;
} else {
    $count = isset($json['results']) ? count($json['results']) : 0;
    echo "SUCCESS: Retrieved $count detoxification elements for Croton tiglium Linn.\n";
    if (isset($json['debug'])) {
        echo "  Raw records (cross-join candidates): {$json['debug']['raw_records']}\n";
        echo "  Duplicates filtered: {$json['debug']['duplicates_filtered']}\n";
    }
}

// 2. Non-existent compound test
echo "Running test: Non-existent Compound\n";
$jsonMissing = call_api('Fake Compound 123');

if ($jsonMissing['status'] !== 'entity_not_found') {
    echo "FAIL: API did not return 'entity_not_found' for missing compound.\n";
    $passed = false;
}

legacy_assert_facts('compound_detoxification', 'Croton tiglium Linn.', $json);
if (!empty($jsonMissing['results'])) throw new RuntimeException('Missing entity returned facts');

if ($passed) {
    echo "ALL TESTS PASSED.\n";
    exit(0);
} else {
    echo "TESTS FAILED.\n";
    exit(1);
}
