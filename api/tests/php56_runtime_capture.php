<?php
/** PHP 5.6-compatible capture only; existing independent evaluators judge output. */
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';
require_once __DIR__ . '/../core/ResponseGenerator.php';
if ($argc !== 3 || file_exists($argv[2])) { fwrite(STDERR, "Usage: php php56_runtime_capture.php FIXTURE NEW_REPORT\n"); exit(2); }
$cases = PhpCompatibility::jsonDecode(file_get_contents($argv[1]), true);
$property = new ReflectionProperty('ChatbotOrchestrator', 'allowlist');
$property->setAccessible(true);
$registry = $property->getValue();
$rows = [];
foreach ($cases as $case) {
    $start = microtime(true);
    $endpoint = $case['expected_endpoint'];
    require_once __DIR__ . '/../' . $endpoint . '.php';
    if (!isset($registry[$endpoint]) || !is_callable($registry[$endpoint])) throw new RuntimeException('Unregistered endpoint');
    $actual = call_user_func($registry[$endpoint], $case['expected_entity'], false);
    $rows[] = ['id' => $case['id'], 'endpoint' => $endpoint, 'actual' => $actual,
        'latency_ms' => (int)round((microtime(true) - $start) * 1000)];
}
file_put_contents($argv[2], PhpCompatibility::jsonEncode(['php_version' => PHP_VERSION,
    'fixture_sha256' => hash_file('sha256', $argv[1]), 'cases' => $rows], JSON_PRETTY_PRINT));
echo 'Captured ' . count($rows) . " factual cases on PHP " . PHP_VERSION . "\n";
