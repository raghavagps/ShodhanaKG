<?php
/** Read-only full reference capture, independent of all retrieval endpoints. */
require_once __DIR__ . '/../db.php';
if ($argc !== 2 || file_exists($argv[1])) { fwrite(STDERR, "Supply a new output path\n"); exit(2); }
$tables = ['Therapy','Media','Shodhana_Method','Process','Purified_Compound','Toxic_Substance',
    'Compound','Disease','Formulation','Symptom','Dosha','Ayurvedic_Diagnosis','Assessment_Parameter',
    'Confirmation_Study','Indication','Ayurvedic_Formulation','Shodhana_Type',
    'USES_FORMULATION','USES_MEDIA','FOLLOWED_BY','PRODUCES','DETOXIFIES','UNDERGOES',
    'TREATED_BY','PRE_TREATED_BY','POST_TREATED_BY','CONTAINS','VALIDATED_BY','CONFIRMS_SAFETY_OF',
    'TREATS','USED_FOR_TREATMENT','DERIEVED_FROM','USED_IN_FORMULATION','HAS_SYMPTOM',
    'HAS_DOSHA_IMBALANCE','HAS_AYURVEDIC_DIAGNOSIS','ASSESS','CLASSIFIED_AS'];
$snapshot = ['captured_at' => gmdate('c'), 'database' => $ARCADE_DB,
    'scope' => 'Raw records for independent endpoint evaluation; scientific review pending', 'tables' => []];
foreach ($tables as $table) {
    $before = arcade_command('SELECT count(*) as total FROM `' . $table . '`')['result'][0]['total'] ?? null;
    $rows = arcade_command('SELECT FROM `' . $table . '`')['result'] ?? null;
    $after = arcade_command('SELECT count(*) as total FROM `' . $table . '`')['result'][0]['total'] ?? null;
    if (!is_array($rows) || $before === null || $after !== $before || count($rows) !== (int)$before) throw new RuntimeException('Truncated/changing table: ' . $table);
    $snapshot['tables'][$table] = $rows;
}
file_put_contents($argv[1], json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
echo 'Captured ' . count($tables) . " tables; existing references untouched\n";
