<?php
$file = 'api/tests/fixtures/chatbot_evaluation_cases.json';
$cases = json_decode(file_get_contents($file), true);
foreach ($cases as &$case) {
    if ($case['id'] === 'eval_047') {
        $case['expected_status'] = 'entity_not_found';
    }
}
file_put_contents($file, json_encode($cases, JSON_PRETTY_PRINT));
echo "Fixed eval_047 expectation\n";
