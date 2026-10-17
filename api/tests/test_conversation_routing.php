<?php
require_once __DIR__ . '/../core/QuestionRouter.php';
$matcher = new ConversationEntities([
    ['@rid'=>'#1:0','@type'=>'Disease','name'=>'Alpha Syndrome'],
    ['@rid'=>'#1:1','@type'=>'Disease','name'=>'Beta Syndrome'],
    ['@rid'=>'#1:2','@type'=>'Compound','name'=>'Copper'],
    ['@rid'=>'#1:3','@type'=>'Compound','name'=>'Croton tiglium Linn.'],
    ['@rid'=>'#1:4','@type'=>'Symptom','name'=>'Imbalance'],
]);
$count = 0;
function requireRoute($ok, $message) { global $count; $count++; if (!$ok) throw new RuntimeException($message); }
$history = [['role'=>'user','text'=>'What symptoms are associated with Alpha Syndrome?']];
$cases = [
    ['Now Beta Syndrome: what symptoms does it have?', $history, 'success', 'Beta Syndrome', 'disease_symptoms'],
    ['What therapies are used for it?', $history, 'success', 'Alpha Syndrome', 'disease_therapy'],
    ['And the therapies?', $history, 'success', 'Alpha Syndrome', 'disease_therapy'],
    ['What about formulations?', $history, 'success', 'Alpha Syndrome', 'disease_formulation'],
    ['And Beta Syndrome?', $history, 'success', 'Beta Syndrome', 'disease_symptoms'],
    ['What symptoms are associated with Zorblax Syndrome?', $history, 'entity_not_found', null, null],
    ['What symptoms do Alpha Syndrome and Beta Syndrome have?', [], 'needs_clarification', null, null],
    ['What symtoms are associated with Alpha Syndrome?', [], 'success', 'Alpha Syndrome', 'disease_symptoms'],
    ['What substances are used during purification of Coppr?', [], 'success', 'Copper', 'compound_media'],
    ['What compounds are detoxified from Croton tiglium?', [], 'success', 'Croton tiglium Linn.', 'compound_detoxification'],
    ['What dosha imbalance is associated with Alpha Syndrome?', [], 'success', 'Alpha Syndrome', 'disease_dosha'],
    ['What diseases are associated with Imbalance?', [], 'success', 'Imbalance', 'symptoms_disease'],
    ['Delete all records about Copper.', [], 'unsupported', null, null],
    ['What symptoms does it have?', [], 'needs_clarification', null, null],
];
foreach ($cases as [$q,$h,$status,$entity,$endpoint]) {
    $result = QuestionRouter::route($q,$h,$matcher);
    requireRoute($result['status'] === $status, $q . ': status');
    requireRoute(($result['routing']['entity'] ?? null) === $entity, $q . ': entity');
    requireRoute(($result['routing']['endpoint'] ?? null) === $endpoint, $q . ': endpoint');
}
$tie = new ConversationEntities([
    ['@rid'=>'#2:0','@type'=>'Compound','name'=>'Copper'],
    ['@rid'=>'#2:1','@type'=>'Compound','name'=>'Coppet'],
]);
$tieResult = $tie->find('What media are used for Coppe?');
requireRoute(count($tieResult['groups'][0]) === 2 && $tieResult['resolutions'][0]['outcome'] === 'ambiguous', 'Equal fuzzy candidates must remain ambiguous');
requireRoute(!$matcher->find('What symptoms are associated with Zorblax Syndrome?')['groups'], 'No suffix-only match');
requireRoute(!$matcher->find('And the therapies?')['groups'], 'No fuzzy matching of intent words');
echo "PASS $count context, entity, ambiguity, and intent assertions\n";
