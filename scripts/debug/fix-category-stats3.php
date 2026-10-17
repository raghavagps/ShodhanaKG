<?php
$file = 'api/category-stats.php';
$content = file_get_contents($file);
$content = str_replace(
    '$rows[$rid][$key] = $cnt;',
    "if (!isset(\$rows[\$rid][\$key])) \$rows[\$rid][\$key] = 0; \$rows[\$rid][\$key] += \$cnt;",
    $content
);
file_put_contents($file, $content);
echo "Replaced string directly.\n";
