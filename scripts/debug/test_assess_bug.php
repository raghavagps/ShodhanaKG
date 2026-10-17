<?php
require 'api/core/GraphRetriever.php';
$res = GraphRetriever::execute("SELECT * FROM Assessment_Parameter WHERE name='Bates-Jensen Wound Assessment Tool (Bwat)'", []);
$rid = $res[0]['@rid'];
echo "RID: $rid\n";
// execute_disease_assessment runs:
$sql = "SELECT @rid as edge_rid, @out as target_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `ASSESS` WHERE @in = :disease_rid";
$debug = [];
$edges = GraphRetriever::execute($sql, ['disease_rid' => $rid], $debug);
print_r($edges);
