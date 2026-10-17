<?php
$f = 'api/tests/test_chatbot_inverse.php';
$c = file_get_contents($f);
// Find the exact line and replace it
$c = preg_replace('/pclose\(popen\("set OLLAMA_BASE_URL=([^"]+)"\)\);/is', '', $c); // just in case
$c = str_replace('start /B php -S', 'start "" /B php -S', $c);
file_put_contents($f, $c);
