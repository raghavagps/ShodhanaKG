<?php
require_once __DIR__ . '/EvaluationAssertions.php';
function verify($value, $message) { if (!$value) throw new RuntimeException($message); }
$case = ['id' => 'a', 'question' => 'q', 'expected_status' => 'success'];
EvaluationAssertions::validateCases([$case]);
foreach ([[], [$case, $case], [array_merge($case, ['history' => 'invalid'])], [array_merge($case, ['question' => 123])]] as $bad) {
    try { EvaluationAssertions::validateCases($bad); throw new RuntimeException('Invalid fixture accepted'); }
    catch (InvalidArgumentException $expected) { }
}
$actual = ['status' => 'success', 'routing' => ['endpoint' => 'unexpected'], 'retrieval' => ['status' => 'success', 'entity' => ['name' => 'A', 'type' => 'Compound'], 'path' => ['Compound', 'UNDERGOES', 'Shodhana_Method'], 'results' => [['method' => 'A', 'media' => 'B', 'properties' => ['pubmed_id' => '123']]]]];
verify(!EvaluationAssertions::compare($case, $actual), 'Omitted expectation should not assert');
verify(count(EvaluationAssertions::compare($case + ['expected_endpoint' => null], $actual)) === 1, 'Explicit null must assert');
verify(!EvaluationAssertions::compare($case + ['expected_results' => [['method' => 'A', 'media' => 'B', 'properties' => ['pubmed_id' => '123']]]], $actual), 'Complete tuple matches');
verify(count(EvaluationAssertions::compare($case + ['forbidden_results' => [['properties' => ['pubmed_id' => '123']]]], $actual)) === 1, 'Forbidden evidence detected');
verify((bool)EvaluationAssertions::compare($case, ['status' => 'success']), 'Missing retrieval must fail');
verify(!EvaluationAssertions::compare($case, ['status' => 'success'], true), 'Routing-only mode must not require retrieval');
verify((bool)EvaluationAssertions::compare($case + ['expected_result_count' => 2], $actual), 'Missing rows must fail');
$factual = json_decode(file_get_contents(__DIR__ . '/fixtures/factual_evaluation_cases_v2.json'), true, 512, JSON_THROW_ON_ERROR);
EvaluationAssertions::validateCases($factual);
foreach ($factual as $reference) {
    $response = ['status' => 'success', 'routing' => ['endpoint' => $reference['expected_endpoint'], 'entity_type' => $reference['expected_entity_type'], 'entity' => $reference['expected_entity']],
        'retrieval' => ['status' => 'success', 'entity' => ['name' => $reference['expected_entity']], 'path' => [], 'results' => $reference['expected_results']]];
    verify(!EvaluationAssertions::compare($reference, $response), 'Reference must satisfy assertions');
    $mutated = $response;
    $mutated['retrieval']['results'][0]['edges'][0]['target'] = '#999:999';
    verify((bool)EvaluationAssertions::compare($reference, $mutated), 'Wrong relationship must fail');
    $mutated = $response;
    $mutated['retrieval']['results'][0]['nodes'][] = $reference['expected_results'][0]['nodes'][0];
    verify((bool)EvaluationAssertions::compare($reference, $mutated), 'Extra path node must fail');
    $mutated = $response;
    $mutated['retrieval']['results'][0]['edges'][0]['evidence']['pubmed_id'] = 'invented';
    verify((bool)EvaluationAssertions::compare($reference, $mutated), 'Wrong reference must fail');
    $mutated = $response;
    $mutated['retrieval']['results'][] = $reference['expected_results'][0];
    verify((bool)EvaluationAssertions::compare($reference, $mutated), 'Extra or duplicate path must fail');
    if ($reference['forbidden_results']) {
        $mutated = $response;
        $mutated['retrieval']['results'][0] = $reference['forbidden_results'][0];
        verify((bool)EvaluationAssertions::compare($reference, $mutated), 'Cross-study substitution must fail');
    }
}
$legacy = json_decode(file_get_contents(__DIR__ . '/fixtures/legacy_factual_evaluation_cases_v2.json'), true, 512, JSON_THROW_ON_ERROR);
EvaluationAssertions::validateCases($legacy);
foreach ($legacy as $reference) {
    $response = ['status' => 'success', 'routing' => ['endpoint' => $reference['expected_endpoint'], 'entity_type' => $reference['expected_entity_type'], 'entity' => $reference['expected_entity']],
        'retrieval' => ['status' => 'success', 'entity' => ['name' => $reference['expected_entity']], 'path' => [], 'results' => $reference['expected_results']]];
    verify(!EvaluationAssertions::compare($reference, $response), 'Legacy reference must satisfy assertions');
    $mutated = $response;
    $mutated['retrieval']['results'][0]['properties']['pubmed_id'] = 'invented';
    verify((bool)EvaluationAssertions::compare($reference, $mutated), 'Legacy reference loss must fail');
    $mutated = $response;
    $mutated['retrieval']['results'][0] = $mutated['retrieval']['results'][1];
    verify((bool)EvaluationAssertions::compare($reference, $mutated), 'Replacing a legacy row with a duplicate must fail');
    if ($reference['forbidden_results']) {
        $mutated = $response;
        $mutated['retrieval']['results'][0] = $reference['forbidden_results'][0];
        verify((bool)EvaluationAssertions::compare($reference, $mutated), 'Legacy cross-study substitution must fail');
    }
}
echo "PASS evaluation fixture validation, null semantics, and tuple assertions\n";

$nodeCase=['expected_status'=>'no_data','expected_endpoint'=>'node_property'];
$nodeActual=['status'=>'no_data','routing'=>['endpoint'=>'node_property','entity_id'=>'#1:1','entity_type'=>'Compound','property'=>'description'],
    'node_answer'=>['status'=>'no_data','node_evidence'=>['source'=>'stored_node_properties','node_id'=>'#1:1','node_type'=>'Compound','requested_property'=>'description','properties'=>[]]]];
verify(!EvaluationAssertions::compare($nodeCase,$nodeActual),'Valid node evidence');
$nodeActual['node_answer']['node_evidence']['node_id']='#1:2';
verify((bool)EvaluationAssertions::compare($nodeCase,$nodeActual),'Wrong node evidence fails');
echo "PASS node-property evidence envelope assertions\n";
