<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/EvidenceCorrelator.php';

$diseaseRid = '#45:94';
$diseaseToTherapyEdges = [];
$therapyRids = [];
$edgeTypes1 = ['PRE_TREATED_BY', 'POST_TREATED_BY', 'TREATED_BY'];
foreach ($edgeTypes1 as $edgeType) {
    $sql = sprintf("SELECT FROM %s WHERE @out = '%s' LIMIT 1000", $edgeType, $diseaseRid);
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

$therapyToFormulationEdges = [];
$formulationRids = [];
if (!empty($therapyRids)) {
    $ridsList = implode(',', array_keys($therapyRids));
    $sql = sprintf("SELECT FROM USES_FORMULATION WHERE @out IN [%s] LIMIT 5000", $ridsList);
    $res = arcade_command($sql, 'sql');
    foreach ($res['result'] as $edge) {
        $therapyRid = isset($edge['@out']) ? $edge['@out'] : '';
        $formRid = isset($edge['@in']) ? $edge['@in'] : '';
        $props = EvidenceCorrelator::extractIdentifiers($edge);
        
        if ($therapyRid && $formRid && ($props['pubmed_id'] !== '' || $props['doi'] !== '' || $props['ayush_id'] !== '')) {
            if (!isset($therapyToFormulationEdges[$therapyRid])) {
                $therapyToFormulationEdges[$therapyRid] = [];
            }
            $therapyToFormulationEdges[$therapyRid][] = array(
                'edge' => $edge,
                'formRid' => $formRid,
                'props' => $props,
            );
            $formulationRids[$formRid] = true;
        }
    }
}

$finalTherapies = [];
foreach ($diseaseToTherapyEdges as $dtEdge) {
    $therapyRid = $dtEdge['therapyRid'];
    $props1 = $dtEdge['props'];
    if (!isset($therapyToFormulationEdges[$therapyRid])) continue;
    foreach ($therapyToFormulationEdges[$therapyRid] as $tfEdge) {
        $props2 = $tfEdge['props'];
        if (EvidenceCorrelator::correlate($props1, $props2)) {
            $finalTherapies[$therapyRid] = true;
        }
    }
}
print_r(array_keys($finalTherapies));
