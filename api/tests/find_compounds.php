<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
$sql = "SELECT * FROM Compound LIMIT 1";
$res = arcade_command($sql, 'sql');
print_r($res);
