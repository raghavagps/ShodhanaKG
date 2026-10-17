<?php
// Read-only audit probes; no production code or fixtures changed.
$root = dirname(__DIR__, 4);
require_once $root . '/api/core/ChatbotOrchestrator.php';
require_once $root . '/api/disease_formulation.php';
$out = ['routes' => [], 'responses' => []];
foreach (['What are the symptoms of Alopecia?', 'Which purified compounds treat Fever?', 'What diseases are treated by the purified compound from Croton tiglium?', 'Give me the Shodhana procedure for Copper', 'What method and media are used to purify Copper?', 'Which therapies are used to treat Cancer?', 'Which compounds are used for Dysureia?'] as $q) {
    $out['routes'][] = ['question' => $q, 'result' => QuestionRouter::route($q)];
}
foreach (['What are the therapeutic uses of Croton tiglium Linn.?', 'What diseases are treated by the purified compound from Croton tiglium?', 'Give me the Shodhana procedure for Copper'] as $q) {
    $out['responses'][] = ['question' => $q, 'result' => ChatbotOrchestrator::process($q)];
}
$out['disease_formulation'] = execute_disease_formulation('Alopecia');
$out['alopecia_nodes'] = arcade_command('SELECT FROM Disease WHERE name = :name', 'sql', ['name' => 'Alopecia'])['result'];
$out['raw_treatment_edges'] = [];
foreach (['TREATED_BY', 'PRE_TREATED_BY', 'POST_TREATED_BY'] as $phase) {
    foreach ($out['alopecia_nodes'] as $n) {
        $out['raw_treatment_edges'] = array_merge($out['raw_treatment_edges'], arcade_command('SELECT FROM `' . $phase . '` WHERE @out = :rid', 'sql', ['rid' => $n['@rid']])['result']);
    }
}
$out['raw_formulation_edges'] = arcade_command('SELECT FROM USES_FORMULATION')['result'];
file_put_contents(__DIR__ . '/diagnostics.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Read-only diagnostics captured\n";
