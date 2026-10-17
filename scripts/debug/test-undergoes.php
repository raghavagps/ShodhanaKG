<?php
require 'api/core/GraphRetriever.php';
$res = GraphRetriever::execute("SELECT @out.@type AS out_type, @in.@type AS in_type FROM UNDERGOES LIMIT 5", []);
print_r($res);
