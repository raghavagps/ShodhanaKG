<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
$ridsList = "#69:52,#69:82,#69:17,#69:27,#69:47,#69:86,#69:94,#69:98";
$sql = sprintf("SELECT @rid, name FROM Therapy WHERE @rid IN [%s]", $ridsList);
$res = arcade_command($sql, 'sql');
foreach ($res['result'] as $row) {
    echo $row['@rid'] . " -> " . $row['name'] . "\n";
}
