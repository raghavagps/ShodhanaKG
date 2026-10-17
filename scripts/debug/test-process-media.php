<?php
require 'api/core/GraphRetriever.php';
$res = GraphRetriever::execute("SELECT count(*) as cnt FROM USES_MEDIA WHERE @out.@type = 'Process'", []);
print_r($res);
