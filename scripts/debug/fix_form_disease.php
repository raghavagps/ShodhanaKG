<?php
$file = 'api/formulation_disease.php';
$content = file_get_contents($file);
$content = str_replace("FROM `USES`", "FROM `USES_FORMULATION`", $content);
file_put_contents($file, $content);
echo "Fixed formulation_disease.php\n";
