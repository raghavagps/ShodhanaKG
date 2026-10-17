<?php
require 'api/core/GraphRetriever.php';
$res = GraphRetriever::execute("SELECT @out.@type AS out_type, @in.@type AS in_type FROM USES_MEDIA LIMIT 5", []);
print_r($res);
