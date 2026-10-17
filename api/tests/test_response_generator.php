<?php
require_once __DIR__ . '/../core/ResponseGenerator.php';

function assertGeneratorState($orchestratorMock, $expectedStatus, $testName) {
    // We pass a dummy question
    $result = ResponseGenerator::generate("Test question", $orchestratorMock);
    
    if ($result['status'] !== $expectedStatus) {
        echo "[FAIL] $testName. Expected '$expectedStatus', got '{$result['status']}'\n";
        return false;
    }
    echo "[PASS] $testName -> $expectedStatus\n";
    return true;
}

$allPass = true;

echo "=== Response Generator State Gates Tests ===\n";

$allPass = assertGeneratorState(['status' => 'no_data'], 'no_data', 'No Data Gate') && $allPass;
$allPass = assertGeneratorState(['status' => 'unsupported'], 'unsupported', 'Unsupported Gate') && $allPass;
$allPass = assertGeneratorState(['status' => 'needs_clarification'], 'needs_clarification', 'Clarification Gate') && $allPass;
$allPass = assertGeneratorState(['status' => 'entity_not_found'], 'entity_not_found', 'Entity Not Found Gate') && $allPass;

echo "=== Malformed Evidence Tests ===\n";
// An unavailable model must not be consulted by graph answer construction.
$_ENV['OLLAMA_BASE_URL'] = 'http://localhost:9999'; 

$successMock = [
    'status' => 'success',
    'retrieval' => [
        'test' => 'data'
    ]
];

// Malformed evidence must fail before answer construction; Ollama is not called.
$allPass = assertGeneratorState($successMock, 'error', 'Malformed retrieval rejected without model call') && $allPass;

if ($allPass) {
    echo "\n=== ALL GENERATOR TESTS PASSED ===\n";
    exit(0);
} else {
    echo "\n=== SOME GENERATOR TESTS FAILED ===\n";
    exit(1);
}
