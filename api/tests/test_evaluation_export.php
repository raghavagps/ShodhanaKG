<?php
require_once __DIR__ . '/EvaluationExport.php';
function checkExport($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$case = ['id' => 'case_1', 'question' => 'Question', 'expected_status' => 'success',
    'expected_endpoint' => null, 'expected_entities' => ['Media A'], 'ground_truth_answer' => 'Reference',
    'history' => [['role' => 'user', 'content' => 'Earlier question']]];
$retrieval = ['status' => 'success', 'results' => [['media' => 'Media A']]];
foreach (['success', 'grounding_error'] as $status) {
    $row = evaluationExportRow($case, 200, json_encode(['status' => $status, 'answer' => 'Final answer',
        'context' => ['status' => 'success', 'routing' => ['endpoint' => 'media_process'], 'retrieval' => $retrieval]]));
    checkExport($row['status'] === $status && $row['orchestrator_status'] === 'success', 'Generation and retrieval status conflated');
    checkExport($row['endpoint'] === 'media_process', 'Wrong nested routing endpoint');
    checkExport($row['retrieval'] === $retrieval && json_decode($row['contexts'][0], true) === $retrieval, 'Raw evidence lost or context polluted');
}
foreach ([[503, false], [200, '{broken'], [200, '{}']] as [$code, $body]) {
    $row = evaluationExportRow($case, $code, $body);
    checkExport($row['status'] === 'export_error' && $row['contexts'] === [], 'Failure fabricated context');
    checkExport($row['ground_truth'] === 'Reference' && $row['expected_entities'] === ['Media A'] && $row['history'] === $case['history'], 'Failure lost annotations');
    checkExport(array_key_exists('expected_endpoint', $row) && $row['expected_endpoint'] === null, 'Explicit null lost');
}
unset($case['expected_endpoint']);
checkExport(!array_key_exists('expected_endpoint', evaluationExportRow($case, 500, false)), 'Missing expectation fabricated');
$reference = json_decode(file_get_contents(__DIR__ . '/fixtures/factual_evaluation_cases.json'), true)[0];
$row = evaluationExportRow($reference, 503, false);
foreach (['expected_results', 'forbidden_results', 'expected_result_count', 'ground_truth_source', 'expected_entity'] as $key) {
    checkExport($row[$key] === $reference[$key], 'Factual reference lost on export failure: ' . $key);
}
echo "PASS evaluation export: success, grounding error, HTTP/malformed failures, annotation preservation\n";
