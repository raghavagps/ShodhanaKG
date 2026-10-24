<?php
require_once __DIR__."/legacy_contract_helper.php";
$_SERVER['REQUEST_METHOD'] = 'CLI';
require_once __DIR__ . '/../core/QuestionRouter.php';

function assertStatus($question, $expectedStatus, $expectedEndpoint = null, $expectedEntity = null, $clarificationEndpoint = null) {
    echo "Q: \"$question\"\n";
    $result = legacy_test_route($question, $clarificationEndpoint ?? $expectedEndpoint);
    
    if ($result['status'] !== $expectedStatus) {
        echo "[FAIL] Expected status '$expectedStatus', got '{$result['status']}'\n";
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
        return false;
    }
    
    if ($expectedStatus === 'success') {
        if ($result['routing']['endpoint'] !== $expectedEndpoint) {
            echo "[FAIL] Expected endpoint '$expectedEndpoint', got '{$result['routing']['endpoint']}'\n";
            return false;
        }
        if ($expectedEntity !== null && stripos($result['routing']['entity'], $expectedEntity) === false) {
            echo "[FAIL] Expected entity containing '$expectedEntity', got '{$result['routing']['entity']}'\n";
            return false;
        }
    }
    
    echo "[PASS] Status: {$result['status']}" . ($expectedEndpoint ? " -> $expectedEndpoint" : "") . "\n\n";
    return true;
}

$allPass = true;

echo "=== Positive Intent Routing Tests ===\n";
$tests = [
    ["What media is used for Croton tiglium Linn.?", "success", "compound_media", "Croton tiglium Linn."],
    ["How is Copper validated?", "success", "compound_validation", "Copper"],
    ["What toxic substance is present in Datura metel L.?", "success", "compound_toxicity", "Datura metel L."],
    ["What does Shodhana detoxify in Zinc?", "success", "compound_detoxification", "Zinc"],
    ["What purified product is produced from Copper?", "success", "compound_purified_forms", "Copper"],
    ["What is the therapeutic use of Zinc?", "success", "compound_therapeutic_use", "Zinc"],
    
    ["What are the symptoms of Alopecia?", "success", "disease_symptoms", "Alopecia"],
    ["What therapies are used for Ankylosing Spondylitis?", "success", "disease_therapy", "Ankylosing Spondylitis"],
    ["What formulations are associated with Alopecia?", "success", "disease_formulation", "Alopecia"],
    
    ['Which botanical compound is derived from croton?', 'needs_clarification', null, null],
    
    // Process / Media Intent
    ['Name any 5 media used for Mardana Shodhana', 'success', 'process_media', 'Mardana'],
    ['Name any media used for Nirvapa', 'success', 'process_media', 'Nirvapa'],
    ['Which media are used in Mardana Shodhana?', 'success', 'process_media', 'Mardana'],
    
    // Inverse Therapeutic Intent (Indication)
    ['Which purified compound treats fever?', 'success', 'semantic_query', 'fever', 'indication_compound'],
    ['Which purified compounds are used for fever?', 'success', 'semantic_query', 'fever', 'indication_compound'],
    ['What purified compound is indicated for fever?', 'success', 'semantic_query', 'fever', 'indication_compound'],
    
    ["Which disease is treated by Croton tiglium?", "success", "integrated_compound_disease", "Croton tiglium"]
];

foreach ($tests as $t) {
    $allPass = assertStatus($t[0], $t[1], $t[2], $t[3], $t[4] ?? null) && $allPass;
}

echo "=== Entity-Type Ambiguity Tests ===\n";
// Therapy intent for Disease vs Compound
$allPass = assertStatus("What therapies are associated with Alopecia?", "success", "disease_therapy", "Alopecia") && $allPass;
$allPass = assertStatus("What are the therapeutic indications of Croton tiglium Linn.?", "success", "compound_therapeutic_use", "Croton") && $allPass;

echo "=== Unsupported Question Tests ===\n";
$allPass = assertStatus("What is the molecular weight of Copper?", "success", "node_property", "Copper") && $allPass;
$allPass = assertStatus("Who discovered this compound called Zinc?", "unsupported") && $allPass;

echo "=== Needs Clarification / Ambiguity Tests ===\n";
$allPass = assertStatus("Tell me something about Copper.", "success", "node_property", "Copper") && $allPass;
$allPass = assertStatus("What about it?", "needs_clarification") && $allPass;
$allPass = assertStatus("Is this safe?", "needs_clarification") && $allPass;

echo "=== General Platform Tests ===\n";
$allPass = assertStatus("How do I use the Disease Explorer?", "success", null, null) && $allPass;
$allPass = assertStatus("How can I filter the graph?", "success", null, null) && $allPass;

echo "=== End-to-End Orchestration Test ===\n";
// Simulate routing + execution
$q = "What media is used for Croton tiglium Linn.?";
echo "Executing: \"$q\"\n";
$routeRes = QuestionRouter::route($q);
if ($routeRes['status'] === 'success' && $routeRes['routing']['endpoint'] === 'compound_media') {
    echo "[PASS] Router mapped to compound_media.\n";
    require __DIR__ . '/../compound_media.php';
    $data = execute_compound_media($routeRes['routing']['entity']);
    
    if (isset($data['status']) && $data['status'] === 'success' && isset($data['results']) && count($data['results']) > 0) {
        echo "[PASS] End-to-end execution returned canonical JSON with " . count($data['results']) . " results.\n";
    } else {
        echo "[FAIL] E2E returned invalid JSON or 0 results.\n";
        echo json_encode($data) . "\n";
        $allPass = false;
    }
} else {
    echo "[FAIL] Router failed to map to compound_media.\n";
    $allPass = false;
}

if ($allPass) {
    echo "\n=== ALL TESTS PASSED ===\n";
    exit(0);
} else {
    echo "\n=== SOME TESTS FAILED ===\n";
    exit(1);
}
