<?php
$file = 'api/core/QuestionRouter.php';
$content = file_get_contents($file);
$content = str_replace("'symptom', 'Symptom',", "'symptom', 'symptoms', 'Symptom',", $content);
$content = str_replace("\$cleanQ = preg_replace('/[^\w\s\.]/', '', \$originalQ);", "\$cleanQ = preg_replace('/[^\w\s]/', '', \$originalQ);", $content);
file_put_contents($file, $content);
echo "Fixed stop words and punctuation\n";
