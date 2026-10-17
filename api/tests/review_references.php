<?php
/** Read a raw KG snapshot; write a review queue without modifying the KG/catalog. */
require_once __DIR__ . '/../core/ReferenceClassifier.php';
if ($argc !== 3 || file_exists($argv[2])) { fwrite(STDERR, "Usage: php review_references.php RAW_SNAPSHOT NEW_REPORT\n"); exit(2); }
$snapshot = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
if (!isset($snapshot['tables'])) throw new RuntimeException('Expected raw snapshot tables');
$seen = []; $queue = []; $counts = [];
foreach ($snapshot['tables'] as $rows) foreach ($rows as $edge) {
    if (($edge['@cat'] ?? null) !== 'e' || isset($seen[$edge['@rid']])) continue;
    $seen[$edge['@rid']] = true;
    $references = ReferenceClassifier::resolve($edge);
    if (!$references) $queue[] = ['edge_id' => $edge['@rid'], 'reason' => 'missing_reference', 'edge' => $edge];
    foreach ($references as $ref) {
        $counts[$ref['status']] = ($counts[$ref['status']] ?? 0) + 1;
        if ($ref['status'] !== 'verified') $queue[] = ['edge_id' => $edge['@rid'], 'reason' => $ref['status'], 'reference' => $ref, 'edge' => $edge];
    }
}
$report = ['created_at' => gmdate('c'), 'catalog_version' => ReferenceClassifier::catalog()['version'],
    'snapshot_sha256' => hash_file('sha256', $argv[1]), 'edges_scanned' => count($seen), 'counts' => $counts, 'review_queue' => $queue];
$out = fopen($argv[2], 'x');
if (!$out) throw new RuntimeException('Report exists');
fwrite($out, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); fclose($out);
echo json_encode(['edges_scanned' => count($seen), 'review_items' => count($queue)]) . "\n";
