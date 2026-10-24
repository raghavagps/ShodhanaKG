<?php

require_once __DIR__ . '/core/PhpCompatibility.php';
require_once __DIR__ . '/core/EvidenceTraversal.php';
function execute_shodhana_method_validation($method, $debugMode = false, $engine = null)
{
    $engine = PhpCompatibility::value($engine, 'EvidenceTraversal', true);
    $input = ['Shodhana_Method'];
    $validation = ['edges' => ['VALIDATED_BY'], 'direction' => 'out', 'types' => ['Confirmation_Study']];
    $following = ['edges' => ['FOLLOWED_BY'], 'direction' => 'out', 'types' => ['Process']];
    $engine = (isset($engine) ? $engine : new EvidenceTraversal());
    $direct = $engine->runDefinition(['input' => $input, 'steps' => [$validation]], $method, $debugMode);
    if (in_array($direct['status'], ['error', 'entity_not_found'], true)) {
        return $direct;
    }
    $indirect = $engine->runDefinition(['input' => $input, 'steps' => [$following, $validation]], $method, $debugMode);
    if ($indirect['status'] === 'error') {
        return $indirect;
    }
    $results = [];
    $evidence = new LegacyEvidence();
    foreach (array_merge($direct['results'], $indirect['results']) as $chain) {
        $last = count($chain['nodes']) - 1;
        $row = ['study' => $chain['nodes'][$last]['label'], 'properties' => $chain['edges'][$last - 1]['evidence']];
        $row[($last === 1 ? 'method' : 'process')] = $chain['nodes'][$last - 1]['label'];
        $evidence->recordPath($row, $chain);
        $results[KnowledgeNormalizer::generateDeduplicationKey($chain['nodes'][$last - 1]['id'], $chain['nodes'][$last]['id'], $row['properties'])] = $row;
    }
    $response = KnowledgeNormalizer::formatCanonical($direct['entity'], ['Shodhana_Method', '[FOLLOWED_BY -> Process]', 'VALIDATED_BY', 'Confirmation_Study'], $results);
    if (!$results && isset($indirect['reason'])) {
        $response['reason'] = $indirect['reason'];
    }
    return $evidence->attach($response);
}
