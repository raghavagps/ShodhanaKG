<?php
/** Live endpoint facts, deliberately bypassing routing; never starts services. */
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';
require_once __DIR__ . '/EvaluationAssertions.php';
if ($argc !== 3 || file_exists($argv[2])) { fwrite(STDERR, "Usage: php run_expanded_factual.php FIXTURE NEW_REPORT\n"); exit(2); }
$cases = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
EvaluationAssertions::validateCases($cases);
foreach ($cases as $case) {
    $source = $case['ground_truth_source'] ?? [];
    $snapshot = __DIR__ . '/fixtures/' . basename($source['snapshot'] ?? '');
    if (!is_file($snapshot) || hash_file('sha256', $snapshot) !== ($source['sha256'] ?? null)) throw new RuntimeException('Snapshot/reference hash mismatch');
}
$registryProperty = (new ReflectionClass(ChatbotOrchestrator::class))->getProperty('allowlist');
$registryProperty->setAccessible(true);
$registry = $registryProperty->getValue();
$report = ['mode' => 'direct registered endpoint; not a routing or answer-quality test', 'captured_at' => gmdate('c'),
    'fixture_sha256' => hash_file('sha256', $argv[1]), 'cases' => [], 'endpoint_source_sha256' => []];
$file = fopen($argv[2], 'x');
if (!$file) exit(2);
foreach ($cases as $case) {
    $start = microtime(true); $endpoint = $case['expected_endpoint'];
    try {
        if (!isset($registry[$endpoint])) throw new RuntimeException('Unregistered endpoint');
        require_once __DIR__ . '/../' . $endpoint . '.php';
        $report['endpoint_source_sha256'][$endpoint] = hash_file('sha256', __DIR__ . '/../' . $endpoint . '.php');
        $actual = ($registry[$endpoint])($case['expected_entity'], false);
        $context = ['status' => $actual['status'] ?? 'error', 'retrieval' => $actual,
            'routing' => ['endpoint' => $endpoint, 'entity' => $actual['entity']['name'] ?? null, 'entity_type' => $actual['entity']['type'] ?? null]];
        $failures = EvaluationAssertions::compare($case, $context);
        $used = [];
        foreach ($actual['results'] ?? [] as $i => $row) {
            $match = null;
            foreach ($case['expected_results'] as $j => $expected) if (!isset($used[$j]) && EvaluationAssertions::contains($row, $expected)) { $match = $j; break; }
            if ($match === null) { $failures[] = 'Unexpected/duplicate row at index ' . $i; continue; }
            $used[$match] = true;
            if (isset($case['expected_safety_candidates'])) {
                $candidates = $case['expected_safety_candidates'][$match];
                $safety = $row['safety_evidence'] ?? null;
                $supported = !$candidates && $safety === null;
                foreach ($candidates as $candidate) if (EvaluationAssertions::contains($safety, $candidate)) $supported = true;
                if (!$supported) $failures[] = 'Missing/unsupported selected safety evidence at index ' . $i;
            }
        }
    } catch (Throwable $error) { $actual = ['status' => 'execution_error']; $failures = [$error->getMessage()]; }
    $report['cases'][] = ['id' => $case['id'], 'endpoint' => $endpoint, 'entity' => $case['expected_entity'],
        'expected_status' => $case['expected_status'], 'expected_result_count' => $case['expected_result_count'],
        'passed' => !$failures, 'failures' => $failures, 'actual' => $actual,
        'latency_ms' => (int)round((microtime(true) - $start) * 1000)];
    echo ($failures ? 'FAIL ' : 'PASS ') . $case['id'] . ($failures ? ' (' . count($failures) . ' findings)' : '') . "\n";
    $report['summary'] = ['total' => count($report['cases']), 'passed' => count(array_filter($report['cases'], static function ($r) { return $r['passed']; }))];
    $report['summary']['failed'] = $report['summary']['total'] - $report['summary']['passed'];
    rewind($file); fwrite($file, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ftruncate($file, ftell($file)); fflush($file);
}
fclose($file);
echo json_encode($report['summary']) . "\n";
exit($report['summary']['failed'] ? 1 : 0);
