<?php
/** Explicit test-user clarification, verified against the immutable raw KG snapshot. */
require_once __DIR__.'/../core/ChatbotOrchestrator.php';
function legacy_test_choose(array $response, string $endpoint): ?array {
    if (($response['status'] ?? '') !== 'needs_clarification' || ($response['reason'] ?? '') !== 'entity_type_ambiguous') return null;
    static $cases = null, $nodes = null;
    if ($cases === null) $cases = json_decode(file_get_contents(__DIR__.'/fixtures/expanded_factual_cases_v4.json'), true, 512, JSON_THROW_ON_ERROR);
    $types = [];
    foreach ($cases as $case) if ($case['expected_endpoint'] === $endpoint) $types[$case['expected_entity_type']] = true;
    if ($endpoint === 'compound_methods') $types = ['Compound'=>true];
    if (!$types) return null;
    if ($nodes === null) {
        $snapshot=__DIR__.'/fixtures/factual_graph_snapshot_expanded.json';
        if (hash_file('sha256',$snapshot) !== $cases[0]['ground_truth_source']['sha256']) throw new RuntimeException('Frozen reference hash mismatch');
        $raw = json_decode(file_get_contents($snapshot), true, 512, JSON_THROW_ON_ERROR);
        $nodes = [];
        foreach ($raw['tables'] as $records) foreach ($records as $record) if (($record['@cat'] ?? '') === 'v') $nodes[$record['@rid']] = $record;
    }
    $options = $response['clarification']['candidates'] ?? [];
    if (count($options) < 2) throw new RuntimeException('Ambiguity requires multiple actual candidates');
    $matching = [];
    foreach ($options as $option) {
        $node = $nodes[$option['id']] ?? [];
        if (($node['@type'] ?? null) !== $option['type'] || ($node['name'] ?? null) !== $option['label']) throw new RuntimeException('Clarification candidate is not the frozen KG entity');
        if (isset($types[$option['type']])) $matching[] = $option;
    }
    if (count($matching) !== 1) throw new RuntimeException('Test requires an explicit unambiguous entity type for '.$endpoint);
    echo 'PASS typed clarification: '.$matching[0]['label'].' ('.$matching[0]['type'].")\n";
    return $matching[0];
}
function legacy_test_route(string $question, ?string $endpoint): array {
    $response = QuestionRouter::route($question);
    $choice = $endpoint ? legacy_test_choose($response, $endpoint) : null;
    return $choice ? QuestionRouter::route($question, [], new ConversationEntities(null, $choice['id'])) : $response;
}
function legacy_test_process(string $question, ?string $endpoint): array {
    $response = ChatbotOrchestrator::process($question);
    $choice = $endpoint ? legacy_test_choose($response, $endpoint) : null;
    return $choice ? ChatbotOrchestrator::process($question, [], $response['conversation_context'], 0, $choice) : $response;
}
function legacy_assert_facts(string $endpoint, string $entity, array $response): void {
    $python = getenv('SHODHANA_TEST_PYTHON') ?: 'python';
    $p = proc_open([$python, '-B', '-X', 'utf8', __DIR__.'/legacy_reference_check.py'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($p)) throw new RuntimeException('Cannot start independent reference check');
    fwrite($pipes[0], json_encode(['endpoint'=>$endpoint,'entity'=>$entity,'response'=>$response], JSON_THROW_ON_ERROR)); fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($p) !== 0) throw new RuntimeException('Independent graph check failed: '.$out.$err);
    echo $out;
}
function legacy_http_request($payload): array {
    require_once __DIR__.'/http_test_helper.php';
    $base=rtrim(getenv('SHODHANA_TEST_API_BASE') ?: 'http://127.0.0.1:8081/api','/');
    $body=is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR);
    $context=stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/json','content'=>$body,'ignore_errors'=>true,'timeout'=>60,'follow_location'=>0]]);
    $response=@file_get_contents($base.'/chatbot_response.php',false,$context);
    return test_http_json_response($response,$http_response_header ?? []);
}
function legacy_http_answer(string $question, ?string $selectionEndpoint = null): array {
    $response=legacy_http_request(['question'=>$question]);
    $choice=$selectionEndpoint ? legacy_test_choose($response['context'] ?? $response, $selectionEndpoint) : null;
    return $choice ? legacy_http_request(['question'=>$question,'conversation_context'=>$response['conversation_context'],'selected_entity'=>$choice]) : $response;
}
