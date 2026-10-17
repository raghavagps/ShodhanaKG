<?php
$file = 'api/core/QuestionRouter.php';
$content = file_get_contents($file);
$content = str_replace("preg_match('/\b(symptom|sign|manifest)/i', \$q)", "preg_match('/\b(symptom|sign|manifest|lakshan)/i', \$q)", $content);
file_put_contents($file, $content);
echo "Fixed Hindi regex for symptoms\n";
