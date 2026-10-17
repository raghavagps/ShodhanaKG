<?php
$file = 'api/core/ResponseGenerator.php';
$content = file_get_contents($file);
$content = str_replace(
    "\n\nUser Question:\n\" . \$question;",
    "\n\nUser Question:\n\" . \$question . \"\\n\\n(Note: Ignore any previous answers in history that contradict the NEW Knowledge Context provided above. The NEW Knowledge Context is your ONLY source of truth for this specific question.)\";",
    $content
);
file_put_contents($file, $content);
echo "Updated prompt again!\n";
