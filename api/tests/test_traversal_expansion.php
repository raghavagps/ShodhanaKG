<?php
require_once __DIR__ . '/../core/EvidenceTraversal.php';
require_once __DIR__ . '/../core/QuestionRouter.php';
require_once __DIR__ . '/../core/LegacyProvenance.php';

$checks = 0;
function check($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}

function fixtureEngine(array $nodes, array $edges, bool $fail = false): EvidenceTraversal {
    $query = static function ($sql, $params) use ($nodes, $edges, $fail) {
        if ($fail) throw new RuntimeException('Database offline');
        if (preg_match('/^SELECT FROM (#\d+:\d+)$/D', $sql, $m)) return isset($nodes[$m[1]]) ? [$nodes[$m[1]]] : [];
        if (preg_match('/^SELECT FROM `([A-Z_]+)` WHERE (@out|@in) = :rid$/D', $sql, $m)) {
            return array_values(array_filter($edges, static function ($e) use ($m, $params) { return $e['@type'] === $m[1] && $e[$m[2]] === $params['rid']; }));
        }
        throw new RuntimeException('Unexpected SQL: ' . $sql);
    };
    $resolve = static function ($type, $name) use ($nodes) {
        foreach ($nodes as $node) if ($node['@type'] === $type && $node['name'] === $name) return $node;
        return null;
    };
    return new EvidenceTraversal($query, $resolve);
}

// Independent contract expectations: do not derive fixtures from production definitions.
$expectedPaths = [
    'therapy_formulation' => ['Therapy', [['USES_FORMULATION', 'out', 'Formulation']]],
    'media_process' => ['Media', [['USES_MEDIA', 'in', 'Shodhana_Method']]],
    'shodhana_method_process' => ['Shodhana_Method', [['FOLLOWED_BY', 'out', 'Process']]],
    'process_shodhana_method' => ['Process', [['FOLLOWED_BY', 'in', 'Shodhana_Method']]],
    'purified_compound_process' => ['Purified_Compound', [['PRODUCES', 'in', 'Shodhana_Method']]],
    'method_toxic_substance' => ['Shodhana_Method', [['DETOXIFIES', 'out', 'Toxic_Substance']]],
    'process_next_process' => ['Process', [['FOLLOWED_BY', 'out', 'Process']]],
    'next_process_process' => ['Process', [['FOLLOWED_BY', 'in', 'Process']]],
    'provenance_compound_method_media' => ['Compound', [['UNDERGOES', 'out', 'Shodhana_Method'], ['USES_MEDIA', 'out', 'Media']]],
    'provenance_media_method_compound' => ['Media', [['USES_MEDIA', 'in', 'Shodhana_Method'], ['UNDERGOES', 'in', 'Compound']]],
    'provenance_disease_therapy_formulation' => ['Disease', [['TREATED_BY', 'out', 'Therapy'], ['USES_FORMULATION', 'out', 'Formulation']]],
    'provenance_formulation_therapy_disease' => ['Formulation', [['USES_FORMULATION', 'in', 'Therapy'], ['TREATED_BY', 'in', 'Disease']]],
];
check(array_keys($expectedPaths) === array_keys(TraversalCatalog::all()), 'Every new endpoint has an independently specified contract');
foreach ($expectedPaths as $endpoint => [$input, $steps]) {
    $spec = ['input' => [$input], 'steps' => array_map(static function ($step) {
        return ['edges' => [$step[0]], 'direction' => $step[1], 'types' => [$step[2]]];
    }, $steps)];
    $nodes = ['#1:0' => ['@rid' => '#1:0', '@type' => $spec['input'][0], 'name' => 'Seed']];
    $edges = [];
    foreach ($spec['steps'] as $i => $step) {
        $from = '#1:' . $i; $to = '#1:' . ($i + 1);
        $nodes[$to] = ['@rid' => $to, '@type' => $step['types'][0], 'name' => 'Target ' . $i];
        $edges[] = ['@rid' => '#2:' . $i, '@type' => $step['edges'][0], '@out' => $step['direction'] === 'out' ? $from : $to, '@in' => $step['direction'] === 'out' ? $to : $from, 'pubmedId' => '12345', 'Procedure' => 'edge ' . $i];
    }
    $good = fixtureEngine($nodes, $edges)->run($endpoint, 'Seed');
    check($good['status'] === 'success' && count($good['results']) === 1, "$endpoint positive");
    $chain = $good['results'][0];
    check(count(fixtureEngine($nodes, array_merge($edges, $edges))->run($endpoint, 'Seed')['results']) === 1, "$endpoint duplicate edges do not duplicate paths");
    if (in_array($endpoint, ['media_process', 'purified_compound_process'], true)) {
        $processNodes = $nodes; $processNodes['#1:1']['@type'] = 'Process';
        $processResult = fixtureEngine($processNodes, $edges)->run($endpoint, 'Seed');
        check($processResult['status'] === 'success' && $processResult['results'][0]['related_type'] === 'Process', "$endpoint also supports actual Process vertices");
    }
    check(count($chain['nodes']) === count($spec['steps']) + 1, "$endpoint complete nodes");
    check($chain['edges'][0]['source'] === $edges[0]['@out'], "$endpoint stored direction");
    check($chain['edges'][0]['properties']['Procedure'] === 'edge 0', "$endpoint edge properties");
    check(fixtureEngine($nodes, $edges)->run($endpoint, 'Missing')['status'] === 'entity_not_found', "$endpoint missing");
    check(fixtureEngine($nodes, [])->run($endpoint, 'Seed')['status'] === 'no_data', "$endpoint empty");
    check(fixtureEngine($nodes, $edges, true)->run($endpoint, 'Seed')['status'] === 'error', "$endpoint database failure");
    $wrong = $edges;
    [$wrong[0]['@out'], $wrong[0]['@in']] = [$wrong[0]['@in'], $wrong[0]['@out']];
    check(fixtureEngine($nodes, $wrong)->run($endpoint, 'Seed')['status'] === 'no_data', "$endpoint inverse excluded");
    $wrongNodes = $nodes; $wrongNodes['#1:1']['@type'] = 'Unrelated';
    check(fixtureEngine($wrongNodes, $edges)->run($endpoint, 'Seed')['status'] === 'no_data', "$endpoint wrong type excluded");
    if (count($edges) === 2) {
        $flat = LegacyProvenance::run($endpoint, 'Seed', false, fixtureEngine($nodes, $edges));
        check($flat['status'] === 'success' && count($flat['results']) === 1, "$endpoint legacy projection");
        check(isset($flat['results'][0]['properties']['pubmed_id']) && $flat['results'][0]['properties']['pubmed_id'] === '12345', "$endpoint legacy evidence preserved");
        $phaseIndex = strpos($endpoint, 'disease') !== false ? ($edges[0]['@type'] === 'USES_FORMULATION' ? 1 : 0) : null;
        if ($phaseIndex !== null) {
            $phases = $edges;
            foreach (['PRE_TREATED_BY', 'POST_TREATED_BY'] as $i => $phase) {
                $extra = $edges[$phaseIndex]; $extra['@type'] = $phase; $extra['@rid'] = '#3:' . $i; $phases[] = $extra;
            }
            $r = fixtureEngine($nodes, $phases)->run($endpoint, 'Seed');
            check(count(array_unique(array_column($r['results'], 'phase'))) === 3, "$endpoint preserves all treatment phases");
        }
        foreach ([['pubmedId' => '999'], [], ['doi' => '10.1234/test']] as $badEvidence) {
            $bad = $edges; unset($bad[1]['pubmedId']); $bad[1] = array_merge($bad[1], $badEvidence);
            $r = fixtureEngine($nodes, $bad)->run($endpoint, 'Seed');
            check($r['status'] === 'no_data' && $r['reason'] === 'no_correlated_evidence', "$endpoint rejects cross-study path");
        }
        foreach (['DOI' => '10.1234/test', 'Ayush_ID' => 'ARP_AYU1', 'Pubmed ID' => '12345'] as $field => $value) {
            $same = $edges;
            foreach ($same as &$e) { unset($e['pubmedId']); $e[$field] = $value; } unset($e);
            check(fixtureEngine($nodes, $same)->run($endpoint, 'Seed')['status'] === 'success', "$endpoint $field alias");
        }
        $studies = $edges;
        foreach ($edges as $e) { $e['@rid'] .= '0'; $e['pubmedId'] = '67890'; $studies[] = $e; }
        $r = fixtureEngine($nodes, $studies)->run($endpoint, 'Seed');
        check(count($r['results']) === 2, "$endpoint preserves studies, excludes Cartesian joins");
        $reverse = [
            'provenance_compound_method_media' => 'provenance_media_method_compound',
            'provenance_disease_therapy_formulation' => 'provenance_formulation_therapy_disease',
        ][$endpoint] ?? null;
        if ($reverse) {
            $r = fixtureEngine($nodes, $edges)->run($reverse, 'Target 1');
            check(array_reverse(array_column($r['results'][0]['edges'], 'id')) === array_column($chain['edges'], 'id'), "$endpoint reverse consistency");
        }
    }
}

$nodes = [
    '#1:0' => ['@rid' => '#1:0', '@type' => 'Process', 'name' => 'Seed'],
    '#1:1' => ['@rid' => '#1:1', '@type' => 'Process', 'name' => 'Next'],
    '#1:2' => ['@rid' => '#1:2', '@type' => 'Process', 'name' => 'Branch'],
];
$edges = [
    ['@rid' => '#2:0', '@type' => 'FOLLOWED_BY', '@out' => '#1:0', '@in' => '#1:1'],
    ['@rid' => '#2:1', '@type' => 'FOLLOWED_BY', '@out' => '#1:0', '@in' => '#1:2'],
    ['@rid' => '#2:2', '@type' => 'FOLLOWED_BY', '@out' => '#1:1', '@in' => '#1:0'],
];
check(count(fixtureEngine($nodes, $edges)->run('process_next_process', 'Seed')['results']) === 2, 'Immediate branching, no recursive cycle expansion');

$classify = new ReflectionMethod(QuestionRouter::class, 'classifyIntentContextually');
$mapping = new ReflectionMethod(QuestionRouter::class, 'getEndpointForIntent');
$questions = [
    'therapy_formulation' => ['Therapy', 'What formulations are used by Seed?'],
    'media_process' => ['Media', 'Which methods use Seed?'],
    'shodhana_method_process' => ['Shodhana_Method', 'What process follows Seed?'],
    'process_shodhana_method' => ['Process', 'Which method precedes Seed?'],
    'purified_compound_process' => ['Purified_Compound', 'Which process produces Seed?'],
    'method_toxic_substance' => ['Shodhana_Method', 'What toxic substances does Seed remove?'],
    'process_next_process' => ['Process', 'What process follows Seed?'],
    'next_process_process' => ['Process', 'What process precedes Seed?'],
    'provenance_compound_method_media' => ['Compound', 'What media are used for Seed with evidence?'],
    'provenance_media_method_compound' => ['Media', 'Which compounds use Seed in the same study?'],
    'provenance_disease_therapy_formulation' => ['Disease', 'What formulations treat Seed with evidence?'],
    'provenance_formulation_therapy_disease' => ['Formulation', 'Which diseases use Seed with evidence?'],
];
foreach ($questions as $expected => [$type, $question]) {
    $intent = $classify->invoke(null, strtolower($question), $type);
    check($intent === $expected && $mapping->invoke(null, $type, $intent) === $expected, "$expected routing");
    require_once __DIR__ . '/../' . $expected . '.php';
    check(is_callable('execute_' . $expected), "$expected callable");
}
check($classify->invoke(null, 'what symptoms does seed have with evidence?', 'Disease') === 'disease_symptoms', 'Evidence alone does not hijack symptoms');
check($classify->invoke(null, 'which compounds use seed?', 'Media') === 'media_compound', 'Preserve media-compound routing');
check($classify->invoke(null, 'which process is followed by seed?', 'Process') === 'next_process_process', 'Passive followed-by wording reverses direction');
check(!EvidenceCorrelator::correlate(['pubmed_id' => '1', 'doi' => 'same'], ['pubmed_id' => '2', 'doi' => 'same']), 'PMID conflict wins over DOI');
require_once __DIR__ . '/../shodhana_method_validation.php';
$nodes = [
    '#1:0' => ['@rid' => '#1:0', '@type' => 'Shodhana_Method', 'name' => 'Seed'],
    '#1:1' => ['@rid' => '#1:1', '@type' => 'Process', 'name' => 'Next'],
    '#1:2' => ['@rid' => '#1:2', '@type' => 'Confirmation_Study', 'name' => 'Study'],
];
$edges = [
    ['@rid' => '#2:0', '@type' => 'FOLLOWED_BY', '@out' => '#1:0', '@in' => '#1:1', 'pubmedId' => '123'],
    ['@rid' => '#2:1', '@type' => 'VALIDATED_BY', '@out' => '#1:1', '@in' => '#1:2', 'pubmedId' => '123'],
];
$result = execute_shodhana_method_validation('Seed', false, fixtureEngine($nodes, $edges));
check($result['status'] === 'success' && $result['results'][0]['process'] === 'Next', 'Method validation traverses FOLLOWED_BY forward');
$edges[1]['pubmedId'] = '999';
check(execute_shodhana_method_validation('Seed', false, fixtureEngine($nodes, $edges))['status'] === 'no_data', 'Method validation rejects unmatched downstream study');
$edges[] = ['@rid' => '#2:2', '@type' => 'VALIDATED_BY', '@out' => '#1:0', '@in' => '#1:2', 'pubmedId' => '456'];
check(execute_shodhana_method_validation('Seed', false, fixtureEngine($nodes, $edges))['results'][0]['method'] === 'Seed', 'Method validation includes direct study');
echo "PASS: $checks traversal, provenance, routing and contract assertions\n";
