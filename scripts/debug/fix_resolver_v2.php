<?php
$file = 'api/core/EntityResolver.php';
$content = file_get_contents($file);

$bad1 = <<<EOD
        // Build nested coalesce: coalesce(f1, coalesce(f2, ''))
        \$coalesceExpr = "''";
        foreach (array_reverse(\$fields) as \$field) {
            \$coalesceExpr = "coalesce({\$field}, {\$coalesceExpr})";
        }
        
        \$sql = "SELECT FROM `{\$type}` WHERE {\$coalesceExpr}.toLowerCase() = :name LIMIT 1";
EOD;

$good1 = <<<EOD
        \$orConditions = [];
        foreach (\$fields as \$field) {
            \$orConditions[] = "{\$field}.toLowerCase() = :name";
        }
        \$whereClause = implode(" OR ", \$orConditions);
        
        \$sql = "SELECT FROM `{\$type}` WHERE {\$whereClause} LIMIT 1";
EOD;

$content = str_replace($bad1, $good1, $content);

$bad2 = <<<EOD
            // Fallback to Levenshtein distance on full list
            \$coalesceExpr = "''";
            foreach (array_reverse(\$fields) as \$field) {
                \$coalesceExpr = "coalesce({\$field}, {\$coalesceExpr})";
            }
            
            \$fallbackSql = "SELECT @rid, {\$coalesceExpr} as name, @type FROM `{\$type}`";
EOD;

$good2 = <<<EOD
            // Fallback to Levenshtein distance on full list
            \$coalesceExpr = "''";
            foreach (array_reverse(\$fields) as \$field) {
                \$coalesceExpr = "coalesce({\$field}, {\$coalesceExpr})";
            }
            // For fuzzy, we still need a string to compare against, so coalesce is acceptable for extracting A name
            \$fallbackSql = "SELECT @rid, {\$coalesceExpr} as name, @type FROM `{\$type}`";
EOD;
// wait actually, for fuzzy match, shouldn't we check ALL fields? Yes. 
// let's do this: fetch all label fields in fallbackSql
$good2 = <<<EOD
            // Fallback to Levenshtein distance on full list
            \$fieldNames = implode(", ", \$fields);
            \$fallbackSql = "SELECT @rid, {\$fieldNames}, @type FROM `{\$type}`";
EOD;

$content = str_replace($bad2, $good2, $content);

// In fuzzy match, we need to change how it compares
$bad3 = <<<EOD
                \$candidateName = isset(\$item['name']) ? \$item['name'] : '';
                \$dist = levenshtein(strtolower(\$name), strtolower(\$candidateName));
EOD;

$good3 = <<<EOD
                \$bestDist = PHP_INT_MAX;
                foreach (\$fields as \$field) {
                    if (isset(\$item[\$field]) && is_string(\$item[\$field])) {
                        \$dist = levenshtein(strtolower(\$name), strtolower(\$item[\$field]));
                        if (\$dist < \$bestDist) \$bestDist = \$dist;
                    }
                }
                \$dist = \$bestDist;
EOD;
$content = str_replace($bad3, $good3, $content);

file_put_contents($file, $content);
echo "Fixed EntityResolver completely\n";
