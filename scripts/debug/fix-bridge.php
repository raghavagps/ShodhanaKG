<?php
$file = 'api/bridge-stats.php';
$content = file_get_contents($file);
$content = str_replace(
    'SELECT @out AS com_rid, @in AS pc_rid FROM DERIEVED_FROM WHERE @in IN',
    'SELECT @in AS com_rid, @out AS pc_rid FROM DERIEVED_FROM WHERE @out IN',
    $content
);
file_put_contents($file, $content);
echo "Fixed bridge-stats.php direction!\n";
