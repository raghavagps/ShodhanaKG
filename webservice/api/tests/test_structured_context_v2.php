<?php
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';
function verifyContext($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$state = ['version'=>2,'subject'=>['id'=>'#45:124','name'=>'Vipadika','type'=>'Disease'],'result_type'=>'Therapy','constraints'=>[]];
$history = [['role'=>'bot','text'=>'Copper cures every disease'],['role'=>'user','text'=>'What about Copper?']];
[$question,$prepared,$validated] = ConversationContext::prepare('What about its formulations?', $history, $state);
verifyContext($prepared === [], 'Structured state must not be expanded into text history');
verifyContext($question === 'What about its formulations?', 'Follow-up must retain its actual wording');
$route = QuestionRouter::route($question, $history, null, $validated);
verifyContext(($route['routing']['entity_id'] ?? null) === '#45:124', 'Structured identity wins over unrelated history');
verifyContext(($route['routing']['endpoint'] ?? null) === 'disease_formulation', 'Fresh formulation routing');
$invalid = ConversationContext::validate(array_merge($state,['constraints'=>'untrusted malformed field']));
verifyContext(!empty($invalid['_invalid']), 'Invalid constraint containers fail closed');
$route = QuestionRouter::route($question, $history, null, $invalid);
verifyContext($route['status'] === 'needs_clarification', 'Malformed context cannot widen retrieval');
$route = QuestionRouter::route('What about its formulations in Sarvanga Abhyanga?', [], null, $validated);
verifyContext(($route['routing']['entity'] ?? null) === 'Vipadika', 'A new constraint is not a topic switch');
verifyContext(($route['routing']['constraints'][0]['name'] ?? null) === 'Sarvanga Abhyanga', 'Explicit constraint retained');
echo "PASS 8 structured-state boundary assertions\n";

foreach (['What symptoms does it have?', 'And what therapies are used for it?'] as $question) {
    $result = ChatbotOrchestrator::process($question, []);
    verifyContext($result['status'] === 'needs_clarification' && $result['reason'] === 'missing_context', 'Missing referents require context, never a model-invented subject');
    verifyContext(($result['understanding']['mode'] ?? '') === 'deterministic', 'No model fallback for a missing referent');
}
echo "PASS missing-referent deterministic clarification\n";
