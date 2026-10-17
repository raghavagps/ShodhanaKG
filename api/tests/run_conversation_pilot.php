<?php
/** Live diagnostic pilot. Explicit output path; no reference labels are changed. */
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';
require_once __DIR__ . '/../core/ConversationalAnswer.php';
$output = $argv[1] ?? null;
if (!$output || file_exists($output)) { fwrite(STDERR, "Supply a new output JSON path\n"); exit(2); }
$questions = ['What formulations treat Vipadika?', 'What purified compounds does Swedana produce?', 'Which diseases use Laghu Sutshekhar with evidence?'];
$rows = [];
foreach ($questions as $question) {
    $context = ChatbotOrchestrator::process($question);
    $calls = [];
    $response = ConversationalAnswer::generate($question, $context['retrieval'] ?? [], [], static function ($prompt, $timeout, $schema) use (&$calls) {
        $result = LLMClient::generate($prompt, $timeout, true, $schema);
        $calls[] = $result;
        return $result;
    });
    $rows[] = ['question' => $question, 'context' => $context, 'response' => $response, 'model_calls' => $calls];
    echo json_encode(['question' => $question, 'generation' => $response['generation'] ?? null]) . "\n";
}
file_put_contents($output, json_encode(['captured_at' => gmdate('c'), 'note' => 'Diagnostic outputs, not expert-reviewed factual labels', 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
