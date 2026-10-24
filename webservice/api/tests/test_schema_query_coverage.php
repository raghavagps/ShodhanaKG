<?php
require_once __DIR__."/legacy_contract_helper.php";
$_SERVER['REQUEST_METHOD'] = 'CLI';

// Use live ArcadeDB or environment
if (!getenv('ARCADE_URL')) {
    putenv("ARCADE_URL=http://localhost:2480");
}
echo "Set ARCADE_URL=" . getenv('ARCADE_URL') . "\n";
require_once dirname(__DIR__) . '/core/ChatbotOrchestrator.php';
global $ARCADE_URL;
echo "Global ARCADE_URL=" . $ARCADE_URL . "\n";
echo "Running Schema Query Coverage Tests...\n";
$passed = 0;
$failed = 0;

function assertCoverage($question, $expectedIntent, $expectedEntity, $selectionEndpoint=null) {
    global $passed,$failed;
    $res=legacy_test_process($question,$selectionEndpoint ?? $expectedIntent);
    $route=(array)($res['routing'] ?? []);
    if (in_array($res['status'],['success','no_data'],true) && ($route['intent'] ?? null)===$expectedIntent && ($route['entity_type'] ?? null)===$expectedEntity) {
        if (($res['status']==='success') !== !empty($res['retrieval']['results'])) throw new RuntimeException('Inconsistent retrieval status/results');
        if ($expectedIntent==='typed_path' && ($route['endpoint'] ?? '')!=='semantic_query') throw new RuntimeException('Typed target lost bounded traversal');
        $passed++; echo "[PASS] $question -> $expectedIntent ($expectedEntity)\n";
    } else { $failed++; echo "[FAIL] $question: ".json_encode([$res['status'],$route])."\n"; }
}

// 1. Compound -> Toxic Substance
assertCoverage("Which toxic substances does Croton tiglium contain?", 'compound_toxicity', 'Compound');
assertCoverage("Which compounds contain Aconitine?", 'toxic_substance_compound', 'Toxic_Substance');

// 2. Process / Media
assertCoverage("What media are used for Mardana?", 'process_media', 'Shodhana_Method');
assertCoverage("Which compounds undergo Mardana?", 'process_compound', 'Shodhana_Method');

// 3. Purified Compound / Indication
assertCoverage("What diseases does Shodhita Gunja treat?", 'purified_compound_disease', 'Purified_Compound');
assertCoverage("Which purified compounds treat Fever?", 'typed_path', 'Indication', 'indication_compound');

// 4. Disease -> Symptoms / Dosha / Assessment
assertCoverage("What are the symptoms of Alopecia?", 'disease_symptoms', 'Disease');
assertCoverage("What therapies are used for Alopecia?", 'disease_therapy', 'Disease');
assertCoverage("What dosha imbalance is linked to Alopecia?", 'disease_dosha', 'Disease');
assertCoverage("What assessment parameters are used for Alopecia?", 'disease_assessment', 'Disease');
// Kandu is a curated terminology suggestion, not an exact Symptom node.
$kandu=QuestionRouter::route('Which diseases have Kandu?');
$choices=$kandu['clarification']['candidates'] ?? [];
$labels=array_column($choices,'label'); sort($labels);
if ($kandu['status']!=='needs_clarification' || ($kandu['reason'] ?? '')!=='terminology_confirmation' || $labels!==['Itching','Pruritis'] || array_unique(array_column($choices,'type'))!==['Indication']) throw new RuntimeException('Kandu terminology silently forced into Symptom');
$passed++; echo "[PASS] Kandu requires terminology/type confirmation\n";
assertCoverage('Which diseases have Abdominal Distension as a symptom?', 'symptoms_disease', 'Symptom');
assertCoverage("Which diseases use Vamana therapy?", 'therapy_disease', 'Therapy');

// 5. Formulation
assertCoverage("Which formulations are used for Alopecia?", 'disease_formulation', 'Disease');
assertCoverage("Which diseases are treated by Triphala Guggulu?", 'formulation_disease', 'Formulation');

// 6. Integrated Compound -> Disease (Forward / Inverse)
assertCoverage("What diseases are treated by the purified compound from Croton tiglium?", 'integrated_compound_disease', 'Compound');
assertCoverage("Which compounds are purified to treat Alopecia?", 'integrated_disease_compound', 'Disease');

echo "\n--- SUMMARY ---\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
