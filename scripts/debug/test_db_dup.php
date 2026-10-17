<?php
require 'api/core/GraphRetriever.php';
$sql = "SELECT * FROM Disease WHERE name.toLowerCase() LIKE '%tubal bloackage%' OR name.toLowerCase() LIKE '%tubal blockage%'";
$res = GraphRetriever::execute($sql, []);
print_r($res);
