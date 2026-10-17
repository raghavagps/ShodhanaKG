<?php
// Read-only enumeration of every schema type; never changes KG data or fixtures.
require __DIR__ . '/../../../../api/db.php';
require __DIR__ . '/../../../../api/core/EvidenceCorrelator.php';
$schema = arcade_command('SELECT name, type FROM schema:types')['result'];
$records = []; $counts = [];
foreach ($schema as $type) {
    $name = str_replace('`', '', $type['name']);
    $before = arcade_command('SELECT count(*) AS n FROM `' . $name . '`')['result'][0]['n'];
    $rows = arcade_command('SELECT FROM `' . $name . '`')['result'];
    $after = arcade_command('SELECT count(*) AS n FROM `' . $name . '`')['result'][0]['n'];
    if (count($rows) !== $before || $before !== $after) throw new Exception('Changing/truncated table: ' . $name);
    $counts[$name] = count($rows);
    foreach ($rows as $row) $records[$row['@rid']] = $row;
}
$refs = []; $missing = []; $vertexRefs = [];
foreach ($records as $rid => $row) {
    if ($row['@cat'] === 'e' && (!array_key_exists('ref', $row) || trim((string)$row['ref']) === '')) $missing[] = $rid;
    if (!array_key_exists('ref', $row)) continue;
    $raw = $row['ref']; $key = (string)$raw;
    if (!isset($refs[$key])) $refs[$key] = ['raw' => $raw, 'ids' => [], 'current_identifiers' => EvidenceCorrelator::extractIdentifiers($row), 'contexts' => []];
    $refs[$key]['ids'][] = $rid;
    if ($row['@cat'] === 'v') $vertexRefs[] = $rid;
    $out = $records[$row['@out'] ?? ''] ?? []; $in = $records[$row['@in'] ?? ''] ?? [];
    $refs[$key]['contexts'][] = ['id' => $rid, 'type' => $row['@type'], 'source' => $out['name'] ?? null, 'target' => $in['name'] ?? null];
}
$report = ['captured_at' => gmdate('c'), 'schema' => $schema, 'type_counts' => $counts,
    'unique_records' => count($records), 'unique_edges' => count(array_filter($records, fn($r) => $r['@cat'] === 'e')),
    'missing_edge_refs' => $missing, 'vertex_ref_ids' => $vertexRefs, 'references' => array_values($refs),
    'classifier_sha256' => hash_file('sha256', __DIR__ . '/../../../../api/core/EvidenceCorrelator.php')];
$f = fopen(__DIR__ . '/stored_references.json', 'x'); if (!$f) exit(2);
fwrite($f, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); fclose($f);
echo json_encode(['distinct_references' => count($refs), 'edges' => $report['unique_edges'], 'missing' => count($missing)]);
