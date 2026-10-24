<?php
/** Emit a read-only registry/coverage inventory. --verify-schema also reads ArcadeDB. */
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';
require_once __DIR__ . '/../core/TraversalCatalog.php';
$registry = (new ReflectionClass(ChatbotOrchestrator::class))->getStaticProperties()['allowlist'];
$definitions = TraversalCatalog::all();
$legacyContracts = json_decode(file_get_contents(__DIR__ . '/fixtures/legacy_traversal_contracts.json'), true, 512, JSON_THROW_ON_ERROR);
$cases = json_decode(file_get_contents(__DIR__ . '/fixtures/chatbot_evaluation_cases.json'), true);
$shared = [
    'provenance_compound_method_media' => 'compound_shodhana_process',
    'provenance_media_method_compound' => 'media_compound',
    'provenance_disease_therapy_formulation' => 'disease_formulation',
    'provenance_formulation_therapy_disease' => 'formulation_disease',
];
$entries = [];
$legacyDefinitions = array_flip($shared);
foreach ($registry as $endpoint => $function) {
    $source = file_get_contents(__DIR__ . '/../' . $endpoint . '.php');
    preg_match_all('/EntityResolver::resolve\(\s*\x27([^\x27]+)\x27/', $source, $types);
    preg_match_all('/\$path\s*=\s*(\[[^;]+\]);/', $source, $paths);
    if (!$paths[1]) preg_match_all('/formatCanonical\(\s*\$entity\s*,\s*(\[[^\]]+\])/', $source, $paths);
    $coverage = array_values(array_map(static function ($c) { return $c['id']; }, array_filter($cases, static function ($c) use ($endpoint) { return preg_replace('/\.php$/', '', $c['expected_endpoint'] ?? '') === $endpoint; })));
    require_once __DIR__ . '/../' . $endpoint . '.php';
    $entry = ['endpoint' => $endpoint, 'function' => $function, 'callable' => is_callable($function), 'implementation' => 'api/' . $endpoint . '.php', 'alias_of' => null, 'routing_cases' => $coverage];
    $definitionName = isset($definitions[$endpoint]) ? $endpoint : ($legacyDefinitions[$endpoint] ?? null);
    if ($definitionName !== null) {
        $entry['definition'] = $definitions[$definitionName];
        $entry['evidence_correlation'] = count($definitions[$definitionName]['steps']) > 1;
        $entry['retrieval_tests'] = ['api/tests/test_traversal_expansion.php'];
    } else {
        $entry['contract'] = $legacyContracts[$endpoint] ?? null;
        $entry['input_types_from_source'] = array_values(array_unique($types[1]));
        $entry['declared_paths_from_source'] = $paths[1];
        $entry['retrieval_tests'] = array_values(array_map(static function ($path) { return 'api/tests/' . basename($path); }, glob(__DIR__ . '/test_' . $endpoint . '.php')));
        $entry['schema_verification'] = 'Existing contract; source extraction is not independent schema verification';
    }
    if (isset($shared[$endpoint])) $entry['shares_traversal_with'] = $shared[$endpoint];
    $entries[] = $entry;
}
$snapshot = [];
$schemaFailures = [];
if (in_array('--verify-schema', $argv, true)) {
    $edgeTypes = [];
    foreach ($definitions as $spec) foreach ($spec['steps'] as $step) foreach ($step['edges'] as $edge) $edgeTypes[$edge] = true;
    foreach (array_keys($edgeTypes) as $edge) {
        try {
            $snapshot[$edge] = arcade_command('SELECT @out.@type as sourceType, @in.@type as targetType, count(*) as total FROM `' . $edge . '` GROUP BY sourceType, targetType')['result'];
        } catch (Throwable $e) { $schemaFailures[] = $edge . ': schema query failed'; }
    }
    foreach ($definitions as $name => $spec) {
        $sources = $spec['input'];
        foreach ($spec['steps'] as $step) {
            foreach ($sources as $source) foreach ($step['types'] as $target) foreach ($step['edges'] as $edge) {
                $out = $step['direction'] === 'out' ? $source : $target;
                $in = $step['direction'] === 'out' ? $target : $source;
                $found = false;
                foreach ($snapshot[$edge] ?? [] as $record) if ($record['sourceType'] === $out && $record['targetType'] === $in) $found = true;
                if (!$found) $schemaFailures[] = "$name: unverified $out -> $edge -> $in";
            }
            $sources = $step['types'];
        }
    }
}
$missing = array_column(array_filter($entries, static function ($e) { return !$e['callable'] || !$e['routing_cases'] || (!isset($e['definition']) && !isset($e['contract'])); }), 'endpoint');
echo json_encode(['generated_at' => gmdate('c'), 'public_endpoints' => count($entries), 'new_endpoints' => count($definitions), 'aliases' => 0, 'shared_traversal_pairs' => $shared, 'coverage_missing' => $missing, 'schema_failures' => $schemaFailures, 'schema_snapshot' => $snapshot, 'entries' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit($missing || $schemaFailures ? 1 : 0);
