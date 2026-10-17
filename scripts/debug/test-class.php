<?php
require 'api/core/GraphRetriever.php';
$res1 = GraphRetriever::execute("SELECT count(*) as cnt FROM PRODUCES WHERE @out.@class = 'Process'", []);
print_r($res1);
