<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_compound_media($compound, $debugMode = false) {
if ($compound === '') { return ['status' => 'error', 'message' => 'Missing compound parameter']; }

$debug = [
    'entity_resolution' => [],
    'sql_queries' => [],
    'raw_records' => 0,
    'duplicates_filtered' => 0,
    'final_count' => 0
];

try {
    // 1. Entity Resolution
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

    // 2. Traversal Layer 1: Compound -> UNDERGOES -> Shodhana_Method
    $sqlUndergoes = "SELECT @rid as edge_rid, @in as method_rid, procedure, Procedure, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `UNDERGOES` WHERE @out = :compound_rid";
    $undergoesEdges = GraphRetriever::execute($sqlUndergoes, ['compound_rid' => $compoundRid], $debug);

    if (empty($undergoesEdges)) {
        $response = KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        return $response;
    }
    
    $methodRids = array_unique(array_column($undergoesEdges, 'method_rid'));
    $methodNameMap = GraphRetriever::fetchNodeNames($methodRids, $debug, 'Shodhana_Method');

    $uniqueResults = [];
    $rawCount = 0;
    
    // Layer 2: Shodhana_Method -> USES_MEDIA -> Media
    foreach ($undergoesEdges as $methodEdge) {
        $methodRid = $methodEdge['method_rid'];
        $methodName = isset($methodNameMap[$methodRid]) ? $methodNameMap[$methodRid] : 'Unknown Method';
        
        $mProps = EvidenceCorrelator::extractIdentifiers($methodEdge);
        
        // Fetch USES_MEDIA edges
        $sqlMedia = "SELECT @rid as edge_rid, @in as media_rid, procedure, Procedure, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId, result, Result FROM `USES_MEDIA` WHERE @out = :method_rid";
        $mediaEdges = GraphRetriever::execute($sqlMedia, ['method_rid' => $methodRid], $debug);
        
        foreach ($mediaEdges as $mediaEdge) {
            $rawCount++;
            $meProps = EvidenceCorrelator::extractIdentifiers($mediaEdge);
            
            // DEDUPLICATION / GRAPH SEMANTIC FIX
            if (!EvidenceCorrelator::correlate($mProps, $meProps)) {
                continue;
            }
            
            // Get Media Name
            $mediaNameMap = GraphRetriever::fetchNodeNames([$mediaEdge['media_rid']], $debug, 'Media');
            $mediaName = isset($mediaNameMap[$mediaEdge['media_rid']]) ? $mediaNameMap[$mediaEdge['media_rid']] : 'Unknown Media';
            
            // Merge properties (USES_MEDIA procedure overrides UNDERGOES procedure)
            $finalProps = $meProps;
            if (empty($finalProps['procedure']) && !empty($mProps['procedure'])) {
                $finalProps['procedure'] = $mProps['procedure'];
            }
            if (empty($finalProps['result']) && !empty($mProps['result'])) {
                $finalProps['result'] = $mProps['result'];
            }
            
            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey($methodName, $mediaName, $finalProps);
            
            if (!isset($uniqueResults[$dedupKey])) {
                $uniqueResults[$dedupKey] = [
                    'method' => $methodName,
                    'media' => $mediaName,
                    'properties' => $finalProps
                ];
            }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$methodEdge, $mediaEdge]);
        }
    }
    
    $debug['raw_records'] = $rawCount;
    $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
    $debug['final_count'] = count($uniqueResults);
    
    $path = ['Compound', 'UNDERGOES', 'Shodhana_Method', 'USES_MEDIA', 'Media'];
    $response = KnowledgeNormalizer::formatCanonical($entity, $path, $uniqueResults, $debugMode ? $debug : null);

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
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
    
    echo json_encode(execute_compound_media($compound, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
