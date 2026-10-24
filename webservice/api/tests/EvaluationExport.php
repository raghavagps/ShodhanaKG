<?php
/** Pure export formatting; failures retain the same annotations as successful rows. */
function evaluationExportRow(array $case, int $httpCode, $response): array {
    $result = is_string($response) ? json_decode($response, true) : null;
    if ($httpCode !== 200 || !is_array($result) || !is_string($result['status'] ?? null)) {
        $result = ['status' => 'export_error', 'message' => $httpCode === 200
            ? 'Malformed chatbot response' : "Error generating answer. HTTP: $httpCode"];
    }
    $retrieval = $result['context']['retrieval'] ?? null;
    $row = [
        'id' => $case['id'],
        'expected_status' => $case['expected_status'],
        'expected_entities' => $case['expected_entities'] ?? [],
        'question' => $case['question'],
        'history' => $case['history'] ?? [],
        'contexts' => $retrieval === null ? [] : [json_encode($retrieval, JSON_UNESCAPED_UNICODE)],
        'answer' => $result['answer'] ?? $result['message'] ?? '',
        'ground_truth' => $case['ground_truth_answer'] ?? '',
        'status' => $result['status'],
        'endpoint' => $result['context']['routing']['endpoint'] ?? null,
        'retrieval' => $retrieval,
        'orchestrator_status' => $result['context']['status'] ?? null,
    ];
    foreach (['expected_endpoint', 'expected_final_status', 'expected_entity', 'expected_results', 'forbidden_results', 'expected_result_count', 'ground_truth_source'] as $key) {
        if (array_key_exists($key, $case)) $row[$key] = $case[$key];
    }
    return $row;
}
