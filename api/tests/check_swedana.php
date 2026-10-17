<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$sql = "SELECT FROM USES_FORMULATION WHERE @out = '#69:94'";
$res = arcade_command($sql, 'sql');
print_r($res['result']);
