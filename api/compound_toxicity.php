<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_compound_toxicity($compound, $debugMode = false) {
if ($compound === '') { return ['status' => 'error', 'message' => 'Missing compound parameter']; }

$debug = [
    'entity_resolution' => [],
    'sql_queries' => [],
    'raw_records' => 0,
    'duplicates_filtered' => 0,
    'final_count' => 0
];

try {
    $entityNode = EntityResolver::resolve('Compound', $compound, $debug);
    
    $entity = [
        'name' => isset($entityNode['name']) ? $entityNode['name'] : $compound,
        'type' => 'Compound'
    ];
    
    if (!$entityNode) {
        return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity];
    }
    
    $entity['_evidence'] = new LegacyEvidence($entityNode);
    
    $compoundRid = $entityNode['rid'];

    // Single hop: Compound -> CONTAINS -> Toxic_Substance
    $sql = "SELECT @rid as edge_rid, @in as toxic_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `CONTAINS` WHERE @out = :compound_rid";
    $edges = GraphRetriever::execute($sql, ['compound_rid' => $compoundRid], $debug);

    if (empty($edges)) {
        $response = KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        return $response;
    }
    
    $toxicRids = array_unique(array_column($edges, 'toxic_rid'));
    $toxicNameMap = GraphRetriever::fetchNodeNames($toxicRids, $debug, 'Toxic_Substance');

    $uniqueResults = [];
    $rawCount = 0;
    
    foreach ($edges as $edge) {
        $rawCount++;
        $toxicRid = $edge['toxic_rid'];
        $toxicName = isset($toxicNameMap[$toxicRid]) ? $toxicNameMap[$toxicRid] : 'Unknown Toxic Substance';
        
        $props = EvidenceCorrelator::extractIdentifiers($edge);
        
        $dedupKey = KnowledgeNormalizer::generateDeduplicationKey('CONTAINS', $toxicName, $props);
        
        if (!isset($uniqueResults[$dedupKey])) {
            $uniqueResults[$dedupKey] = [
                'toxic_substance' => $toxicName,
                'properties' => $props
            ];
        }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$edge]);
    }
    
    $debug['raw_records'] = $rawCount;
    $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
    $debug['final_count'] = count($uniqueResults);
    
    $path = ['Compound', 'CONTAINS', 'Toxic_Substance'];
    $response = KnowledgeNormalizer::formatCanonical($entity, $path, $uniqueResults, $debugMode ? $debug : null);

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => $debug
    ];
}

return $response;
}


if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $compound = isset($_GET['compound']) ? trim($_GET['compound']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    
    if ($compound === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing compound parameter']);
        exit;
    }
    
    echo json_encode(execute_compound_toxicity($compound, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
