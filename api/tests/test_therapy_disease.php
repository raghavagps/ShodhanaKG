<?php
require_once __DIR__.'/legacy_contract_helper.php';
require_once __DIR__.'/../disease_therapy.php';
require_once __DIR__.'/../therapy_disease.php';
$checks=0;
foreach (['What therapies are used to treat Alopecia?','Which therapies are used to treat Alopecia?','Alopecia is treated by which therapy?','Which therapies treat Alopecia?'] as $question) {
    $route=legacy_test_route($question,'disease_therapy');
    if (($route['routing']['endpoint'] ?? '')!=='disease_therapy') throw new RuntimeException('Wrong disease/therapy routing');
    $checks++;
}
// Panchakarma is not an exact Therapy node; context-free pronouns are not entities.
foreach (['Which diseases does Panchakarma treat?','What diseases are treated by Panchakarma?','Which diseases does this therapy treat?'] as $question) {
    $route=QuestionRouter::route($question);
    if ($route['status']!=='needs_clarification' || !empty(((array)($route['retrieval'] ?? []))['results'])) throw new RuntimeException('Unresolved therapy invented a subject');
    $checks++;
}
$previous=legacy_test_process('Which diseases does Abhyanga treat?','therapy_disease');
if ($previous['status']!=='success') throw new RuntimeException('Missing real therapy seed');
$next=ChatbotOrchestrator::process('Which diseases does this therapy treat?',[],$previous['conversation_context']);
if (($next['routing']['endpoint'] ?? '')!=='therapy_disease' || $next['retrieval']['results']!==$previous['retrieval']['results']) throw new RuntimeException('Therapy follow-up lost subject or facts');
legacy_assert_facts('therapy_disease','Abhyanga',$next['retrieval']); $checks++;
$route=QuestionRouter::route('Which therapies treat Cancer?');
$disease=execute_disease_therapy('Cancer',false);
if ($route['status']!=='needs_clarification' || $disease['status']!=='entity_not_found') throw new RuntimeException('Cancer namespace isolation changed');
$checks++;
echo "PASS $checks therapy routing/context/isolation contracts\n";
