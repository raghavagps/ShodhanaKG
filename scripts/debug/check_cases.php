<?php
$cases = json_decode(file_get_contents('api/tests/fixtures/chatbot_evaluation_cases.json'), true);
echo "Total cases in file: " . count($cases) . "\n";
echo "First case ID: " . $cases[0]['id'] . "\n";
echo "Last case ID: " . $cases[count($cases)-1]['id'] . "\n";
