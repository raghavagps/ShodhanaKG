<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/EvidenceCorrelator.php';

$diseaseId = "45:94"; // Trigeminal Neuralgia
$sql = "SELECT FROM Disease WHERE @rid = '#45:94'";
$res = arcade_command($sql, 'sql');
$diseaseVertex = $res['result'][0];
$diseaseRid = '#45:94';

$diseaseToTherapyEdges = [];
$therapyRids = [];
$edgeTypes1 = ['PRE_TREATED_BY', 'POST_TREATED_BY', 'TREATED_BY'];

foreach ($edgeTypes1 as $edgeType) {
    $sql = sprintf("SELECT FROM %s WHERE @out = %s LIMIT 150", $edgeType, $diseaseRid);
    $res = arcade_command($sql, 'sql');
    foreach ($res['result'] as $edge) {
        $therapyRid = isset($edge['@in']) ? $edge['@in'] : '';
        $props = EvidenceCorrelator::extractIdentifiers($edge);
        
        if ($therapyRid && ($props['pubmed_id'] !== '' || $props['doi'] !== '' || $props['ayush_id'] !== '')) {
            $diseaseToTherapyEdges[] = array(
                'edge' => $edge,
                'therapyRid' => $therapyRid,
                'props' => $props,
                'edgeType' => $edgeType,
            );
            $therapyRids[$therapyRid] = true;
        }
    }
}

echo "Found " . count($diseaseToTherapyEdges) . " valid Disease->Therapy edges\n";
print_r(array_keys($therapyRids));
