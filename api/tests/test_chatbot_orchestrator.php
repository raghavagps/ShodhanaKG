<?php
require_once __DIR__."/legacy_contract_helper.php";
require_once __DIR__.'/stored_node_contract.php';
$_SERVER['REQUEST_METHOD'] = 'CLI';
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';

function runTest($question, $expectedStatus, $expectedEndpoint = null, $expectedResultCount = -1) {
    echo "Q: \"$question\"\n";
    $result = legacy_test_process($question, $expectedEndpoint);
    
    if ($result['status'] !== $expectedStatus) {
        echo "[FAIL] Expected status '$expectedStatus', got '{$result['status']}'\n";
        if (isset($result['message'])) echo "Message: {$result['message']}\n";
        return false;
    }
    
    if ($expectedEndpoint === 'node_property') {
        assert_stored_node_contract($result['node_answer']);
        if (($result['node_answer']['node_evidence']['source'] ?? '') !== 'stored_node_properties' || (($expectedStatus === 'success') !== !empty($result['node_answer']['node_evidence']['properties']))) throw new RuntimeException('Missing node property invented data');
        return true;
    }
    if ($expectedStatus === 'success' || $expectedStatus === 'no_data') {
        if ($result['routing']['endpoint'] !== $expectedEndpoint) {
            echo "[FAIL] Expected endpoint '$expectedEndpoint', got '{$result['routing']['endpoint']}'\n";
            return false;
        }
        
        $count = count($result['retrieval']['results']);
        if ($expectedResultCount !== -1 && $count !== $expectedResultCount) {
            echo "[FAIL] Expected $expectedResultCount results, got $count\n";
            return false;
        }
        
        // Anti-hallucination Integrity Check
        $func = 'execute_' . $expectedEndpoint;
        $directData = $func($result['routing']['entity'], false);
        
        // If normalizer outputs an empty array, Orchestrator changes status to no_data. Let's align directData status for comparison.
        if (empty($directData['results'])) {
            $directData['status'] = 'no_data';
        }
        
        // Normalize JSON to ignore array ordering if associative vs sequential, but standard json_encode works well for strict equality here.
        if (json_encode($result['retrieval']) !== json_encode($directData)) {
            echo "[FAIL] INTEGRITY MISMATCH! Orchestrator altered the canonical payload.\n";
            return false;
        }
    }
    
    echo "[PASS] Status: {$result['status']}\n\n";
    return true;
}

$allPass = true;

echo "=== Compound (Botanical) Tests ===\n";
$allPass = runTest("What media is used for Croton tiglium Linn.?", "success", "compound_media") && $allPass;
$allPass = runTest("What validation studies are available for Croton tiglium Linn.?", "success", "compound_validation") && $allPass;
$allPass = runTest("What toxic substances are present in Croton tiglium Linn.?", "success", "compound_toxicity") && $allPass;
$allPass = runTest("What detoxification evidence exists for Croton tiglium Linn.?", "success", "compound_detoxification") && $allPass;
$allPass = runTest("How is Croton tiglium Linn. purified?", "success", "compound_shodhana_process") && $allPass;
$allPass = runTest("What are the therapeutic uses of Croton tiglium Linn.?", "success", "compound_therapeutic_use") && $allPass;

echo "=== Compound (Mineral) Tests ===\n";
$allPass = runTest("What media are used for Copper?", "success", "compound_media") && $allPass;
$allPass = runTest("What validation studies are available for Copper?", "success", "compound_validation") && $allPass;
$allPass = runTest("What media are used for Zinc?", "success", "compound_media") && $allPass;
$allPass = runTest("What validation studies are available for Zinc?", "success", "compound_validation") && $allPass;

echo "=== Disease Tests ===\n";
$allPass = runTest("What are the symptoms of Alopecia?", "success", "disease_symptoms") && $allPass;
$allPass = runTest("What therapies are associated with Alopecia?", "success", "disease_therapy") && $allPass;
$allPass = runTest("What formulations are associated with Ankylosing Spondylitis?", "success", "disease_formulation") && $allPass;

echo "=== Integrated Tests ===\n";
// As validated, Croton tiglium lacks USED_FOR_TREATMENT edges
$allPass = runTest("Which disease is treated by Croton tiglium Linn.?", "no_data", "integrated_compound_disease", 0) && $allPass;

echo "=== Negative State Tests ===\n";
$allPass = runTest("Tell me about Copper.", "success", "node_property") && $allPass;
$allPass = runTest("Tell me about Croton tiglium.", "needs_clarification") && $allPass;
$allPass = runTest("What is the molecular weight of Copper?", "no_data", "node_property") && $allPass;
$allPass = runTest("Who discovered Copper?", "unsupported") && $allPass;
$allPass = runTest("What is the chemical structure of Croton tiglium?", "needs_clarification") && $allPass;
$allPass = runTest("What media is used for XYZABC?", "entity_not_found") && $allPass;

// Valid Query with No Evidence (Alopecia Formulations return 0 due to EvidenceCorrelator)
$allPass = runTest("What formulations are associated with Alopecia?", "success", "disease_formulation") && $allPass;


echo "=== Disease Extended Tests ===\n";
$allPass = runTest("What dosha imbalance is associated with Alopecia?", "success", "disease_dosha") && $allPass;
$allPass = runTest("What is the Ayurvedic diagnosis for Alopecia?", "needs_clarification") && $allPass;
$allPass = runTest("Which Ayurvedic diagnoses are linked to Alopecia?", "success", "disease_diagnosis") && $allPass;
$allPass = runTest("What assessment parameters are used for Alopecia?", "success", "disease_assessment") && $allPass;
$allPass = runTest("What compounds treat Alopecia?", "success", "integrated_disease_compound") && $allPass;
$allPass = runTest("What therapies are used to treat Alopecia?", "success", "disease_therapy") && $allPass;

echo "=== Purified Compound Extended Tests ===\n";
$allPass = runTest("What formulations contain Shodhita Parada?", "success", "purified_compound_formulation") && $allPass;
$allPass = runTest("What raw compound is the source of Shodhita Parada?", "success", "purified_compound_source") && $allPass;

echo "=== Compound Extended Tests ===\n";
$allPass = runTest("What validation studies exist for Zinc?", "success", "compound_validation") && $allPass;
$allPass = runTest("How is Croton tiglium Linn. detoxified?", "success", "compound_detoxification") && $allPass;
$allPass = runTest("What purification does Copper undergo?", "needs_clarification") && $allPass;
$allPass = runTest("What are the therapeutic uses of Copper?", "no_data", "compound_therapeutic_use") && $allPass;
$allPass = runTest("What diseases are linked to Copper?", "success", "integrated_compound_disease") && $allPass;

echo "=== Indication Tests ===\n";
$allPass = runTest("Which compounds are indicated for Cough?", "success", "indication_compound") && $allPass;


echo "=== Missing 22 Endpoints Coverage ===\n";
// Assessment_Parameter
$allPass = runTest("What diseases use 6 Min Walk Test as an assessment parameter?", "success", "assessment_disease") && $allPass;

// Ayurvedic_Diagnosis
$allPass = runTest("What modern diseases map to Khalitya?", "success", "ayurvedic_diagnosis_disease") && $allPass;

// Shodhana_Type (Classification)
$allPass = runTest("What processes belong to Samanya Shodhana?", "success", "classification_process") && $allPass;

// Compound
$allPass = runTest("What are the purified forms of Copper?", "success", "compound_purified_forms") && $allPass;

// Confirmation_Study
$allPass = runTest("What methods were used in Cytotoxicity Study?", "success", "confirmation_study_method") && $allPass;
$allPass = runTest("What purified compounds were evaluated in Cytotoxicity Study?", "success", "confirmation_study_purified_compound") && $allPass;

// Dosha
$allPass = runTest("What diseases are caused by Vata imbalance?", "needs_clarification") && $allPass;
$allPass = runTest("Which diseases are associated with Vata dosha?", "success", "dosha_disease") && $allPass;

// Formulation
$allPass = runTest("What compounds are in Triphala Churna?", "no_data", "formulation_compound") && $allPass;
$allPass = runTest("What therapies use Triphala Churna?", "success", "formulation_therapy") && $allPass;

// Media
$allPass = runTest("Which compounds are purified with Cow Milk?", "success", "media_compound") && $allPass;

// Process (Shodhana_Method)
$allPass = runTest("How is Nirvapa classified?", "success", "process_classification") && $allPass;
$allPass = runTest("Which compounds undergo Nirvapa?", "success", "process_compound") && $allPass;
$allPass = runTest("What media is used during Nirvapa?", "success", "process_media") && $allPass;
$allPass = runTest("What purified compounds are produced by Nirvapa?", "success", "process_purified_compound") && $allPass;
// Independently verified direct VALIDATED_BY edges include XRD, ref 34872806.
// The former no_data expectation encoded the reversed FOLLOWED_BY bug.
$allPass = runTest("What validation studies exist for Nirvapa?", "success", "shodhana_method_validation") && $allPass;

// Purified_Compound
$allPass = runTest("What diseases are treated by Shodhita Parada?", "success", "purified_compound_disease") && $allPass;
$allPass = runTest("What validation exists for Shodhita Parada?", "success", "purified_compound_validation") && $allPass;

// Symptom
$allPass = runTest("What diseases have Abdominal Distension as a symptom?", "success", "symptoms_disease") && $allPass;

// Therapy
$allPass = runTest("What diseases are treated by Swedana?", "success", "therapy_disease") && $allPass;

// Toxic_Substance
$allPass = runTest("Which compounds contain Aconitine?", "success", "toxic_substance_compound") && $allPass;
$allPass = runTest("How is Aconitine removed?", "success", "toxic_substance_method") && $allPass;

if ($allPass) {
    echo "\n=== ALL ORCHESTRATION TESTS PASSED ===\n";
    exit(0);
} else {
    echo "\n=== SOME TESTS FAILED ===\n";
    exit(1);
}
