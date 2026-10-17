<?php
$file = 'api/core/QuestionRouter.php';
$content = file_get_contents($file);
$content = str_replace(
    '$resolvedEntity = null;',
    'print_r($candidates); $resolvedEntity = null;',
    $content
);
file_put_contents($file, $content);
