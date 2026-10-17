<?php
$cases = json_decode(file_get_contents('api/tests/fixtures/chatbot_evaluation_cases.json'), true);
file_put_contents('api/tests/fixtures/chatbot_evaluation_cases_tmp.json', json_encode(array_slice($cases, 41, 14), JSON_PRETTY_PRINT));
