<?php
require_once __DIR__ . '/../core/PathEvidence.php';
require_once __DIR__ . '/../core/LegacyEvidence.php';
require_once __DIR__ . '/../core/ConversationContext.php';
require_once __DIR__ . '/../core/SemanticQuery.php';
require_once __DIR__ . '/../core/QuestionRouter.php';
$count = 0;
function semanticAssert($ok, $message) { global $count; $count++; if (!$ok) throw new RuntimeException($message); }
$nodes = [
    ['id' => '#999:0', 'type' => 'Compound', 'label' => 'Raw'],
    ['id' => '#999:1', 'type' => 'Shodhana_Method', 'label' => 'Method'],
    ['id' => '#999:2', 'type' => 'Process', 'label' => 'Process'],
    ['id' => '#999:3', 'type' => 'Purified_Compound', 'label' => 'Purified'],
    ['id' => '#999:4', 'type' => 'Disease', 'label' => 'Disease']
];
$edges = [];
foreach (['UNDERGOES', 'FOLLOWED_BY', 'PRODUCES', 'USED_FOR_TREATMENT'] as $i => $type) {
    $props = ['pubmedId' => $i === 3 ? '22222222' : '11111111', 'Procedure' => 'Stored detail'];
    $edges[] = ['id' => '#998:' . $i, 'type' => $type, 'source' => $nodes[$i]['id'], 'target' => $nodes[$i+1]['id'], 'properties' => $props, 'evidence' => EvidenceCorrelator::extractIdentifiers($props, '#998:' . $i)];
}
$path = ['nodes' => $nodes, 'edges' => $edges, 'evidence_policy' => 'separate_treatment'];
$path['evidence_groups'] = PathEvidence::groups($path, true);
semanticAssert(count($path['evidence_groups']) === 2, 'Treatment study is separate');
semanticAssert(PathEvidence::groups($path, false) === null, 'Strict provenance cannot join different studies');
LegacyEvidence::validate($path); semanticAssert(true, 'Valid grouped path accepted');
$reversed = $path; $reversed['nodes'] = array_reverse($nodes); $reversed['edges'] = array_reverse($edges);
$reversed['evidence_groups'] = PathEvidence::groups($reversed, true);
LegacyEvidence::validate($reversed); semanticAssert(count($reversed['evidence_groups']) === 2, 'Reverse traversal preserves stored directions and groups');
foreach (['edge_ids', 'reference'] as $field) {
    $bad = $path; $bad['evidence_groups'][0][$field] = [];
    try { LegacyEvidence::validate($bad); semanticAssert(false, 'Forged grouping accepted'); }
    catch (RuntimeException $e) { semanticAssert($e->getMessage() === 'Invalid study groups', 'Forged grouping rejected'); }
}
$bad = $path; $bad['edges'][1]['evidence'] = $edges[3]['evidence'];
semanticAssert(PathEvidence::groups($bad, true) === null, 'No study switch inside purification');
$bad = $path; $bad['nodes'][3]['type'] = 'Compound';
semanticAssert(PathEvidence::groups($bad, true) === null, 'A raw compound is not a purified treatment boundary');
$same = $path; $same['edges'][3]['evidence'] = $edges[0]['evidence'];
semanticAssert(count(PathEvidence::groups($same, true)) === 1, 'Same study is not split or labelled cross-study');
$unknown = $path; $unknown['edges'][3]['evidence'] = EvidenceCorrelator::extractIdentifiers(['ref' => '99999999'], '#998:3');
semanticAssert(PathEvidence::groups($unknown, true) === null, 'Unclassified numbers cannot authorize cross-study joins');
foreach (['Which method purifies lead?', 'What Shodhana method is used to purify lead?', 'Which purification methods are used for copper?'] as $q) semanticAssert(SemanticQuery::target($q) === 'Shodhana_Method', 'Requested method type');
semanticAssert(SemanticQuery::target('Which purified compounds are derived from Copper?') === 'Purified_Compound', 'Requested product type');
$forged = ConversationContext::validate(['version' => 1, 'subject' => ['id' => '#999:999', 'name' => 'Copper', 'type' => 'Compound'], 'facts' => ['invented'], 'constraints' => [null, 42]]);
semanticAssert(!isset($forged['subject']) && !isset($forged['facts']), 'Stale identity and client facts cannot enter retrieval context');
semanticAssert(SemanticQuery::definitions('Unknown', 'Disease') === [], 'Unknown schema source rejected');
foreach (['Which diseases are associated with method Nirvapa?', 'What does method Nirvapa treat?'] as $question) {
    $route = QuestionRouter::route($question);
    semanticAssert(($route['routing']['endpoint'] ?? '') === 'semantic_query' && $route['routing']['entity_type'] === 'Shodhana_Method', 'Method treatment questions cannot execute a Therapy endpoint');
}
echo "PASS $count semantic path, grouping, direction, type and context assertions\n";
