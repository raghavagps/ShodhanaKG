<?php
$file = 'api/core/ResponseGenerator.php';
$content = file_get_contents($file);
$content = str_replace(
    'The supplied Knowledge Context is the only source of factual information.',
    "The supplied Knowledge Context is the only source of factual information.\nCRITICAL RULE: Always prioritize the NEW Knowledge Context over any previous information in the Conversation History. If the NEW Knowledge Context contradicts a previous BOT answer, completely ignore the previous answer.",
    $content
);
file_put_contents($file, $content);
echo "Updated system prompt!\n";
