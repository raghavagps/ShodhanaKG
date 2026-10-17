<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_process_compound($method, $debugMode = false) {
    if ($method === '') { return ['status' => 'error', 'message' => 'Missing parameter']; }
    $debug = ['entity_resolution' => [], 'sql_queries' => [], 'raw_records' => 0, 'duplicates_filtered' => 0, 'final_count' => 0];

    try {
        $entityNode = EntityResolver::resolve('Shodhana_Method', $method, $debug);
        $entity = ['name' => isset($entityNode['name']) ? $entityNode['name'] : $method, 'type' => 'Shodhana_Method'];
        if (!$entityNode) { return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity]; }
        
        $entity['_evidence'] = new LegacyEvidence($entityNode);
        
        $methodRid = $entityNode['rid'];
        // Read the complete edge so property aliases and stored procedures survive.
        $sql = "SELECT FROM `UNDERGOES` WHERE @in = :method_rid";
        $edges = GraphRetriever::execute($sql, ['method_rid' => $methodRid], $debug);

        if (empty($edges)) return KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        
        $targetRids = array_unique(array_column($edges, '@out'));
        $targetNameMap = GraphRetriever::fetchNodeNames($targetRids, $debug, 'Compound');

        $uniqueResults = [];
        $rawCount = 0;
        foreach ($edges as $edge) {
            $rawCount++;
            $targetRid = $edge['@out'];
            $targetName = isset($targetNameMap[$targetRid]) ? $targetNameMap[$targetRid] : 'Unknown Compound';
            $props = EvidenceCorrelator::extractIdentifiers($edge);
            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey('UNDERGOES', $targetName, $props);
            
            if (!isset($uniqueResults[$dedupKey])) {
                $uniqueResults[$dedupKey] = ['compound' => $targetName, 'properties' => $props];
            }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$edge]);
        }
        
        $debug['raw_records'] = $rawCount;
        $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
        $debug['final_count'] = count($uniqueResults);
        return KnowledgeNormalizer::formatCanonical($entity, ['Shodhana_Method', '<- UNDERGOES', 'Compound'], $uniqueResults, $debugMode ? $debug : null);
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage(), 'debug' => $debug];
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $method = isset($_GET['method']) ? trim($_GET['method']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    if ($method === '') { echo json_encode(['status' => 'error', 'message' => 'Missing parameter']); exit; }
    echo json_encode(execute_process_compound($method, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
