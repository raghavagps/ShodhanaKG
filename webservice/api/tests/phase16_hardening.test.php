<?php
require_once __DIR__."/legacy_contract_helper.php";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';
require_once __DIR__ . '/../core/EntityResolver.php';

function test_phase16_hardening() {
    echo "Starting Phase 16 - Provenance Hardening & Final Regression Test...\n";
    $allPass = true;

    // Helper to print test result
    $assert = function($condition, $testName) use (&$allPass) {
        if ($condition) {
            echo "PASS: $testName\n";
        } else {
            echo "FAIL: $testName\n";
            $allPass = false;
        }
    };

    // 1. Dysureia (Partial Path) should NOT contain 'Unknown Compound' or 'Unknown Method' placeholders
    $response = legacy_test_process("Which purified compounds are used for Dysureia?", 'integrated_disease_compound');
    $assert($response['status'] === 'success', "Dysureia routing success");
    
    $results = $response['retrieval']['results'] ?? [];
    legacy_assert_facts('integrated_disease_compound', 'Dysuria', $response['retrieval']);
    $hasPraval = false;
    $hasUnknownCompound = false;
    $hasUnknownMethod = false;
    $hasCanonicalIdentities = true;

    foreach ($results as $res) {
        if (in_array('Praval Bhasma', array_column($res['nodes'], 'label'), true)) {
            $hasPraval = true;
        }
        if (in_array('Unknown Compound', array_column($res['nodes'], 'label'), true)) {
            $hasUnknownCompound = true;
        }
        if (in_array('Unknown Method', array_column($res['nodes'], 'label'), true)) {
            $hasUnknownMethod = true;
        }
        foreach ($res['nodes'] as $node) if (empty($node['id']) || empty($node['type']) || empty($node['label'])) $hasCanonicalIdentities = false;
    }
    
    $assert($hasPraval, "Dysureia (Praval Bhasma) fetched successfully");
    $assert(!$hasUnknownCompound, "Dysureia response contains no 'Unknown Compound' literal");
    $assert(!$hasUnknownMethod, "Dysureia response contains no 'Unknown Method' literal");
    // The frozen graph now has 39 complete Dysuria paths. Do not force a null
    // method onto supported nodes; the independent oracle above verifies every RID.
    $assert($hasCanonicalIdentities, "Dysuria canonical paths contain real node identities instead of placeholders");

    // 2. Malicious fuzzy-resolution inputs (Security)
    $maliciousQueries = [
        "What are the Symptoms of <script>alert(1)</script>?",
        "What are the Symptoms of ../../etc/passwd?",
        "What are the Symptoms of OR 1=1?",
        "What are the Symptoms of " . str_repeat("A", 1000) . "?"
    ];

    foreach ($maliciousQueries as $q) {
        $secRes = ChatbotOrchestrator::process($q);
        // It shouldn't crash or throw a raw exception, and it shouldn't execute anything malicious
        $assert(isset($secRes['status']), "Malicious fuzzy resolution handled gracefully: " . substr($q, 0, 50));
    }

    // 3. Complete integrated path vs Cartesian product (Diabetes Mellitus)
    $diabetesRes = legacy_test_process("Which purified compounds are used for Diabetes Mellitus?", 'integrated_disease_compound');
    $assert($diabetesRes['status'] === 'success', "Diabetes Mellitus integration query success");
    
    $diabResults = $diabetesRes['retrieval']['results'] ?? [];
    legacy_assert_facts('integrated_disease_compound', 'Diabetes Mellitus', $diabetesRes['retrieval']);
    $hasCorrelatedProps = true;
    foreach ($diabResults as $dres) {
        if (empty($dres['edges']) || count(array_filter($dres['edges'], static function($e) { return isset($e['properties']); })) !== count($dres['edges'])) {
            $hasCorrelatedProps = false;
        }
        if (($dres['compound'] ?? '') === 'Unknown Compound' || ($dres['shodhana_method'] ?? '') === 'Unknown Method') {
            $assert(false, "Diabetes Mellitus result contains placeholder 'Unknown ...'");
        }
    }
    $assert($hasCorrelatedProps, "Diabetes Mellitus results preserve exact edge-scoped provenance properties");

    if ($allPass) {
        echo "ALL TESTS PASSED: Phase 16 Hardening\n";
    } else {
        echo "SOME TESTS FAILED in Phase 16 Hardening\n";
        exit(1);
    }
}

test_phase16_hardening();
