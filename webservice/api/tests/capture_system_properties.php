<?php
/** Read-only presentation capture; expectations live in independent Python tests. */
require_once __DIR__ . '/../core/ResultsPresentation.php';
require_once __DIR__ . '/../graph_service.php';
if ($argc !== 4) exit(2);
$snapshot = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$facts = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
$report = ['edges' => [], 'answers' => [], 'synthetic' => []];
foreach ($snapshot['tables'] as $rows) foreach ($rows as $row) if (isset($row['@out'])) {
    $report['edges'][$row['@rid']] = map_edge_props($row);
}
foreach ($facts['cases'] as $case) if (($case['actual']['status'] ?? '') === 'success') {
    $report['answers'][$case['id']] = ResultsPresentation::build($case['actual']);
}
foreach (['TREATED_BY', 'PRE_TREATED_BY', 'POST_TREATED_BY', 'USES_FORMULATION'] as $type) {
    $report['synthetic'][$type] = map_edge_props(['@rid' => '#999:1', '@type' => $type, '@out' => '#1:1', '@in' => '#2:1',
        'phase' => 'MISLEADING', 'name' => 'post treatment', 'Dose' => '5 g', 'Route' => 'oral', 'Frequency' => 'twice daily',
        'Future_Property' => ['nested' => 0], 'Zero' => 0, 'False' => false, 'Empty' => '', 'Missing' => null, 'Placeholder' => 'Not supplied']);
}
file_put_contents($argv[3], json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo count($report['edges']) . " edges captured\n";
