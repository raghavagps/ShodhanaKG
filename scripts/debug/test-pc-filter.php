<?php
require 'api/core/GraphRetriever.php';
$res1 = GraphRetriever::execute("SELECT count(*) as cnt FROM PRODUCES WHERE @in = '#62:30' AND @out.@type = 'Process'", []);
echo "Process -> Abhraka Bhasma: " . $res1[0]['cnt'] . "\n";
$res2 = GraphRetriever::execute("SELECT count(*) as cnt FROM PRODUCES WHERE @in = '#62:30' AND @out.@type = 'Shodhana_Method'", []);
echo "Shodhana_Method -> Abhraka Bhasma: " . $res2[0]['cnt'] . "\n";
