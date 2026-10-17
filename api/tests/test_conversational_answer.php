<?php
require_once __DIR__ . '/../core/ConversationalAnswer.php';
function checkConversation($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$cases = json_decode(file_get_contents(__DIR__ . '/fixtures/legacy_factual_evaluation_cases_v3.json'), true);
$case = array_values(array_filter($cases, static function ($c) { return $c['expected_endpoint'] === 'disease_formulation'; }))[0];
$retrieval = ['status' => 'success', 'entity' => ['name' => $case['expected_entity']], 'results' => $case['expected_results']];
$packet = ConversationalAnswer::packet($retrieval);
$selected = []; $seen = [];
foreach ($packet as $fact) {
    if (isset($seen[$fact['statement']])) continue;
    $seen[$fact['statement']] = true;
    if (count($selected) < 4) $selected[] = $fact;
}
$sentences = array_map(static function ($f) { return ['fact_id' => $f['fact_ids'][0], 'text' => $f['statement']]; }, $selected);
$reviews = array_fill(0, count($selected), 'supported');
putenv('CHATBOT_ANSWER_MODE=hybrid');
$calls = 0;
$accepted = ConversationalAnswer::generate('What formulations are recorded?', $retrieval, [], static function ($prompt, $timeout) use (&$calls, $sentences, $reviews) {
    checkConversation($timeout > 0 && $timeout <= ($calls === 0 ? 30 : 15), 'Unbounded model timeout');
    return ['status' => 'success', 'text' => json_encode($calls++ === 0 ? ['sentences' => $sentences] : ['reviews' => $reviews])];
});
checkConversation($calls === 2 && $accepted['generation']['mode'] === 'hybrid', 'Valid reviewed answer rejected');
checkConversation(count($accepted['answer_blocks']) <= 4 && count($accepted['citations']) === count($retrieval['results']), 'Summary hid source records');
checkConversation(strpos($accepted['answer'], 'Graph results') === false && strpos($accepted['answer'], 'properties:') === false, 'Database dump leaked');
foreach (['unavailable', 'malformed', 'wrong_id', 'number', 'missing_entity', 'unsupported', 'uncertain', 'review_missing', 'false_string', 'citation', 'efficacy', 'metadata'] as $failure) {
    $calls = 0;
    $result = ConversationalAnswer::generate('q', $retrieval, [], static function ($prompt, $timeout) use (&$calls, $sentences, $reviews, $failure) {
        if ($failure === 'unavailable') return ['status' => 'llm_error'];
        if ($failure === 'malformed') return ['status' => 'success', 'text' => 'not JSON'];
        if ($calls++ === 0) {
            if ($failure === 'wrong_id') $sentences[0]['fact_id'] = 'f999999';
            if ($failure === 'number') $sentences[0]['text'] .= ' It lasts 987654 days.';
            if ($failure === 'missing_entity') $sentences[0]['text'] = 'An unrelated association.';
            if ($failure === 'citation') $sentences[0]['text'] .= ' [PMID: 123]';
            if ($failure === 'efficacy') $sentences[0]['text'] .= ' This treats the condition.';
            if ($failure === 'metadata') $sentences[0]['text'] .= ' These are required entities.';
            return ['status' => 'success', 'text' => json_encode(['sentences' => $sentences])];
        }
        if ($failure === 'unsupported') $reviews[0] = 'unsupported';
        if ($failure === 'uncertain') $reviews[0] = 'uncertain';
        if ($failure === 'false_string') $reviews[0] = 'true';
        if ($failure === 'review_missing') array_pop($reviews);
        return ['status' => 'success', 'text' => json_encode(['reviews' => $reviews])];
    });
    checkConversation($result['generation']['mode'] === 'fallback' && $result['status'] === 'success', 'Failure did not fall back: ' . $failure);
    checkConversation(strpos($result['answer'], '987654') === false, 'Rejected text leaked');
}
// A correct reference does not excuse a reversed relationship or unsupported finding.
$calls = 0;
$result = ConversationalAnswer::generate('q', $retrieval, [], static function ($prompt) use (&$calls, $sentences, $reviews) {
    if ($calls++ === 0) {
        $sentences[0]['text'] .= ' This cures the condition.';
        return ['status' => 'success', 'text' => json_encode(['sentences' => $sentences])];
    }
    checkConversation(strpos($prompt, 'This cures the condition.') !== false && strpos($prompt, 'ONLY its own evidence') !== false, 'Reviewer not given attributed claims');
    $reviews[0] = 'unsupported';
    return ['status' => 'success', 'text' => json_encode(['reviews' => $reviews])];
});
checkConversation($result['generation']['mode'] === 'fallback' && strpos($result['answer'], 'cures') === false, 'Unsupported finding displayed');
// Reviewer rejection must also block reversed directed claims with valid IDs.
$canonical = json_decode(file_get_contents(__DIR__ . '/fixtures/factual_evaluation_cases_v3.json'), true)[0];
$single = ['status' => 'success', 'entity' => ['name' => $canonical['expected_entity']], 'results' => [$canonical['expected_results'][0]]];
$nodes = $single['results'][0]['nodes'];
$reversed = $nodes[1]['label'] . ' is recorded as using ' . $nodes[0]['label'] . '.';
$calls = 0;
$result = ConversationalAnswer::generate('q', $single, [], static function () use (&$calls, $reversed) {
    return ['status' => 'success', 'text' => json_encode($calls++ === 0 ? ['sentences' => [['fact_id' => 'f1', 'text' => $reversed]]] : ['reviews' => ['unsupported']])];
});
checkConversation($result['generation']['mode'] === 'fallback' && $result['answer_blocks'][0]['text'] !== $reversed, 'Reversed relationship displayed');
$oversized = ConversationalAnswer::generate(str_repeat('question ', 2000), $single, [], static function () {
    throw new LogicException('Oversized prompt must not call the model');
});
checkConversation($oversized['generation']['fallback_reason'] === 'context_too_large', 'Oversized prompt not bounded');
putenv('CHATBOT_ANSWER_MODE=fallback');
require_once __DIR__ . '/../core/ResponseGenerator.php';
$context = ['status' => 'success', 'retrieval' => $retrieval];
$json = ResponseGenerator::generate('q', $context);
$stream = '';
$done = ResponseGenerator::generateStream('q', $context, [], static function ($chunk) use (&$stream) { $stream .= $chunk; });
checkConversation($stream === $json['answer'] && $done['citations'] === $json['citations'] && $done['answer_blocks'] === $json['answer_blocks'], 'JSON/SSE presentation mismatch');
echo "PASS conversational acceptance, rejection, complete attribution, bounded calls, fallback and stream parity\n";
