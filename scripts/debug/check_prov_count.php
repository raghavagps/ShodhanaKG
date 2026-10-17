<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
$ridsList = "#69:52,#69:82,#69:17,#69:27,#69:47,#69:86,#69:94,#69:98";
$sql = sprintf("SELECT COUNT(*) FROM USES_FORMULATION WHERE @out IN [%s]", $ridsList);
$res = arcade_command($sql, 'sql');
print_r($res);
