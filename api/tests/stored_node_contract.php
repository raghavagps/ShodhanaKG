<?php
/** Compare public node properties to frozen independent records, never generated prose. */
function assert_stored_node_contract(array $response): void {
    $e = $response['node_evidence'] ?? [];
    $snapshot = json_decode(file_get_contents(__DIR__.'/fixtures/factual_graph_snapshot_expanded.json'), true);
    $node = null;
    foreach ($snapshot['tables'] as $rows) foreach ($rows as $row) if (($row['@rid'] ?? null) === ($e['node_id'] ?? null)) $node = $row;
    if (!$node || ($e['source'] ?? '') !== 'stored_node_properties' || ($e['node_type'] ?? '') !== $node['@type']) throw new RuntimeException('Wrong stored-node identity/source');
    $normalize = static function ($s) { return strtolower(trim(preg_replace('/[_\s]+/', ' ', $s))); };
    $requested = $e['requested_property'] ?? null;
    if ($requested === null) throw new RuntimeException('Missing property request');
    $expected = [];
    foreach ($node as $key => $value) {
        if (preg_match('/^[@_]|^(in|out)(_|$)/i', $key) || $value === null || $value === [] || (is_string($value) && trim($value) === '')) continue;
        if ($requested === '*' || $normalize($key) === $normalize($requested) || ($requested === 'description' && preg_match('/(^|[_. ])description([. ]\d+)?$/i', $key))) $expected[$key] = $value;
    }
    $actual = $e['properties'] ?? null;
    if (!is_array($actual)) throw new RuntimeException('Missing stored properties');
    ksort($actual); ksort($expected);
    if ($actual !== $expected || $response['status'] !== ($expected ? 'success' : 'no_data')) throw new RuntimeException('Stored node fields differ from independent snapshot');
    if (!empty($response['citations']) || !empty($response['evidence_records']) || !empty($response['answer_results'])) throw new RuntimeException('Node property invented edge evidence');
}
