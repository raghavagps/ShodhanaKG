<?php
require_once __DIR__."/legacy_contract_helper.php";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';

function test_phase15_bugfixes() {
    echo "Starting Phase 15 - Entity Resolution and Graph Path Fallbacks Test...\n";
    
    // Case 1: Misspelling of "Psoriasis" -> "Psoraisis"
    $response = ChatbotOrchestrator::process("What are the Symptoms of Psoraisis?");
    if ($response['status'] !== 'success' || $response['routing']['intent'] !== 'disease_symptoms') {
        echo "FAIL: Case 1 routing failed. Got status: " . $response['status'] . "\n";
        exit(1);
    }
    
    if (!isset($response['retrieval']['entity']['name']) || $response['retrieval']['entity']['name'] !== 'Psoriasis') {
        echo "FAIL: Expected entity name 'Psoriasis', got " . ($response['retrieval']['entity']['name'] ?? 'null') . "\n";
        exit(1);
    }
    
    echo "PASS: Case 1 (Psoraisis -> Psoriasis)\n";
    
    // Case 2: Dysureia (partial path with no underlying Compound)
    $response2 = legacy_test_process("Which purified compounds are used for Dysureia?", 'integrated_disease_compound');
    if ($response2['status'] !== 'success') {
        echo "FAIL: Case 2 failed. Got status: " . $response2['status'] . "\n";
        exit(1);
    }
    
    $results = $response2['retrieval']['results'] ?? [];
    legacy_assert_facts('integrated_disease_compound', 'Dysuria', $response2['retrieval']);
    if (empty($results)) {
        echo "FAIL: Expected purified compounds for Dysureia, got empty results\n";
        exit(1);
    }
    
    $foundPraval = false;
    foreach ($results as $res) {
        if (in_array('Praval Bhasma', array_column($res['nodes'], 'label'), true)) {
            $foundPraval = true;
            break;
        }
    }
    
    if (!$foundPraval) {
        echo "FAIL: Expected 'Praval Bhasma' in results for Dysureia\n";
        exit(1);
    }
    
    echo "PASS: Case 2 (Dysureia -> Praval Bhasma without full path)\n";
    echo "ALL TESTS PASSED: Phase 15 Bugfixes\n";
}

test_phase15_bugfixes();
