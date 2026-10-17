<?php
require 'api/core/GraphRetriever.php';
$res = GraphRetriever::execute("SELECT @rid, name FROM Therapy WHERE name = 'Swedana'", []);
if ($res) {
    $rid = $res[0]['@rid'];
    echo "Swedana RID: $rid\n";
    
    $res1 = GraphRetriever::execute("SELECT count(*) as cnt FROM TREATED_BY WHERE @in = '$rid'", []);
    echo "TREATED_BY: " . $res1[0]['cnt'] . "\n";
    
    $res2 = GraphRetriever::execute("SELECT count(*) as cnt FROM PRE_TREATED_BY WHERE @in = '$rid'", []);
    echo "PRE_TREATED_BY: " . $res2[0]['cnt'] . "\n";
    
    $res3 = GraphRetriever::execute("SELECT count(*) as cnt FROM POST_TREATED_BY WHERE @in = '$rid'", []);
    echo "POST_TREATED_BY: " . $res3[0]['cnt'] . "\n";
}
