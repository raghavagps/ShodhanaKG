<?php
$file = 'api/tests/test_chatbot_orchestrator.php';
$content = file_get_contents($file);
$newTests = <<<'EOD'

echo "=== Disease Extended Tests ===\n";
$allPass = runTest("What dosha imbalance is associated with Alopecia?", "success", "disease_dosha") && $allPass;
$allPass = runTest("What is the Ayurvedic diagnosis for Fever?", "success", "disease_diagnosis") && $allPass;
$allPass = runTest("What assessment parameters are used for Diabetes?", "success", "disease_assessment") && $allPass;
$allPass = runTest("What compounds treat Alopecia?", "success", "integrated_disease_compound") && $allPass;
$allPass = runTest("What therapies are used to treat Fever?", "success", "disease_therapy") && $allPass;

echo "=== Purified Compound Extended Tests ===\n";
$allPass = runTest("What formulations contain Shodhita Guggulu?", "success", "purified_compound_formulation") && $allPass;
$allPass = runTest("What raw compound is the source of Shodhita Guggulu?", "success", "purified_compound_source") && $allPass;

echo "=== Compound Extended Tests ===\n";
$allPass = runTest("What validation studies exist for Zinc?", "success", "compound_validation") && $allPass;
$allPass = runTest("How is Croton tiglium Linn. detoxified?", "success", "compound_detoxification") && $allPass;
$allPass = runTest("What purification does Mercury undergo?", "success", "compound_purification") && $allPass;
$allPass = runTest("What are the therapeutic uses of Copper?", "success", "compound_therapeutic_use") && $allPass;
$allPass = runTest("What diseases are linked to Mercury?", "success", "integrated_compound_disease") && $allPass;

echo "=== Indication Tests ===\n";
$allPass = runTest("Which compounds are indicated for Cough?", "success", "indication_compound") && $allPass;

if ($allPass) {
EOD;
$content = str_replace('if ($allPass) {', $newTests, $content);
file_put_contents($file, $content);
echo "Done\n";
