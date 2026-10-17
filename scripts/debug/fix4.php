<?php
$f = 'api/tests/fixtures/chatbot_evaluation_cases.json';
$c = file_get_contents($f);
if (substr($c, 0, 3) == "\xEF\xBB\xBF") {
    $c = substr($c, 3);
}
// Try decoding
$cases = json_decode($c, true);
if (!is_array($cases)) {
    echo "Still failing decode\n";
    echo substr($c, 0, 50);
} else {
    // Write clean file
    file_put_contents($f, json_encode($cases, JSON_PRETTY_PRINT));
    echo "Cleaned\n";
}
