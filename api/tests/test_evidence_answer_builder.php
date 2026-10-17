<?php
require_once __DIR__ . '/../core/ResponseGenerator.php';
function demand($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$_ENV['OLLAMA_BASE_URL'] = 'http://127.0.0.1:1'; // A model must not be needed.
$cases = json_decode(file_get_contents(__DIR__ . '/fixtures/factual_evaluation_cases_v3.json'), true, 512, JSON_THROW_ON_ERROR);
putenv('CHATBOT_ANSWER_MODE=fallback');
$count = 0;
foreach ($cases as $case) {
    $context = ['status' => 'success', 'retrieval' => ['status' => 'success', 'entity' => ['name' => $case['expected_entity']], 'results' => $case['expected_results']]];
    $json = ResponseGenerator::generate($case['question'], $context);
    demand($json['status'] === 'success', $case['id'] . ' failed construction');
    foreach ($case['expected_results'] as $row) demand(strpos(json_encode($json['citations'], JSON_UNESCAPED_UNICODE), $row['related_entity']) !== false, 'Missing target: ' . $row['related_entity']);
    demand(strpos($json['answer'], 'same study') === false, 'Invented study grouping');
    $stream = '';
    $done = ResponseGenerator::generateStream($case['question'], $context, [], static function ($chunk) use (&$stream) { $stream .= $chunk; });
    demand($done['answer_blocks'] === $json['answer_blocks'] && $done['citations'] === $json['citations'] && $stream === $json['answer'], 'JSON/SSE mismatch');
    $count++;
}
$case = $cases[8];
$doiChecked = false;
foreach ($cases as $reference) {
    foreach ($reference['expected_results'] as $row) {
        $doi = $row['edges'][0]['evidence']['doi'] ?? '';
        if ($doi === '') continue;
        $answer = EvidenceAnswerBuilder::build(['status' => 'success', 'entity' => ['name' => $reference['expected_entity']], 'results' => [$row]]);
        demand($answer['status'] === 'success' && strpos($answer['answer'], 'Stored reference: doi: ' . $doi) !== false, 'DOI not attributed as DOI');
        demand(strpos($answer['answer'], 'pubmed id: ' . $doi) === false, 'DOI mislabeled in answer');
        $badRow = $row;
        $badRow['edges'][0]['evidence']['pubmed_id'] = $doi;
        $badRow['edges'][0]['evidence']['doi'] = '';
        demand(EvidenceAnswerBuilder::build(['status' => 'success', 'entity' => ['name' => $reference['expected_entity']], 'results' => [$badRow]])['status'] === 'error', 'Historical DOI-as-PMID label accepted');
        $doiChecked = true;
        break 2;
    }
}
demand($doiChecked, 'No DOI answer regression exercised');
$bad = $case['expected_results'][0];
$bad['edges'][1]['evidence']['pubmed_id'] = 'other study';
$stream = '';
$result = ResponseGenerator::generateStream('q', ['status' => 'success', 'retrieval' => ['status' => 'success', 'entity' => ['name' => $case['expected_entity']], 'results' => [$bad]]], [], static function ($chunk) use (&$stream) { $stream .= $chunk; });
demand($result['status'] === 'error' && strpos($stream, 'other study') === false && strpos($stream, $bad['related_entity']) === false, 'Invalid path leaked before validation');
$legacy = ['status' => 'success', 'entity' => ['name' => 'A new seed'], 'results' => [
    ['method' => 'Novel method', 'media' => 'Novel media', 'properties' => ['pubmed_id' => '111', 'duration' => '3 Days']],
    ['method' => 'Other method', 'media' => 'Other media', 'validation_properties' => ['pubmed_id' => '222'], 'safety_evidence' => ['doi' => 'separate']]]];
$answer = EvidenceAnswerBuilder::build($legacy);
demand($answer['status'] === 'success' && strpos($answer['answer'], 'Novel media') !== false && strpos($answer['answer'], 'validation properties') !== false && strpos($answer['answer'], 'safety evidence') !== false, 'Legacy attribution scopes lost');
demand(strpos($answer['answer'], 'same study') === false, 'Legacy records merged');
$invalid = $cases[0]['expected_results'][0];
$invalid['edges'][0]['target'] = '#999:999';
demand(EvidenceAnswerBuilder::build(['status' => 'success', 'entity' => ['name' => $cases[0]['expected_entity']], 'results' => [$invalid]])['status'] === 'error', 'Disconnected path accepted');
$invalid = $cases[0]['expected_results'][0];
$invalid['edges'][0]['evidence']['pubmed_id'] = 'wrong attribution';
demand(EvidenceAnswerBuilder::build(['status' => 'success', 'entity' => ['name' => $cases[0]['expected_entity']], 'results' => [$invalid]])['status'] === 'error', 'Single-edge reference mismatch accepted');
demand(EvidenceAnswerBuilder::build(['status' => 'success', 'entity' => ['name' => 'x'], 'results' => []])['status'] === 'error', 'Empty success accepted');
$good = $cases[0]['expected_results'][0];
$envelope = ['status' => 'success', 'entity' => ['name' => $cases[0]['expected_entity']], 'results' => [$good]];
demand(EvidenceAnswerBuilder::build($envelope)['status'] === 'success', 'Identity test baseline invalid');
$mutated = $envelope;
$mutated['results'][0]['related_entity'] = 'Invented label';
demand(EvidenceAnswerBuilder::build($mutated)['status'] === 'error', 'Conflicting related label accepted');
$mutated = $envelope;
$mutated['entity']['name'] = 'Different seed';
demand(EvidenceAnswerBuilder::build($mutated)['status'] === 'error', 'Wrong seed accepted');
$mutated = $envelope;
$mutated['results'][0]['nodes'][1]['id'] = $good['nodes'][0]['id'];
$mutated['results'][0]['edges'][0]['target'] = $good['nodes'][0]['id'];
demand(EvidenceAnswerBuilder::build($mutated)['status'] === 'error', 'Conflicting labels for same node ID accepted');
echo "PASS $count frozen-reference answers: complete cited target coverage, JSON/SSE parity, no model dependency, rejected corrupt evidence, legacy attribution\n";
