<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$sql2 = "SELECT FROM TREATED_BY WHERE @out = '#45:94'";
$res2 = arcade_command($sql2, 'sql');
print_r($res2['result']);
