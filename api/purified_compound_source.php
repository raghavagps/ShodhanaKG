<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_purified_compound_source($purified, $debugMode = false) {
    if ($purified === '') { return ['status' => 'error', 'message' => 'Missing parameter']; }

    $debug = [
        'entity_resolution' => [],
        'sql_queries' => [],
        'raw_records' => 0,
        'duplicates_filtered' => 0,
        'final_count' => 0
    ];

    try {
        $entityNode = EntityResolver::resolve('Purified_Compound', $purified, $debug);
        $entity = ['name' => isset($entityNode['name']) ? $entityNode['name'] : $purified, 'type' => 'Purified_Compound'];
        if (!$entityNode) { return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity]; }
        
        $entity['_evidence'] = new LegacyEvidence($entityNode);
        
        $purifiedRid = $entityNode['rid'];

        // Purified_Compound <-[PRODUCES]- Shodhana_Method
        $sqlProduces = "SELECT @rid as edge_rid, @out as shodhana_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `PRODUCES` WHERE @in = :purified_rid";
        $producesEdges = GraphRetriever::execute($sqlProduces, ['purified_rid' => $purifiedRid], $debug);

        if (empty($producesEdges)) return KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        
        $shodhanaRids = array_unique(array_column($producesEdges, 'shodhana_rid'));
        $shodhanaNameMap = GraphRetriever::fetchNodeNames($shodhanaRids, $debug, 'Shodhana_Method');

        $uniqueResults = [];
        $rawCount = 0;
        
        // Shodhana_Method <-[UNDERGOES]- Compound
        foreach ($producesEdges as $pEdge) {
            $shodhanaRid = $pEdge['shodhana_rid'];
            $shodhanaName = isset($shodhanaNameMap[$shodhanaRid]) ? $shodhanaNameMap[$shodhanaRid] : 'Unknown Method';
            
            $pProps = EvidenceCorrelator::extractIdentifiers($pEdge);
            
            $sqlUndergoes = "SELECT @rid as edge_rid, @out as compound_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId, procedure FROM `UNDERGOES` WHERE @in = :shodhana_rid";
            $undergoesEdges = GraphRetriever::execute($sqlUndergoes, ['shodhana_rid' => $shodhanaRid], $debug);
            
            foreach ($undergoesEdges as $uEdge) {
                $uProps = EvidenceCorrelator::extractIdentifiers($uEdge);
                
                if (!EvidenceCorrelator::correlate($pProps, $uProps)) {
                    continue; 
                }
                
                $rawCount++;
                $compoundRid = $uEdge['compound_rid'];
                
                $compoundRids = [$compoundRid];
                $compoundNameMap = GraphRetriever::fetchNodeNames($compoundRids, $debug, 'Compound');
                $compoundName = isset($compoundNameMap[$compoundRid]) ? $compoundNameMap[$compoundRid] : 'Unknown Compound';
                
                $finalProps = $uProps;
                if (isset($uEdge['procedure'])) {
                    $finalProps['procedure'] = $uEdge['procedure'];
                }
                
                $dedupKey = KnowledgeNormalizer::generateDeduplicationKey($shodhanaName, $compoundName, $finalProps);
                
                if (!isset($uniqueResults[$dedupKey])) {
                    $uniqueResults[$dedupKey] = [
                        'shodhana_method' => $shodhanaName,
                        'compound' => $compoundName,
                        'properties' => $finalProps
                    ];
                }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$pEdge, $uEdge]);
            }
        }
        
        $debug['raw_records'] = $rawCount;
        $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
        $debug['final_count'] = count($uniqueResults);
        
        $path = ['Purified_Compound', '<- PRODUCES', 'Shodhana_Method', '<- UNDERGOES', 'Compound'];
        return KnowledgeNormalizer::formatCanonical($entity, $path, $uniqueResults, $debugMode ? $debug : null);

    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage(), 'debug' => $debug];
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $purified = isset($_GET['purified']) ? trim($_GET['purified']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    if ($purified === '') { echo json_encode(['status' => 'error', 'message' => 'Missing parameter']); exit; }
    echo json_encode(execute_purified_compound_source($purified, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
