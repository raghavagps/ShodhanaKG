<?php
$f = 'api/tests/test_chatbot_inverse.php';
$c = file_get_contents($f);
$c = preg_replace('/-t [^\,]+, "r"\)\);/', '-t .", "r"));', $c);
file_put_contents($f, $c);
