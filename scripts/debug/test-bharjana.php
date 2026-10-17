<?php
require 'api/core/GraphRetriever.php';
$res = GraphRetriever::execute("SELECT count(*) as cnt FROM FOLLOWED_BY WHERE @out IN (SELECT @rid FROM Shodhana_Method WHERE name = 'Bharjana')", []);
print_r($res);
