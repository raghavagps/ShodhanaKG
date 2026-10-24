<?php
require_once __DIR__."/legacy_contract_helper.php";
$_SERVER['REQUEST_METHOD'] = 'CLI';
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';

function runRealTest($question, $expectedStatus, $expectedEndpoint = null) {
    echo "Q: \"$question\"\n";
    $result = legacy_test_process($question, $expectedEndpoint);
    
    if ($result['status'] !== $expectedStatus) {
        echo "[FAIL] Expected status '$expectedStatus', got '{$result['status']}'\n";
        return false;
    }
    
    if ($expectedStatus === 'success' || $expectedStatus === 'no_data') {
        if ($result['routing']['endpoint'] !== $expectedEndpoint) {
            echo "[FAIL] Expected endpoint '$expectedEndpoint', got '{$result['routing']['endpoint']}'\n";
            return false;
        }
    }
    
    echo "[PASS] Status: {$result['status']}\n\n";
    return true;
}

$allPass = true;
echo "=== Representative Schema Queries ===\n";
$allPass = runRealTest("What media is used for Copper?", "success", "compound_media") && $allPass;
$allPass = runRealTest("What validation studies are available for Croton tiglium Linn.?", "success", "compound_validation") && $allPass;
$allPass = runRealTest("What are the symptoms of Alopecia?", "success", "disease_symptoms") && $allPass;
$allPass = runRealTest("What therapies are associated with Rheumatoid Arthritis?", "success", "disease_therapy") && $allPass;
$allPass = runRealTest("What is the Ayurvedic Diagnosis for Fever?", "needs_clarification") && $allPass;

if ($allPass) {
    echo "\n=== ALL REAL SCHEMA QUERIES PASSED ===\n";
    exit(0);
} else {
    echo "\n=== SOME TESTS FAILED ===\n";
    exit(1);
}
