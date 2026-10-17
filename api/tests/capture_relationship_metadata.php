<?php
/** Read-only type-pair metadata for presentation; no entities or answer facts. */
require_once __DIR__ . '/../graph_service.php';
$target = $argv[1] ?? null;
if (!$target || file_exists($target)) { fwrite(STDERR, "Supply a new metadata output path\n"); exit(2); }
$types = [];
$unavailable = [];
foreach (edge_types_for_domain('integrated') as $edge) {
    try {
    $rows = arcade_command('SELECT @out.@type as sourceType, @in.@type as targetType FROM `' . $edge . '`', 'sql');
    if (isset($rows['error'])) throw new RuntimeException('Cannot inspect ' . $edge);
    $pairs = [];
    foreach ($rows['result'] ?? [] as $r) if (!empty($r['sourceType']) && !empty($r['targetType'])) {
        $pairs[$r['sourceType'] . ':' . $r['targetType']] = ['source' => $r['sourceType'], 'target' => $r['targetType']];
    }
    $types[$edge] = array_values($pairs);
    } catch (Throwable $e) { $unavailable[] = $edge; }
}
file_put_contents($target, json_encode(['captured_at' => gmdate('c'), 'basis' => 'Observed directed type pairs; presentation metadata only', 'unavailable' => $unavailable, 'edges' => $types], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo 'Captured ' . count($types) . " edge types\n";
