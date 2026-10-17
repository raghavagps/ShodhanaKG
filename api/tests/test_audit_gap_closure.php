<?php
/** Deterministic-answer and request boundaries; never starts servers or writes mocks. */
require_once __DIR__.'/legacy_contract_helper.php';
require_once __DIR__.'/../core/ResponseGenerator.php';
$checks=0;
foreach (['', '{ this is not json }', ['not_question'=>'hello'], ['question'=>null], ['question'=>str_repeat('a',10000)]] as $payload) {
    $response=legacy_http_request($payload);
    if ($response['status']!=='error' || !empty($response['answer_results']) || !empty($response['citations'])) throw new RuntimeException('Invalid request accepted');
    $checks++;
}
$question='What validation studies are available for Copper?';
$context=ChatbotOrchestrator::process($question);
if ($context['status']!=='success') throw new RuntimeException('Missing independent validation seed');
legacy_assert_facts('compound_validation','Copper',$context['retrieval']);
$baseline=ResponseGenerator::generate($question,$context,[]);
foreach (['It is validated in PMID: 98765432','It is validated in 10.98765/fake-doi','It is validated in AYUSH-987654321','It uses Cow Milk','It uses Lepa','It operates at 987654321 degrees C','It takes 987654321 days','It uses 987654321 cycles','It treats Diabetes','It uses Vati'] as $untrusted) {
    $response=ResponseGenerator::generate($question,$context,[['role'=>'bot','text'=>$untrusted]]);
    if ($response!==$baseline) throw new RuntimeException('Untrusted answer history changed deterministic facts/citations');
    $checks++;
}
// The active response generator must also remain deterministic when model configuration is unavailable.
$saved=getenv('OLLAMA_BASE_URL'); putenv('OLLAMA_BASE_URL=http://127.0.0.1:1');
try {
    if (ResponseGenerator::generate($question,$context,[])!==$baseline) throw new RuntimeException('Optional model outage changed factual answer');
    $checks++;
} finally { $saved===false ? putenv('OLLAMA_BASE_URL') : putenv('OLLAMA_BASE_URL='.$saved); }
echo "PASS $checks request/active-answer boundary contracts\n";
// Retain fault/review injection coverage for the optional legacy formatter through
// its existing callable seam, rather than an uncontrolled shared mock web server.
require __DIR__.'/test_conversational_answer.php';
