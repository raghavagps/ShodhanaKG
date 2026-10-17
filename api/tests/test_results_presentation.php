<?php
require_once __DIR__ . '/../core/ResultsPresentation.php';
function checkPresentation($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$sample = json_decode(ltrim(file_get_contents(__DIR__ . '/fixtures/results_first_psoriasis.json'), "\xEF\xBB\xBF"), true);
$retrieval = $sample['context']['retrieval'];
$original = $retrieval;
$answer = ResultsPresentation::build($retrieval);
checkPresentation($retrieval === $original, 'Retrieval mutated');
checkPresentation(count($answer['answer_results']) === 19 && count($answer['citations']) === 38, 'Grouping lost entities or evidence');
$indices = array_merge(...array_column($answer['answer_results'], 'record_indices')); sort($indices);
checkPresentation($indices === range(0, 37), 'Evidence duplicated or omitted');
checkPresentation($answer['generation']['mode'] === 'deterministic', 'Unexpected model generation');
foreach ($answer['citations'] as $i => $citation) {
    checkPresentation($citation['record_indices'] === [$i] && $citation['fact_ids'] === ['f' . ($i + 1)], 'Attribution changed');
    checkPresentation($citation['scopes'][0]['relationship'] === 'Disease → Symptom', 'Semantic relationship missing');
}
$retrieval['results'][0]['novel_property'] = 'Future field';
$answer = ResultsPresentation::build($retrieval);
checkPresentation(strpos(json_encode($answer['citations'][0]['scopes']), 'Future field') !== false, 'Unknown property lost');
checkPresentation($answer['answer_results'][0]['label'] === 'Pain', 'Property mistaken for entity');
$cases = json_decode(file_get_contents(__DIR__ . '/fixtures/factual_evaluation_cases_v3.json'), true);
foreach ($cases as $case) {
    $answer = ResultsPresentation::build(['status' => 'success', 'entity' => ['name' => $case['expected_entity']], 'results' => $case['expected_results']]);
    checkPresentation($answer['status'] === 'success', 'Canonical construction failed');
    foreach ($case['expected_results'] as $i => $row) foreach ($row['edges'] as $j => $edge) {
        $scope = $answer['citations'][$i]['scopes'][$j];
        $nodes = array_column($row['nodes'], null, 'id');
        checkPresentation($scope['source_id'] === $edge['source'] && $scope['target_id'] === $edge['target'] && $scope['edge_type'] === $edge['type'], 'Direction or type lost');
        checkPresentation($scope['properties'] === $edge['properties'], 'Edge properties changed');
        checkPresentation($scope['entities'][0]['label'] === $nodes[$edge['source']]['label'], 'Inverse traversal reversed evidence');
    }
}
echo "PASS results presentation: 19 entities / 38 citations, immutable facts, arbitrary properties, 12 canonical fixtures with directed edge attribution\n";

// Explicit schema-chain expectations, independent of the formatter's catalog.
$chains = [
    'compound_shodhana_process' => ['Compound → Shodhana Method', 'Shodhana Method → Media'],
    'media_compound' => ['Shodhana Method → Media', 'Compound → Shodhana Method'],
    'disease_formulation' => ['Disease → Therapy', 'Therapy → Formulation'],
    'formulation_disease' => ['Therapy → Formulation', 'Disease → Therapy']
];
$captured = json_decode(file_get_contents(__DIR__ . '/fixtures/results_evaluation_answers_v1.json'), true);
$checked = 0;
foreach ($captured['rows'] as $sample) {
    $endpoint = $sample['response']['context']['routing']['endpoint'];
    if (!isset($chains[$endpoint])) continue;
    $retrieval = $sample['response']['context']['retrieval'];
    $original = $retrieval;
    $answer = ResultsPresentation::build($retrieval);
    checkPresentation($retrieval === $original, 'Relationship rendering mutated retrieval');
    checkPresentation($answer['answer_results'] === $sample['response']['answer_results'], 'Relationship fix changed result grouping or fact IDs');
    foreach ($answer['citations'] as $i => $citation) {
        checkPresentation($citation['relationships'] === $chains[$endpoint], 'Incomplete chain: ' . $endpoint);
        checkPresentation($citation['attribution'] === 'record', 'Flat record gained fabricated edge precision');
        foreach ($citation['scopes'] as $j => $scope) {
            checkPresentation(!isset($scope['relationship']) && !isset($scope['edge_id']), 'Invented scope relationship/edge');
            checkPresentation($scope['properties'] === $sample['response']['citations'][$i]['scopes'][$j]['properties'], 'Scope properties changed');
        }
        $checked++;
    }
}
checkPresentation($checked === 160, 'Did not exercise all four regression cases');
$ambiguous = ['status' => 'success', 'entity' => ['name' => 'Test seed'],
    'path' => ['Method/Process', 'USES_MEDIA', 'Media'],
    'results' => [['media' => 'Test medium', 'properties' => ['pubmed_id' => '111']]]];
checkPresentation(ResultsPresentation::build($ambiguous)['citations'][0]['relationships'] === [], 'Ambiguous union guessed a type');
$process = $ambiguous;
$process['path'][0] = 'Process';
checkPresentation(ResultsPresentation::build($process)['citations'][0]['relationships'] === ['Process → Media'], 'Process incorrectly relabeled as method');
$partial = $ambiguous;
$partial['path'] = ['Compound', 'UNDERGOES', 'Shodhana_Method', 'UNKNOWN_EDGE', 'Media'];
$partialAnswer = ResultsPresentation::build($partial);
checkPresentation(!isset($partialAnswer['citations'][0]['scopes'][0]['relationship']), 'Incomplete multi-hop chain presented as one edge');
echo "PASS 160 legacy chain cards, inverse direction, property/ID preservation and unresolved-type safeguards\n";
