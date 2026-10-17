<?php
require 'api/core/GraphRetriever.php';
$sql = "SELECT @rid as edge_rid, @out as target_rid FROM `ASSESS` WHERE @in = '#45:102'"; // I don't know its RID
// Let's just find Biochemical Parameters
$res = GraphRetriever::execute("SELECT * FROM Assessment_Parameter WHERE name='Biochemical Parameters'", []);
if ($res) {
    $rid = $res[0]['@rid'];
    echo "RID: $rid\n";
    $edges = GraphRetriever::execute("SELECT * FROM `ASSESS` WHERE @in = '$rid' OR @out = '$rid'", []);
    print_r($edges);
}
