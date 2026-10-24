<?php
require_once __DIR__ . '/../core/ResponseValidator.php';

function runValidationTest($caseName, $llmOutput, $context, $expected) {
    $result = ResponseValidator::validate($llmOutput, $context);
    if ($result !== $expected) {
        echo "[FAIL] $caseName\n";
        return false;
    }
    echo "[PASS] $caseName\n";
    return true;
}

$allPass = true;

// Base valid context
$context = [
    'status' => 'success',
    'results' => [
        [
            'method' => 'Swedana',
            'media' => 'Cow Milk',
            'properties' => [
                'procedure' => 'Heated 3 times for 2 hours',
                'pubmed_id' => '37230917',
                'doi' => ''
            ]
        ]
    ]
];

echo "=== Response Validator Anti-Hallucination Tests ===\n";

$allPass = runValidationTest("Valid Output", "The compound undergoes Swedana using Cow Milk for 2 hours, as per PMID 37230917. Heated 3 times.", $context, true) && $allPass;

$allPass = runValidationTest("1. Fake DOI", "The DOI is 10.1234/fake.doi", $context, false) && $allPass;
$allPass = runValidationTest("2. Fake PMID", "PubMed ID: 12345678", $context, false) && $allPass;
$allPass = runValidationTest("3. Fake AYUSH ID", "Reported in AYUSH-XYZ123", $context, false) && $allPass;

$allPass = runValidationTest("4. Fake temperature", "It was heated to 100 degrees C.", $context, false) && $allPass;
$allPass = runValidationTest("4b. Fake temperature 2", "Temperature was 50°.", $context, false) && $allPass;

$allPass = runValidationTest("5. Fake media", "It uses Goat Milk.", $context, false) && $allPass;
$allPass = runValidationTest("6. Fake Shodhana procedure", "Then it undergoes Bhavana.", $context, false) && $allPass;

$allPass = runValidationTest("7. Fake quantitative cycles", "It was heated 7 times.", $context, false) && $allPass;
$allPass = runValidationTest("7b. Fake quantitative hours", "It was heated for 5 hours.", $context, false) && $allPass;

$allPass = runValidationTest("8. Fake disease", "It treats Alopecia.", $context, false) && $allPass;
$allPass = runValidationTest("9. Fake Process", "The process used is Marana.", $context, false) && $allPass;
$allPass = runValidationTest("10. Fake formulation", "Used in a vati formulation.", $context, false) && $allPass;

// Therapeutic Relationship Validation - Limitation demonstration
$toxContext = [
    'status' => 'success',
    'results' => [
        [
            'compound' => 'Copper',
            'disease' => 'Asthma',
            'relationship' => 'TOXICITY'
        ]
    ]
];
// Since 'Asthma' exists in context, the validator will accept this statement, even though the relationship is fabricated.
// We expect it to return true, demonstrating the limitation.
$allPass = runValidationTest("11. Therapeutic relationship hallucination limitation", "Copper is used to treat Asthma.", $toxContext, true) && $allPass;


$allPass = runValidationTest("Valid parphrased output (should pass)", "The process involves heating it three times. The PMID is 37230917, and it is boiled in Cow Milk.", $context, true) && $allPass;

// Test valid number edge cases (like the number '1' in text shouldn't fail if it doesn't match quantity keywords)
$allPass = runValidationTest("Unrelated number (should pass)", "There is 1 result. The method is Swedana.", $context, true) && $allPass;

if ($allPass) {
    echo "\n=== ALL VALIDATOR TESTS PASSED ===\n";
    exit(0);
} else {
    echo "\n=== SOME VALIDATOR TESTS FAILED ===\n";
    exit(1);
}
