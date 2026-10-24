<?php
require_once __DIR__."/legacy_contract_helper.php";
require_once __DIR__ . "/endpoint_test_helper.php";
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
require_once dirname(__DIR__) . '/config.php';

// Helper to simulate calling the API
function call_api($disease, $debug = false) {
    $scriptPath = dirname(__DIR__) . '/disease_symptoms.php';
    $output = test_endpoint_output($scriptPath, ['disease' => $disease, 'debug' => $debug ? '1' : '0']);
    
    $jsonStart = strpos($output, '{');
    if ($jsonStart !== false) {
        $output = substr($output, $jsonStart);
    }
    
    return json_decode($output, true);
}

echo "=== TEST: DP-01 Disease -> Symptoms ===\n";

$passed = true;

// 1. Valid Disease Test
echo "Running test: Valid Disease (Alopecia)\n";
$json = call_api('Alopecia', true);

if (!$json || $json['status'] !== 'success') {
    echo "FAIL: API did not return success status.\n";
    if ($json) echo "Response: " . json_encode($json) . "\n";
    $passed = false;
} else {
    echo "SUCCESS: Retrieved " . count($json['results']) . " symptoms for Alopecia.\n";
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
    $passed = false;
} else {
    echo "SUCCESS: Retrieved " . count($json2['results']) . " symptoms for Ankylosing Spondylitis.\n";
}

// 3. Non-existent disease test
echo "Running test: Non-existent Disease\n";
$jsonMissing = call_api('Fake Disease 123');

if ($jsonMissing['status'] !== 'entity_not_found') {
    echo "FAIL: API did not return 'entity_not_found' for missing disease.\n";
    $passed = false;
}

legacy_assert_facts('disease_symptoms', 'Alopecia', $json);
legacy_assert_facts('disease_symptoms', 'Ankylosing Spondylitis', $json2);
if (!empty($jsonMissing['results'])) throw new RuntimeException('Missing entity returned facts');

if ($passed) {
    echo "ALL TESTS PASSED.\n";
    exit(0);
} else {
    echo "TESTS FAILED.\n";
    exit(1);
}
