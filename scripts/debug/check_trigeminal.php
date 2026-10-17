<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$diseaseName = "Trigeminal Neuralgia";
$sql = "SELECT FROM Disease WHERE name = 'Trigeminal Neuralgia'";
$res = arcade_command($sql, 'sql');
$disease = $res['result'][0];
$rid = $disease['@rid'];
echo "Disease: " . $rid . "\n";

$sql2 = "SELECT @rid, @in.name as therapy_name, @in.@rid as therapy_rid, pubmedId, doi, ayush_id FROM TREATED_BY WHERE @out = '$rid'";
$res2 = arcade_command($sql2, 'sql');
foreach ($res2['result'] as $edge) {
    echo "TREATED_BY Therapy: " . $edge['therapy_name'] . " (RID: " . $edge['therapy_rid'] . "), PMID: " . $edge['pubmedId'] . ", DOI: " . $edge['doi'] . ", AyushID: " . $edge['ayush_id'] . "\n";
    
    $sql3 = "SELECT @rid, @in.name as formulation_name, pubmedId, doi, ayush_id FROM USES_FORMULATION WHERE @out = '{['therapy_rid']}'";
    $res3 = arcade_command($sql3, 'sql');
    foreach ($res3['result'] as $edge3) {
        echo "  -> Formulation: " . $edge3['formulation_name'] . ", PMID: " . $edge3['pubmedId'] . ", DOI: " . $edge3['doi'] . ", AyushID: " . $edge3['ayush_id'] . "\n";
    }
}

$sql4 = "SELECT @rid, @in.name as therapy_name, @in.@rid as therapy_rid, pubmedId, doi, ayush_id FROM PRE_TREATED_BY WHERE @out = '$rid'";
$res4 = arcade_command($sql4, 'sql');
foreach ($res4['result'] as $edge) {
    echo "PRE_TREATED_BY Therapy: " . $edge['therapy_name'] . " (RID: " . $edge['therapy_rid'] . "), PMID: " . $edge['pubmedId'] . ", DOI: " . $edge['doi'] . ", AyushID: " . $edge['ayush_id'] . "\n";
    
    $sql3 = "SELECT @rid, @in.name as formulation_name, pubmedId, doi, ayush_id FROM USES_FORMULATION WHERE @out = '{['therapy_rid']}'";
    $res3 = arcade_command($sql3, 'sql');
    foreach ($res3['result'] as $edge3) {
        echo "  -> Formulation: " . $edge3['formulation_name'] . ", PMID: " . $edge3['pubmedId'] . ", DOI: " . $edge3['doi'] . ", AyushID: " . $edge3['ayush_id'] . "\n";
    }
}
