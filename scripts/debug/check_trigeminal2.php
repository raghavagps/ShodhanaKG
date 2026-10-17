<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$diseaseName = "Trigeminal Neuralgia";
$sql = "SELECT FROM Disease WHERE name = 'Trigeminal Neuralgia'";
$res = arcade_command($sql, 'sql');
$disease = $res['result'][0];
$rid = $disease['@rid'];

$sql2 = "SELECT @rid, @in.name as therapy_name, pubmedId, doi, ayush_id FROM TREATED_BY WHERE @out = '$rid'";
$res2 = arcade_command($sql2, 'sql');
foreach ($res2['result'] as $edge) {
    echo "TREATED_BY: " . $edge['therapy_name'] . " | PMID: " . $edge['pubmedId'] . " | DOI: " . $edge['doi'] . " | AyushID: " . $edge['ayush_id'] . "\n";
}

$sql4 = "SELECT @rid, @in.name as therapy_name, pubmedId, doi, ayush_id FROM PRE_TREATED_BY WHERE @out = '$rid'";
$res4 = arcade_command($sql4, 'sql');
foreach ($res4['result'] as $edge) {
    echo "PRE_TREATED_BY: " . $edge['therapy_name'] . " | PMID: " . $edge['pubmedId'] . " | DOI: " . $edge['doi'] . " | AyushID: " . $edge['ayush_id'] . "\n";
}
