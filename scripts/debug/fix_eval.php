<?php
$f = 'api/tests/fixtures/chatbot_evaluation_cases.json';
$d = json_decode(file_get_contents($f), true);
foreach($d as &$c) {
    if ($c['id'] === 'eval_035') {
        $c['expected_endpoint'] = 'compound_shodhana_process';
    }
}
file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT));
