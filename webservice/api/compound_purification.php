<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_compound_purification($compound, $debugMode = false) {
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

    // Layer 1: Compound -> UNDERGOES -> Shodhana_Method
    $sqlUndergoes = "SELECT @rid as edge_rid, @in as shodhana_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId, procedure FROM `UNDERGOES` WHERE @out = :compound_rid";
    $undergoesEdges = GraphRetriever::execute($sqlUndergoes, ['compound_rid' => $compoundRid], $debug);

    if (empty($undergoesEdges)) {
        $response = KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        return $response;
    }
    
    $shodhanaRids = array_unique(array_column($undergoesEdges, 'shodhana_rid'));
    $shodhanaNameMap = GraphRetriever::fetchNodeNames($shodhanaRids, $debug, 'Shodhana_Method');

    $uniqueResults = [];
    $rawCount = 0;
    
    // Layer 2: Shodhana_Method -> PRODUCES -> Purified_Compound
    foreach ($undergoesEdges as $uEdge) {
        $shodhanaRid = $uEdge['shodhana_rid'];
        $shodhanaName = isset($shodhanaNameMap[$shodhanaRid]) ? $shodhanaNameMap[$shodhanaRid] : 'Unknown Method';
        
        $uProps = EvidenceCorrelator::extractIdentifiers($uEdge);
        
        $sqlProduces = "SELECT @rid as edge_rid, @in as purified_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `PRODUCES` WHERE @out = :shodhana_rid";
        $producesEdges = GraphRetriever::execute($sqlProduces, ['shodhana_rid' => $shodhanaRid], $debug);
        
        foreach ($producesEdges as $pEdge) {
            $rawCount++;
            $pProps = EvidenceCorrelator::extractIdentifiers($pEdge);
            
            // DEDUPLICATION / GRAPH SEMANTIC FIX
            if (!EvidenceCorrelator::correlate($uProps, $pProps)) {
                continue; // Prevent cross-join contamination
            }
            
            $purifiedRids = [$pEdge['purified_rid']];
            $purifiedNameMap = GraphRetriever::fetchNodeNames($purifiedRids, $debug, 'Purified_Compound');
            $purifiedName = isset($purifiedNameMap[$pEdge['purified_rid']]) ? $purifiedNameMap[$pEdge['purified_rid']] : 'Unknown Purified Compound';
            
            // Final properties inherit from both edges, focusing on evidence and procedure
            $finalProps = $pProps;
            if (isset($uEdge['procedure'])) {
                $finalProps['procedure'] = $uEdge['procedure'];
            }
            
            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey($shodhanaName, $purifiedName, $finalProps);
            
            if (!isset($uniqueResults[$dedupKey])) {
                $uniqueResults[$dedupKey] = [
                    'shodhana_method' => $shodhanaName,
                    'purified_compound' => $purifiedName,
                    'properties' => $finalProps
                ];
            }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$uEdge, $pEdge]);
        }
    }
    
    $debug['raw_records'] = $rawCount;
    $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
    $debug['final_count'] = count($uniqueResults);
    
    $path = ['Compound', 'UNDERGOES', 'Shodhana_Method', 'PRODUCES', 'Purified_Compound'];
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
    
    echo json_encode(execute_compound_purification($compound, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
