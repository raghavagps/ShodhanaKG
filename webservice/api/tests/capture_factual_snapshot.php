<?php
/** Explicit read-only database capture. Never calls the router, retriever, or endpoint functions. */
require_once __DIR__ . '/../db.php';
if ($argc !== 2) { fwrite(STDERR, "Usage: php capture_factual_snapshot.php OUTPUT.json\n"); exit(2); }
if (file_exists($argv[1])) { fwrite(STDERR, "Refusing to overwrite a reference snapshot; use a new filename.\n"); exit(2); }
$tables = ['Therapy', 'Media', 'Shodhana_Method', 'Process', 'Purified_Compound', 'Toxic_Substance',
    'Compound', 'Disease', 'Formulation', 'USES_FORMULATION', 'USES_MEDIA', 'FOLLOWED_BY',
    'PRODUCES', 'DETOXIFIES', 'UNDERGOES', 'TREATED_BY', 'PRE_TREATED_BY', 'POST_TREATED_BY'];
$snapshot = ['captured_at' => gmdate('c'), 'database' => $ARCADE_DB,
    'scope' => 'Raw graph records; graph fidelity reference, not expert-reviewed scientific truth', 'tables' => []];
foreach ($tables as $table) {
    $result = arcade_command('SELECT FROM `' . $table . '`');
    if (!isset($result['result']) || !is_array($result['result']) || !$result['result']) throw new RuntimeException('Missing table: ' . $table);
    $total = arcade_command('SELECT count(*) as total FROM `' . $table . '`')['result'][0]['total'] ?? null;
    if ($total === null || count($result['result']) !== (int)$total) throw new RuntimeException('Truncated or changing table: ' . $table);
    $snapshot['tables'][$table] = $result['result'];
}
if (file_put_contents($argv[1], json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) exit(1);
echo "Captured " . count($tables) . " raw tables\n";
