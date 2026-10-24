<?php
require_once __DIR__ . '/../core/QuestionRouter.php';

$history = [
    ['role' => 'user', 'text' => 'What media is used for Copper?'],
    ['role' => 'bot', 'text' => 'Copper uses Buttermilk.']
];

$routing = QuestionRouter::route("Does it have validation studies?", $history);
if ($routing['status'] === 'success' && $routing['routing']['endpoint'] === 'compound_validation') {
    echo "PASS: Pronoun resolution works with history.\n";
    exit(0);
} else {
    echo "FAIL: Pronoun resolution failed.\n";
    var_dump($routing);
    exit(1);
}
