<?php
/** Live integration runner. Direct mode requires ArcadeDB, HTTP also needs PHP/Ollama.
 * --fixture=PATH --filter=ID_PREFIX --http --routing --dry-run. Never starts servers.
 * Exit codes: 0 pass, 1 assertion failure, 2 setup error.
 */
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';
require_once __DIR__ . '/EvaluationAssertions.php';
require_once __DIR__ . '/../core/FAQMatcher.php';
$options = [];
foreach (array_slice($argv, 1) as $arg) {
    [$key, $value] = array_pad(explode('=', $arg, 2), 2, true);
    $options[$key] = $value;
}
try {
    $file = $options['--fixture'] ?? __DIR__ . '/contracts/chatbot_evaluation_cases_v8.json';
    $cases = json_decode((string)@file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    EvaluationAssertions::validateCases($cases);
    if (isset($options['--filter'])) $cases = array_values(array_filter($cases, static function ($c) use ($options) { return strpos($c['id'], $options['--filter']) === 0; }));
    if (!$cases) throw new InvalidArgumentException('Filter matched zero cases');
} catch (Throwable $e) { fwrite(STDERR, 'SETUP ERROR: ' . $e->getMessage() . "\n"); exit(2); }

function evaluateHttp(array $case): array {
    $url = getenv('CHATBOT_EVAL_URL') ?: 'http://127.0.0.1:8081/api/chatbot_response.php';
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => json_encode(['question' => $case['question'], 'history' => $case['history'] ?? []]), 'ignore_errors' => true, 'timeout' => 180]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false || !preg_match('/\s200\s/', $http_response_header[0] ?? '')) return ['status' => 'http_error'];
    return json_decode($body, true) ?: ['status' => 'http_error'];
}

$pass = 0; $fail = 0;
foreach ($cases as $case) {
    try {
        if (isset($options['--http'])) {
            $response = evaluateHttp($case);
            $actual = (array)($response['context'] ?? ['status' => $response['status'] ?? 'http_error']);
            if (isset($case['expected_faq_id'])) {
                // Educational answers have no retrieval context/status: validate their
                // explicit top-level status and ensure they never acquire KG citations.
                $actual['status'] = $response['status'] ?? 'http_error';
                if (!empty($response['citations']) || !empty($response['evidence_records'])) throw new RuntimeException('FAQ fabricated KG evidence');
            }
            $why = EvaluationAssertions::compare($case, $actual);
            $wantFinal = $case['expected_final_status'] ?? $case['expected_status'];
            if (($response['status'] ?? null) !== $wantFinal) $why[] = 'final status: expected ' . $wantFinal . ', got ' . ($response['status'] ?? 'missing');
        } else {
            // FAQ matching precedes graph orchestration in the real HTTP entrypoint.
            $faq = FAQMatcher::find($case['question']);
            $actual = $faq !== null ? ['status' => 'success', 'type' => 'faq_static_response', 'faq' => $faq]
                : (isset($options['--routing']) ? QuestionRouter::route($case['question'], $case['history'] ?? []) : ChatbotOrchestrator::process($case['question'], $case['history'] ?? []));
            $why = EvaluationAssertions::compare($case, $actual, isset($options['--routing']));
        }
        if (isset($options['--dry-run'])) { echo json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"; exit(0); }
    } catch (Throwable $e) { $why = ['execution_error: ' . $e->getMessage()]; }
    if ($why) { $fail++; echo 'FAIL ' . $case['id'] . ': ' . implode('; ', $why) . "\n"; }
    else { $pass++; echo 'PASS ' . $case['id'] . "\n"; }
}
echo json_encode(['total' => count($cases), 'passed' => $pass, 'failed' => $fail, 'skipped' => 0]) . "\n";
exit($fail ? 1 : 0);
