<?php
$testFailures = 0;
require_once __DIR__ . '/../core/QuestionRouter.php';
require_once __DIR__ . '/../compound_shodhana_process.php';

echo "Testing compound_shodhana_process endpoint and routing...\n\n";

$queries = [
    "What is the complete Shodhana process for Copper?",
    "How is Copper purified?",
    "What method and media are used to purify Copper?",
    "Give me the Shodhana procedure for Copper",
    "How is Copper purified using Shodhana?"
];

$passed = 0;
foreach ($queries as $q) {
    $routing = QuestionRouter::route($q);
    $intent = isset($routing['routing']['intent']) ? $routing['routing']['intent'] : (isset($routing['intent']) ? $routing['intent'] : '');
    if ($intent === 'compound_shodhana_process') {
        echo "[PASS] Query correctly routed: $q\n";
        $passed++;
    } else {
        $testFailures++; echo "[FAIL] Query failed to route to compound_shodhana_process (got {$intent}): $q\n";
    }
}

$old_query = "What Shodhana method is used for Copper?";
$routing = QuestionRouter::route($old_query);
$intent = isset($routing['routing']['intent']) ? $routing['routing']['intent'] : (isset($routing['intent']) ? $routing['intent'] : '');
if ($intent === 'compound_methods') {
    echo "[PASS] Existing query preserved: $old_query\n";
} else {
    $testFailures++; echo "[FAIL] Existing query routing broken, got: {$intent}\n";
}

echo "\nTesting execution...\n";
$res = execute_compound_shodhana_process('Copper', false);
if (isset($res['status']) && $res['status'] === 'success') {
    echo "[PASS] Copper process retrieved.\n";
} else {
    $testFailures++; echo "[FAIL] Copper process failed or no data. Response: " . json_encode($res) . "\n";
}

// Test quantity queries
$q_query = "Name any 5 media used for Copper Shodhana";
$routing = QuestionRouter::route($q_query);
$intent = isset($routing['routing']['intent']) ? $routing['routing']['intent'] : (isset($routing['intent']) ? $routing['intent'] : '');
if ($intent === 'compound_media' || $intent === 'compound_shodhana_process' || (isset($routing['status']) && $routing['status'] === 'needs_clarification')) {
    echo "[PASS] Quantity query routed properly: " . json_encode($routing) . "\n";
} else {
    $testFailures++; echo "[FAIL] Quantity query routing failed: " . json_encode($routing) . "\n";
}

echo "\nTesting unknown compound...\n";
$intent_unk = QuestionRouter::route("What is the Shodhana process for UnknownCompoundX123?");
if ($intent_unk['status'] === 'entity_not_found' || $intent_unk['status'] === 'needs_clarification') {
    echo "[PASS] Unknown compound correctly rejected.\n";
} else {
    $testFailures++; echo "[FAIL] Unknown compound handling failed: " . json_encode($intent_unk) . "\n";
}

echo "\nDone testing compound_shodhana_process.\n";

exit($testFailures ? 1 : 0);
