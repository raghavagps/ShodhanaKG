<?php
require_once __DIR__."/legacy_contract_helper.php";
$_SERVER['REQUEST_METHOD'] = 'CLI';

// No mock server! We use real ArcadeDB on port 2480.
require_once __DIR__ . '/../core/QuestionRouter.php';
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';

echo "==================================================\n";
echo "PHASE 7 — COMPREHENSIVE FUNCTIONAL QUERY AUDIT\n";
echo "==================================================\n\n";

// 1. ENVIRONMENT VERIFICATION
$arcadePort = 2480;
$fpArcade = @fsockopen('localhost', $arcadePort, $errno, $errstr, 2);
if ($fpArcade) {
    echo "ArcadeDB: ONLINE\n";
    fclose($fpArcade);
} else {
    echo "ArcadeDB: OFFLINE\n";
    echo "ERROR: ArcadeDB must be running on port 2480 for real query matrix tests.\n";
    exit(1);
}

// Ensure config points to real DB
putenv("ARCADE_URL=http://localhost:2480");
// You might need to set DB, User, Pass if they aren't default in config.php.

$results = [
    'total' => 0,
    'passed' => 0,
    'expected_negative' => 0,
    'failures' => 0,
    'SCHEMA_LIMITATION' => 0,
    'UNSUPPORTED_QUERY' => 0,
    'ENTITY_RESOLUTION_FAILURE' => 0,
    'ROUTER_FAILURE' => 0,
    'ENDPOINT_FAILURE' => 0,
    'DATABASE_RETRIEVAL_FAILURE' => 0,
    'PROVENANCE_FAILURE' => 0,
    'details' => []
];

function testRealQuery($query, $expectedEndpoint, $expectedState, $expectedEntityTypes = [], $expectedResultsCount = -1, $clarificationEndpoint = null) {
    global $results;
    $results['total']++;
    
    try {
        $orchestratorResult = legacy_test_process($query, $clarificationEndpoint ?? $expectedEndpoint);
    } catch (Exception $e) {
        $orchestratorResult = ['status' => 'error', 'message' => $e->getMessage()];
    }
    
    $status = $orchestratorResult['status'];
    $routing = isset($orchestratorResult['routing']) ? (array)$orchestratorResult['routing'] : [];
    $actualEndpoint = isset($routing['endpoint']) ? $routing['endpoint'] : 'none';
    $retrieval = isset($orchestratorResult['retrieval']) ? (array)$orchestratorResult['retrieval'] : [];
    $retrievalResults = isset($retrieval['results']) ? $retrieval['results'] : [];
    
    $isExpectedNegative = in_array($expectedState, ['needs_clarification', 'entity_not_found', 'unsupported', 'no_data']);
    $passed = false;
    $failureCategory = '-';
    
    if ($status === $expectedState) {
        if ($isExpectedNegative) {
            $passed = empty($retrievalResults) && ($expectedState !== 'no_data' || $actualEndpoint === $expectedEndpoint);
            if ($passed) $results['expected_negative']++;
            else $failureCategory = 'PROVENANCE_FAILURE';
        } else {
            // For success, we must verify actual retrieval worked
            if ($actualEndpoint === $expectedEndpoint) {
                if ($expectedResultsCount !== -1 && count($retrievalResults) < $expectedResultsCount) {
                    $failureCategory = 'DATABASE_RETRIEVAL_FAILURE';
                } else {
                    $passed = true;
                    foreach ($retrievalResults as $row) if ($expectedEntityTypes && !in_array($row['related_type'] ?? null, $expectedEntityTypes, true)) { $passed=false; $failureCategory='PROVENANCE_FAILURE'; }
                }
            } else {
                $failureCategory = 'ROUTER_FAILURE';
            }
        }
    } else {
        if ($status === 'needs_clarification' || $status === 'entity_not_found') {
            $failureCategory = 'ENTITY_RESOLUTION_FAILURE';
        } else if ($actualEndpoint !== $expectedEndpoint && !empty($expectedEndpoint)) {
            $failureCategory = 'ROUTER_FAILURE';
        } else if ($status === 'error') {
            $failureCategory = 'ENDPOINT_FAILURE';
        } else if ($status === 'no_data' && $expectedState === 'success') {
            $failureCategory = 'DATABASE_RETRIEVAL_FAILURE';
        } else {
            $failureCategory = 'ENDPOINT_FAILURE';
        }
    }
    
    if ($passed) {
        $results['passed']++;
    } else {
        $results['failures']++;
        if (isset($results[$failureCategory])) {
            $results[$failureCategory]++;
        }
    }
    
    $results['details'][] = [
        'query' => $query,
        'endpoint' => $actualEndpoint,
        'expectedState' => $expectedState,
        'actualState' => $status,
        'result' => $passed ? ($isExpectedNegative ? 'EXPECTED NEGATIVE' : 'PASS') : 'FAILURE',
        'failureCategory' => $failureCategory,
        'retrievedCount' => count($retrievalResults)
    ];
}

// --------------------------------------------------
// 1. COMPOUND
// --------------------------------------------------
testRealQuery("What is the Shodhana method for Copper?", "compound_methods", "success", [], 1);
testRealQuery("How is Copper purified?", "compound_shodhana_process", "success", [], 1);
testRealQuery("Which media are used for Copper Shodhana?", "compound_media", "success", [], 1);
testRealQuery("What purified compound is produced from Copper?", "compound_purified_forms", "success", [], 1);
testRealQuery("How is Copper validated?", "compound_validation", "success", [], 0); // May not have data
testRealQuery("What toxic substance is associated with Copper?", "compound_toxicity", "no_data", [], 0);
testRealQuery("Which diseases are treated by purified products derived from Copper?", "integrated_compound_disease", "success", [], 0);

// --------------------------------------------------
// 2. SHODHANA METHOD / PROCESS
// --------------------------------------------------
testRealQuery("What media are used for Mardana Shodhana?", "process_media", "success", [], 1);
testRealQuery("Name any 5 media used for Mardana Shodhana.", "process_media", "success", [], 1);
testRealQuery("Which compounds undergo Nirvapa?", "process_compound", "success", [], 1);
testRealQuery("Which compounds undergo Swedana?", "process_compound", "success", [], 1);
testRealQuery("What media are used during Nirvapa?", "process_media", "success", [], 1);

// --------------------------------------------------
// 3. MEDIA
// --------------------------------------------------
testRealQuery("Which compounds use Sesame Oil?", "media_compound", "success", [], 1);
testRealQuery("Which Shodhana methods use Sesame Oil?", "media_process", "success", [], 1);
testRealQuery("What is Sesame Oil used for in Shodhana?", "none", "needs_clarification", [], 0);

// --------------------------------------------------
// 4. PURIFIED COMPOUND
// --------------------------------------------------
testRealQuery("Which purified compound is produced from Copper?", "compound_purified_forms", "success", [], 1);
testRealQuery("Which diseases are treated by Shodhita Gunja?", "purified_compound_disease", "success", [], 1);
testRealQuery("Which compound produces Shodhita Gunja?", "purified_compound_source", "success", [], 1);
testRealQuery("Which purified compounds are indicated for Fever?", "semantic_query", "success", ["Purified_Compound"], 1, "indication_compound");

// --------------------------------------------------
// 5. DISEASE / INDICATION
// --------------------------------------------------
testRealQuery("What symptoms are associated with Alopecia?", "disease_symptoms", "success", [], 1);
testRealQuery("What therapies are associated with Alopecia?", "disease_therapy", "success", [], 1);
testRealQuery("What formulations are associated with Alopecia?", "disease_formulation", "success", [], 0);
testRealQuery("Which doshas are associated with Alopecia?", "disease_dosha", "success", [], 1);
testRealQuery("What diagnosis is associated with Alopecia?", "disease_diagnosis", "success", [], 1);
testRealQuery("What assessment parameters are associated with Alopecia?", "disease_assessment", "success", [], 0);

// --------------------------------------------------
// 6. INTEGRATED
// --------------------------------------------------
testRealQuery("What is produced from Copper that treats Alopecia?", "none", "needs_clarification", [], 0);

// --------------------------------------------------
// 7. GENERIC / NEGATIVE / UNSUPPORTED
// --------------------------------------------------
testRealQuery("Tell me everything about Shodhana.", "none", "needs_clarification");
testRealQuery("Tell me about disease.", "none", "needs_clarification");
testRealQuery("Tell me about media.", "none", "needs_clarification");
testRealQuery("Which compound?", "none", "needs_clarification");
testRealQuery("What does this treat?", "none", "unsupported");
testRealQuery("Give me information.", "none", "needs_clarification");

testRealQuery("Which media are used for XYZ123?", "none", "entity_not_found");
testRealQuery("Which compound produces FakeCompound?", "none", "needs_clarification");
testRealQuery("Which disease is treated by NonexistentCompound?", "none", "entity_not_found");

testRealQuery("What diseases have Fever?", "none", "needs_clarification");


// Print Table
echo sprintf("%-60s | %-30s | %-15s | %-15s | %-10s | %-20s | %-5s\n", "Query", "Endpoint", "Expected", "Actual", "Result", "Failure Cat", "Count");
echo str_repeat("-", 170) . "\n";
foreach ($results['details'] as $row) {
    echo sprintf("%-60s | %-30s | %-15s | %-15s | %-10s | %-20s | %-5d\n", 
        substr($row['query'], 0, 57) . (strlen($row['query']) > 57 ? '...' : ''), 
        substr($row['endpoint'], 0, 30), 
        $row['expectedState'], 
        $row['actualState'], 
        $row['result'], 
        $row['failureCategory'],
        $row['retrievedCount']
    );
}

echo "\n==================================================\n";
echo "1. Total tests: {$results['total']}\n";
echo "2. Passed: {$results['passed']}\n";
echo "3. Expected negative cases: {$results['expected_negative']}\n";
echo "4. Actual failures: {$results['failures']}\n";
echo "5. Router failures: {$results['ROUTER_FAILURE']}\n";
echo "6. Entity-resolution failures: {$results['ENTITY_RESOLUTION_FAILURE']}\n";
echo "7. Endpoint failures: {$results['ENDPOINT_FAILURE']}\n";
echo "8. Retrieval/provenance failures: {$results['DATABASE_RETRIEVAL_FAILURE']}\n";
echo "9. Schema limitations: {$results['SCHEMA_LIMITATION']}\n";
echo "==================================================\n";

exit($results['failures'] ? 1 : 0);
