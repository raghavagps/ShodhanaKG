<?php
$file = 'api/core/ResponseGenerator.php';
$content = file_get_contents($file);
$content = preg_replace('/\$historyStr = \'\';.*?\$prompt = self::buildSystemPrompt\(\) \. \$historyStr \./s', "\$prompt = self::buildSystemPrompt() .", $content);
file_put_contents($file, $content);
echo "Removed history from prompt!\n";
