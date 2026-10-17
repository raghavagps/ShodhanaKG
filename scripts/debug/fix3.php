<?php
$f = 'api/tests/test_chatbot_inverse.php';
$c = file_get_contents($f);
$c = str_replace('start "" /B', 'start \"\" /B', $c);
file_put_contents($f, $c);
