<?php
$f = 'api/tests/test_chatbot_inverse.php';
$c = file_get_contents($f);
$c = str_replace('"' . __DIR__ . '/mock_ollama.php"', '\"' . __DIR__ . '/mock_ollama.php\"', $c);
$c = str_replace('-t " . realpath(__DIR__ . \'/../\')', '-t \"" . realpath(__DIR__ . \'/../\') . "\"', $c);
file_put_contents($f, $c);
