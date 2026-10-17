<?php
require 'api/core/GraphRetriever.php';
$res = GraphRetriever::execute("SELECT DISTINCT(@out.@type) AS out_type FROM PRODUCES", []);
print_r($res);
