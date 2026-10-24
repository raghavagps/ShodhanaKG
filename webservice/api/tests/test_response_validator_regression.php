<?php
require_once __DIR__ . '/../core/ResponseValidator.php';

echo "==================================================\n";
echo "PHASE 5 — RESPONSE VALIDATOR REGRESSION\n";
echo "==================================================\n\n";

$results = [
    'total' => 0,
    'PASS' => 0,
    'KNOWN_LIMITATION' => 0,
    'UNEXPECTED_FAILURE' => 0,
    'details' => []
];

function execute_test($name, $category, $context, $llmOutput, $expectedResult, $expectedClassification) {
    global $results;
    $results['total']++;
    
    $actualResult = ResponseValidator::validate($llmOutput, $context);
    
    $classification = '';
    if ($actualResult === $expectedResult) {
        if ($expectedClassification === 'KNOWN_LIMITATION') {
            // It behaved as expected, but the expectation itself is a known limitation
            $classification = 'KNOWN_LIMITATION';
        } else {
            $classification = 'PASS';
        }
    } else {
        $classification = 'UNEXPECTED_FAILURE';
    }
    
    $results[$classification]++;
    $results['details'][] = [
        'name' => $name,
        'category' => $category,
        'classification' => $classification,
        'actual' => $actualResult ? 'true' : 'false',
        'expected' => $expectedResult ? 'true' : 'false'
    ];
    
    echo "Test: [$category] $name\n";
    echo "  Expected: " . ($expectedResult ? 'true' : 'false') . " ($expectedClassification)\n";
    echo "  Actual:   " . ($actualResult ? 'true' : 'false') . "\n";
    echo "  Result:   $classification\n\n";
}

// --------------------------------------------------
// A. VALID GROUNDED ANSWERS
// --------------------------------------------------
$contextA = [
    'results' => [
        [
            'Compound' => 'Croton tiglium Linn.',
            'Method' => 'Swedana',
            'Media' => 'Cow Milk',
            'Temperature' => '100 °C',
            'Duration' => '3 hours',
            'PMID' => '108950'
        ]
    ]
];
execute_test(
    "Accept fully grounded statement",
    "Valid Grounded",
    $contextA,
    "Croton tiglium Linn. was purified using Swedana with Cow Milk at 100 °C for 3 hours according to PMID 108950.",
    true,
    "PASS"
);

// --------------------------------------------------
// B. ENTITY HALLUCINATION
// --------------------------------------------------
execute_test(
    "Fail to reject hallucinated entity not in hardcoded schema list",
    "Entity Hallucination",
    $contextA,
    "Croton tiglium Linn. was purified using Triphala Kwatha.", // Triphala Kwatha is not in context and NOT in $schemaEntities
    true, // EXPECTED BEHAVIOR OF CURRENT CODE: TRUE
    "KNOWN_LIMITATION"
);

execute_test(
    "Reject unsupported schema entity",
    "Entity Hallucination",
    $contextA,
    "Croton tiglium Linn. was purified using Goat Milk.", // Goat Milk is in ResponseValidator::$schemaEntities
    false,
    "PASS"
);

// --------------------------------------------------
// C. NUMERIC HALLUCINATION
// --------------------------------------------------
execute_test(
    "Reject unsupported temperature",
    "Numeric Hallucination",
    $contextA,
    "The compound was heated at 500 °C for 3 hours.",
    false,
    "PASS"
);
execute_test(
    "Reject unsupported duration",
    "Numeric Hallucination",
    $contextA,
    "The compound was heated at 100 °C for 10 hours.",
    false,
    "PASS"
);

// --------------------------------------------------
// D. IDENTIFIER HALLUCINATION
// --------------------------------------------------
execute_test(
    "Reject unsupported PMID",
    "Identifier Hallucination",
    $contextA,
    "The process is documented in PMID 99999999.",
    false,
    "PASS"
);
execute_test(
    "Reject unsupported DOI",
    "Identifier Hallucination",
    $contextA,
    "The process is documented in DOI 10.1234/fake-doi.",
    false,
    "PASS"
);
execute_test(
    "Reject unsupported AYUSH ID",
    "Identifier Hallucination",
    $contextA,
    "The process is documented in AYUSH-999.",
    false,
    "PASS"
);

// --------------------------------------------------
// E. RELATIONSHIP HALLUCINATION (KNOWN LIMITATION)
// --------------------------------------------------
$contextE = [
    'results' => [
        [
            'Compound' => 'Croton tiglium Linn.',
            'Method' => 'Swedana'
        ],
        [
            'Disease' => 'Asthma',
            'Therapy' => 'Vati'
        ]
    ]
];
execute_test(
    "Fail to reject unrelated entities present in context",
    "Relationship Hallucination",
    $contextE,
    "Croton tiglium Linn. treats Asthma.",
    true, // EXPECTED BEHAVIOR OF CURRENT CODE: TRUE (accepts it because both strings are in JSON)
    "KNOWN_LIMITATION"
);

// --------------------------------------------------
// F. PROVENANCE CONTAMINATION (KNOWN LIMITATION)
// --------------------------------------------------
$contextF = [
    'results' => [
        [
            'Compound' => 'Abrus precatorius Linn.',
            'Purified Compound' => 'Shodhita Gunja',
            'Method' => 'Swedana',
            'PMID' => '108950'
        ],
        [
            'Compound' => 'Datura metel L.',
            'Purified Compound' => 'Shodhita Dhattura',
            'Method' => 'Swedana',
            'PMID' => '165494'
        ]
    ]
];
execute_test(
    "Fail to reject mixed provenance across valid records",
    "Provenance Contamination",
    $contextF,
    "Shodhita Gunja was produced from Datura metel L. according to PMID 108950.",
    true, // Expected to accept due to string-only validation
    "KNOWN_LIMITATION"
);

// --------------------------------------------------
// G. INTEGRATED PATH HALLUCINATION (KNOWN LIMITATION)
// --------------------------------------------------
$contextG = [
    'results' => [
        [
            'Compound' => 'Copper',
            'Disease' => 'Fever'
        ],
        [
            'Compound' => 'Zinc',
            'Disease' => 'Alopecia'
        ]
    ]
];
execute_test(
    "Fail to reject cross-connected integrated paths",
    "Integrated Path Hallucination",
    $contextG,
    "Copper treats Alopecia.",
    true, // Expected to accept due to string-only validation
    "KNOWN_LIMITATION"
);

// --------------------------------------------------
// H. QUALITATIVE HALLUCINATION (KNOWN LIMITATION)
// --------------------------------------------------
execute_test(
    "Fail to reject qualitative claims",
    "Qualitative Hallucination",
    $contextA,
    "This treatment is highly effective and completely safe.",
    true, // Expected to accept since no numbers, schema entities, or IDs are used
    "KNOWN_LIMITATION"
);

// --------------------------------------------------
// I. FORMATTING TOLERANCE
// --------------------------------------------------
execute_test(
    "Accept varied formatting of valid data",
    "Formatting Tolerance",
    $contextA,
    "1. Compound: Croton tiglium Linn.\n2. Method: Swedana\n3. Media: Cow Milk\n- Temp: 100 °C\n- Time: 3 hours\n(Ref: PMID 108950)",
    true,
    "PASS"
);


echo "==================================================\n";
echo "FINAL RESULTS\n";
echo "Total Executed: {$results['total']}\n";
echo "PASS: {$results['PASS']}\n";
echo "KNOWN LIMITATION: {$results['KNOWN_LIMITATION']}\n";
echo "UNEXPECTED FAILURE: {$results['UNEXPECTED_FAILURE']}\n";
echo "==================================================\n";

if ($results['UNEXPECTED_FAILURE'] > 0) {
    exit(1);
}
exit(0);

