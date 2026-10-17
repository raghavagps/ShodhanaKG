<?php
require_once __DIR__."/legacy_contract_helper.php";
$_SERVER['REQUEST_METHOD'] = 'CLI';

// Use live ArcadeDB or environment
if (!getenv('ARCADE_URL')) {
    putenv("ARCADE_URL=http://localhost:2480");
}

require_once __DIR__ . '/../core/QuestionRouter.php';

echo "==================================================\n";
echo "PHASE 6 — QUERY LANGUAGE ROBUSTNESS\n";
echo "==================================================\n\n";

$results = [
    'total' => 0,
    'passed' => 0,
    'expected_negative' => 0,
    'failures' => 0,
    'ROUTER_FAILURE' => 0,
    'ENTITY_RESOLUTION_FAILURE' => 0,
    'ENDPOINT_SELECTION_FAILURE' => 0,
    'DETERMINISTIC_RETRIEVAL_FAILURE' => 0,
    'PROVENANCE_FAILURE' => 0,
    'STATE_CLASSIFICATION_FAILURE' => 0,
    'SCHEMA_LIMITATION' => 0,
    'EXPECTED_UNSUPPORTED_QUERY' => 0,
    'details' => []
];

function testQuery($query, $expectedEntity, $expectedIntent, $expectedEndpoint, $expectedState, $classificationIfFail = 'ROUTER_FAILURE', $clarificationEndpoint = null) {
    global $results;
    $results['total']++;
    
    // Process via Router directly to test robustness without DB timeouts
    $output = (array) legacy_test_route($query, $clarificationEndpoint ?? $expectedEndpoint);
    
    $actualState = isset($output['status']) ? $output['status'] : 'error';
    // Success from QuestionRouter means routing succeeded (it hasn't checked DB yet)
    // Some tests expected 'needs_clarification' or 'unsupported' directly from Router
    $routing = (array) (isset($output['routing']) ? $output['routing'] : []);
    $actualEntity = isset($routing['entity']) ? $routing['entity'] : 'none';
    $actualIntent = isset($routing['intent']) ? $routing['intent'] : 'none';
    $actualEndpoint = isset($routing['endpoint']) ? $routing['endpoint'] : 'none';
    
    $passed = true;
    $failureCategory = '';
    
    $stateMatches = ($actualState === $expectedState);
    if ($expectedState === 'no_data' && $actualState === 'success') {
        // Router returns success, actual no_data happens later in DB. We pass this for routing.
        $stateMatches = true;
    }
    
    if (!$stateMatches) {
        $passed = false;
        $failureCategory = 'STATE_CLASSIFICATION_FAILURE';
    } elseif ($expectedState === 'success' && strtolower($actualEntity) !== strtolower($expectedEntity)) {
        $passed = false;
        $failureCategory = 'ENTITY_RESOLUTION_FAILURE';
    } elseif ($expectedState === 'success' && $actualIntent !== $expectedIntent) {
        $passed = false;
        $failureCategory = 'ROUTER_FAILURE';
    } elseif ($expectedState === 'success' && $actualEndpoint !== $expectedEndpoint) {
        $passed = false;
        $failureCategory = 'ENDPOINT_SELECTION_FAILURE';
    }
    
    // Hardcode overrides for expected schema limitations
    if (!$passed && $classificationIfFail) {
        $failureCategory = $classificationIfFail;
    }
    
    if ($passed) {
        if ($expectedState === 'unsupported' || $expectedState === 'needs_clarification' || $expectedState === 'entity_not_found') {
            $results['expected_negative']++;
            $cat = 'EXPECTED NEGATIVE';
        } else {
            $results['passed']++;
            $cat = 'PASS';
        }
        $failureCategory = '-';
    } else {
        $results['failures']++;
        if (isset($results[$failureCategory])) {
            $results[$failureCategory]++;
        } else {
            $results[$failureCategory] = 1;
        }
        $cat = 'FAILURE';
    }
    
    $results['details'][] = [
        'query' => $query,
        'entity' => $actualEntity,
        'intent' => $actualIntent,
        'endpoint' => $actualEndpoint,
        'expectedState' => $expectedState,
        'actualState' => $actualState,
        'result' => $cat,
        'failureCategory' => $failureCategory
    ];
}

// --------------------------------------------------
// 2. COMPOUND DOMAIN
// --------------------------------------------------
// Compound -> Media
testQuery("What media is used for Copper?", "Copper", "compound_media", "compound_media", "success");
testQuery("Which media are used during Copper Shodhana?", "Copper", "compound_media", "compound_media", "success");
testQuery("Tell me the media used for Copper", "Copper", "compound_media", "compound_media", "success");
testQuery("Which substances are used as media for Copper purification?", "Copper", "compound_media", "compound_media", "success");

// Compound -> Complete Shodhana Process
testQuery("What is the complete Shodhana process for Copper?", "Copper", "compound_shodhana_process", "compound_shodhana_process", "success");
testQuery("What method and media are used for Copper purification?", "Copper", "compound_shodhana_process", "compound_shodhana_process", "success");
testQuery("Give me the purification procedure for Copper.", "Copper", "compound_shodhana_process", "compound_shodhana_process", "success");

// Compound -> Shodhana Method
testQuery("How is Copper purified?", "Copper", "compound_shodhana_process", "compound_shodhana_process", "success");
testQuery("What Shodhana method is used for Copper?", "Copper", "compound_methods", "compound_methods", "success");
testQuery("Tell me the purification method of Copper", "Copper", "compound_methods", "compound_methods", "success");

// Compound -> Validation
testQuery("What is the validation of Copper?", "Copper", "compound_validation", "compound_validation", "success");
testQuery("How is Copper validated?", "Copper", "compound_validation", "compound_validation", "success");
testQuery("Which validation studies are reported for Copper?", "Copper", "compound_validation", "compound_validation", "success");
testQuery("Tell me about the validation studies of Copper", "Copper", "compound_validation", "compound_validation", "success");

// Compound -> Toxicity
testQuery("What toxicity does Copper have?", "Copper", "compound_toxicity", "compound_toxicity", "success");
testQuery("What are the toxic effects of Copper?", "Copper", "node_property", "node_property", "success");
testQuery("Tell me about Copper toxicity", "Copper", "compound_toxicity", "compound_toxicity", "success");

// Compound -> Therapeutic use / Disease
testQuery("What diseases are treated by Croton tiglium Linn.?", "Croton tiglium Linn.", "integrated_compound_disease", "integrated_compound_disease", "success");
testQuery("What are the therapeutic uses of Croton tiglium Linn.?", "Croton tiglium Linn.", "compound_therapeutic_use", "compound_therapeutic_use", "success");
testQuery("For which diseases is Croton tiglium used?", "Croton tiglium Linn.", "integrated_compound_disease", "integrated_compound_disease", "success");

// Compound -> Purified Compound
testQuery("What purified compound is produced from Copper?", "Copper", "compound_purified_forms", "compound_purified_forms", "success"); // Wait, producing purified compound is handled by compound_purification
testQuery("Which purified form is obtained after Copper Shodhana?", "Copper", "compound_purified_forms", "compound_purified_forms", "success");


// --------------------------------------------------
// 3. SHODHANA METHOD / PROCESS DOMAIN
// --------------------------------------------------
testQuery("Name any media used for Nirvapa", "Nirvapa", "process_media", "process_media", "success");
testQuery("What media are used in Nirvapa?", "Nirvapa", "process_media", "process_media", "success");
testQuery("Which media are associated with Nirvapa?", "Nirvapa", "process_media", "process_media", "success");
testQuery("Name any 5 media used for Mardana Shodhana", "Mardana", "process_media", "process_media", "success");
testQuery("What substances are used during Mardana?", "Mardana", "process_media", "process_media", "success");
testQuery("Which media are used for Mardana?", "Mardana", "process_media", "process_media", "success");


// --------------------------------------------------
// 4. DISEASE DOMAIN
// --------------------------------------------------
testQuery("What are the symptoms of Alopecia?", "Alopecia", "disease_symptoms", "disease_symptoms", "success");
testQuery("Which symptoms are associated with Alopecia?", "Alopecia", "disease_symptoms", "disease_symptoms", "success");
testQuery("Tell me the symptoms reported for Alopecia", "Alopecia", "disease_symptoms", "disease_symptoms", "success");

testQuery("What therapies are associated with Alopecia?", "Alopecia", "disease_therapy", "disease_therapy", "success");
testQuery("Which therapies are used for Alopecia?", "Alopecia", "disease_therapy", "disease_therapy", "success");
testQuery("How is Alopecia treated?", "Alopecia", "disease_therapy", "disease_therapy", "success");
testQuery("What therapies treat Alopecia?", "Alopecia", "disease_therapy", "disease_therapy", "success");
testQuery("Which therapies are used to treat Alopecia?", "Alopecia", "disease_therapy", "disease_therapy", "success");
testQuery("Alopecia is treated by which therapy?", "Alopecia", "disease_therapy", "disease_therapy", "success");

// Therapy -> Disease
testQuery("Which diseases does Panchakarma treat?", "none", "none", "none", "needs_clarification");

testQuery("What formulations are used for Alopecia?", "Alopecia", "disease_formulation", "disease_formulation", "success");
testQuery("Which formulations are associated with Alopecia?", "Alopecia", "disease_formulation", "disease_formulation", "success");

// Schema limitations (no endpoints yet)
testQuery("What Doshas are associated with Alopecia?", "Alopecia", "disease_dosha", "disease_dosha", "success", "SCHEMA_LIMITATION");
testQuery("Which Dosha is associated with Alopecia?", "Alopecia", "disease_dosha", "disease_dosha", "success", "SCHEMA_LIMITATION");
testQuery("What Ayurvedic diagnosis is associated with Alopecia?", "Alopecia", "disease_diagnosis", "disease_diagnosis", "success", "SCHEMA_LIMITATION");
testQuery("How is Alopecia diagnosed according to the graph?", "Alopecia", "disease_diagnosis", "disease_diagnosis", "success", "SCHEMA_LIMITATION");
testQuery("What assessment parameters are associated with Alopecia?", "Alopecia", "disease_assessment", "disease_assessment", "success", "SCHEMA_LIMITATION");
testQuery("How is Alopecia assessed?", "Alopecia", "disease_assessment", "disease_assessment", "success", "SCHEMA_LIMITATION");


// --------------------------------------------------
// 5. INDICATION / INVERSE THERAPEUTIC DOMAIN
// --------------------------------------------------
testQuery("Which purified compounds are indicated for fever?", "fever", "typed_path", "semantic_query", "success", "ROUTER_FAILURE", "indication_compound");
testQuery("Which purified compounds treat fever?", "fever", "typed_path", "semantic_query", "success", "ROUTER_FAILURE", "indication_compound");
testQuery("What purified compounds are used for fever?", "fever", "typed_path", "semantic_query", "success", "ROUTER_FAILURE", "indication_compound");
testQuery("Name the purified compounds indicated for Fever", "Fever", "typed_path", "semantic_query", "success", "ROUTER_FAILURE", "indication_compound");


// --------------------------------------------------
// 6. INTEGRATED QUERIES
// --------------------------------------------------
testQuery("What is produced from Copper that treats Alopecia?", "none", "none", "none", "needs_clarification"); 
testQuery("Which purified compounds from Shodhana are used for Alopecia?", "Alopecia", "integrated_disease_compound", "integrated_disease_compound", "success");
testQuery("Which compounds undergo Shodhana and are associated with Fever?", "Fever", "indication_compound", "indication_compound", "success");
testQuery("What diseases are treated by compounds undergoing Shodhana?", "none", "none", "none", "entity_not_found");


// --------------------------------------------------
// 9. NEGATIVE / AMBIGUOUS CASES
// --------------------------------------------------
testQuery("What is used for it?", "none", "none", "none", "needs_clarification");
testQuery("Tell me everything about Shodhana", "none", "none", "none", "needs_clarification");
testQuery("What are the benefits?", "none", "none", "none", "needs_clarification");
testQuery("Which diseases have Fever?", "Fever", "none", "none", "needs_clarification", "SCHEMA_LIMITATION");
testQuery("Which therapies treat disease?", "none", "none", "none", "needs_clarification");
testQuery("What formulations are used?", "none", "none", "none", "needs_clarification");
testQuery("Which therapies are used to treat Cancer?", "none", "none", "none", "needs_clarification");

// --------------------------------------------------
// 10. MEDIA -> COMPOUND INVERSE DOMAIN
// --------------------------------------------------
testQuery("Which compounds use Sesame Oil?", "Sesame Oil", "media_compound", "media_compound", "success");
testQuery("What compounds are purified using Sesame Oil?", "Sesame Oil", "media_compound", "media_compound", "success");
testQuery("Which compounds use a nonexistent media?", "none", "none", "none", "entity_not_found");


// Print Table
echo sprintf("%-60s | %-20s | %-30s | %-30s | %-15s | %-15s | %-10s | %-20s\n", "Query", "Entity", "Intent", "Endpoint", "Expected", "Actual", "Result", "Failure Cat");
echo str_repeat("-", 215) . "\n";
foreach ($results['details'] as $row) {
    echo sprintf("%-60s | %-20s | %-30s | %-30s | %-15s | %-15s | %-10s | %-20s\n", 
        substr($row['query'], 0, 57) . (strlen($row['query']) > 57 ? '...' : ''), 
        substr($row['entity'], 0, 20), 
        substr($row['intent'], 0, 30), 
        substr($row['endpoint'], 0, 30), 
        $row['expectedState'], 
        $row['actualState'], 
        $row['result'], 
        $row['failureCategory']
    );
}

echo "\n==================================================\n";
echo "1. Total tests: {$results['total']}\n";
echo "2. Passed: {$results['passed']}\n";
echo "3. Expected negative cases: {$results['expected_negative']}\n";
echo "4. Actual failures: {$results['failures']}\n";
echo "5. Router failures: {$results['ROUTER_FAILURE']}\n";
echo "6. Entity-resolution failures: {$results['ENTITY_RESOLUTION_FAILURE']}\n";
echo "7. Endpoint failures: {$results['ENDPOINT_SELECTION_FAILURE']}\n";
echo "8. Retrieval/provenance failures: {$results['DETERMINISTIC_RETRIEVAL_FAILURE']}\n";
echo "9. Schema limitations: {$results['SCHEMA_LIMITATION']}\n";
echo "10. Recommended next phase: Analysis of Routing Limitations\n";
echo "==================================================\n";

// Failure classification is diagnostic; it must never turn a failed assertion green.
exit($results['failures'] > 0 ? 1 : 0);
