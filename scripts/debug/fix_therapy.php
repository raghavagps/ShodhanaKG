<?php
$file = 'api/therapy_disease.php';
$content = file_get_contents($file);

$badQuery = <<<EOD
        \$sql = "SELECT @rid as edge_rid, @out as target_rid, @class as edge_class, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM E WHERE @in = :therapy_rid AND (@class = 'TREATED_BY' OR @class = 'PRE_TREATED_BY' OR @class = 'POST_TREATED_BY')";
        \$edges = GraphRetriever::execute(\$sql, ['therapy_rid' => \$therapyRid], \$debug);
EOD;

$goodQuery = <<<EOD
        \$edges = [];
        foreach (['TREATED_BY', 'PRE_TREATED_BY', 'POST_TREATED_BY'] as \$edgeClass) {
            \$sql = "SELECT @rid as edge_rid, @out as target_rid, '\$edgeClass' as edge_class, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `\$edgeClass` WHERE @in = :therapy_rid";
            \$classEdges = GraphRetriever::execute(\$sql, ['therapy_rid' => \$therapyRid], \$debug);
            if (is_array(\$classEdges)) {
                \$edges = array_merge(\$edges, \$classEdges);
            }
        }
EOD;

$content = str_replace($badQuery, $goodQuery, $content);
file_put_contents($file, $content);
echo "Fixed therapy_disease.php\n";
