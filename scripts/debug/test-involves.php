<?php
require 'api/core/GraphRetriever.php';
$res = GraphRetriever::execute("SELECT count(*) as cnt FROM INVOLVES_PROCESS", []);
print_r($res);
