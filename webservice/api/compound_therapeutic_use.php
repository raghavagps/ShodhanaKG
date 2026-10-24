<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_compound_therapeutic_use($compound, $debugMode = false) {
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
            $pProps = EvidenceCorrelator::extractIdentifiers($pEdge);
            
            // DEDUPLICATION / GRAPH SEMANTIC FIX
            if (!EvidenceCorrelator::correlate($uProps, $pProps)) {
                continue; // Prevent cross-join contamination for Purified Compound
            }
            
            $purifiedRid = $pEdge['purified_rid'];
            
            // Layer 3: Purified_Compound -> TREATS -> Indication
            $sqlTreats = "SELECT @rid as edge_rid, @in as indication_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `TREATS` WHERE @out = :purified_rid";
            $treatsEdges = GraphRetriever::execute($sqlTreats, ['purified_rid' => $purifiedRid], $debug);
            
            foreach ($treatsEdges as $tEdge) {
                $rawCount++;
                
                $indicationRids = [$tEdge['indication_rid']];
                $indicationNameMap = GraphRetriever::fetchNodeNames($indicationRids, $debug, 'Indication');
                $indicationName = isset($indicationNameMap[$tEdge['indication_rid']]) ? $indicationNameMap[$tEdge['indication_rid']] : 'Unknown Indication';
                
                // TREATS relation represents a distinct study.
                // We extract its properties instead of inheriting the Shodhana study properties.
                $tProps = EvidenceCorrelator::extractIdentifiers($tEdge);
                
                // Keep procedure from shodhana for context, but use TREATS evidence as primary
                $finalProps = $tProps;
                if (isset($uEdge['procedure'])) {
                    $finalProps['procedure'] = $uEdge['procedure'];
                }
                
                $purifiedNameMap = GraphRetriever::fetchNodeNames([$purifiedRid], $debug, 'Purified_Compound');
                $purifiedName = isset($purifiedNameMap[$purifiedRid]) ? $purifiedNameMap[$purifiedRid] : 'Unknown Purified Compound';
                
                // All returned entities belong to the row identity. Distinct methods
                // must never reuse a row that names a different supporting method.
                $dedupKey = KnowledgeNormalizer::generateDeduplicationKey(
                    $shodhanaRid, $purifiedRid . '|' . $tEdge['indication_rid'], $finalProps);
                
                if (!isset($uniqueResults[$dedupKey])) {
                    $uniqueResults[$dedupKey] = [
                        'shodhana_method' => $shodhanaName,
                        'purified_compound' => $purifiedName,
                        'indication' => $indicationName,
                        'properties' => $finalProps
                    ];
                }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$uEdge, $pEdge, $tEdge], 'cross_study_association');
            }
        }
    }
    
    $debug['raw_records'] = $rawCount;
    $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
    $debug['final_count'] = count($uniqueResults);
    
    $path = ['Compound', 'UNDERGOES', 'Shodhana_Method', 'PRODUCES', 'Purified_Compound', 'TREATS', 'Indication'];
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
    
    echo json_encode(execute_compound_therapeutic_use($compound, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
