<?php
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';
require_once __DIR__ . '/../core/ResponseGenerator.php';
require_once __DIR__ . '/../db.php';

// The active results-first pipeline is deterministic. Tests never manage servers.
putenv('CHATBOT_ANSWER_MODE=fallback');

echo "=== Context Immutability Test ===\n";

$question = "What media is used for Copper?";

// 1. Supply a mock orchestrator output to avoid DB dependency
$originalContext = [
    'status' => 'success',
    'routing' => [
        'domain' => 'compound',
        'intent' => 'compound_media',
        'entity' => 'Copper',
        'entity_type' => 'Compound',
        'endpoint' => '/api/compound_media.php',
        'confidence' => 1,
        'needs_clarification' => false
    ],
    'retrieval' => [
        'status' => 'success',
        'entity' => ['name' => 'Copper', 'type' => 'Compound'],
        'path' => ['Compound', 'UNDERGOES', 'Shodhana_Method', 'USES_MEDIA', 'Media'],
        'results' => [
            [
                'method' => 'Samanya Shodhana',
                'media' => 'Cow Urine',
                'properties' => ['procedure' => 'Boiled for 3 hours']
            ]
        ]
    ]
];

// 2. Perform a strict deep copy of the original context
$strictCopyBeforeGeneration = unserialize(serialize($originalContext));

// 3. Run the generation pipeline
$generationResult = ResponseGenerator::generate($question, $originalContext);

// 4. In chatbot_response.php, the final API response contains the context directly.
// We must verify $strictCopyBeforeGeneration === $originalContext
if ($strictCopyBeforeGeneration === $originalContext) {
    echo "[PASS] STRICTLY VERIFIED: Context is strictly immutable.\n";
    echo "       The orchestrator response strictly matches the object passed through generation.\n";
    exit(0);
} else {
    echo "[FAIL] Context was mutated during the pipeline!\n";
    exit(1);
}
