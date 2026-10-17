<?php
$file = 'api/core/QuestionRouter.php';
$content = file_get_contents($file);
$content = str_replace(
    'public static function route($question) {',
    'public static function route($question, $history = []) {',
    $content
);
file_put_contents($file, $content);
echo "Updated route signature\n";
