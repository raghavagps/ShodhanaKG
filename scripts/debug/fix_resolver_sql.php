<?php
$file = 'api/core/EntityResolver.php';
$content = file_get_contents($file);

$badQueryBuilder = <<<EOD
        // Build nested coalesce: coalesce(f1, coalesce(f2, ''))
        \$coalesceExpr = "''";
        foreach (array_reverse(\$fields) as \$field) {
            \$coalesceExpr = "coalesce({\$field}, {\$coalesceExpr})";
        }
        
        \$sql = "SELECT FROM `{\$type}` WHERE {\$coalesceExpr}.toLowerCase() = :name LIMIT 1";
EOD;

$goodQueryBuilder = <<<EOD
        \$orConditions = [];
        foreach (\$fields as \$field) {
            \$orConditions[] = "{\$field}.toLowerCase() = :name";
        }
        \$whereClause = implode(" OR ", \$orConditions);
        
        \$sql = "SELECT FROM `{\$type}` WHERE {\$whereClause} LIMIT 1";
EOD;

$content = str_replace($badQueryBuilder, $goodQueryBuilder, $content);
file_put_contents($file, $content);
echo "Fixed EntityResolver SQL\n";
