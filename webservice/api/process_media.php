<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_process_media($processName, $debugMode = false) {
    if ($processName === '') { return ['status' => 'error', 'message' => 'Missing process parameter']; }

    $debug = [
        'entity_resolution' => [],
        'sql_queries' => [],
        'raw_records' => 0,
        'duplicates_filtered' => 0,
        'final_count' => 0
    ];

    try {
        $entityNode = EntityResolver::resolve('Shodhana_Method', $processName, $debug);
        
        $entity = [
            'name' => isset($entityNode['name']) ? $entityNode['name'] : $processName,
            'type' => 'Shodhana_Method'
        ];
        
        if (!$entityNode) {
        return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity];
    }
        
        $entity['_evidence'] = new LegacyEvidence($entityNode);
        
        $methodRid = $entityNode['rid'];
        $methodName = $entity['name'];

        $uniqueResults = [];
        $rawCount = 0;
        
        // Layer 1: Shodhana_Method -> USES_MEDIA -> Media
        $processRid = $entityNode['rid'];
    
        $sql = "SELECT @rid as edge_rid, @in as media_rid, procedure, Procedure, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId, result, Result FROM `USES_MEDIA` WHERE @out = " . $processRid;
        $mediaEdges = GraphRetriever::execute($sql, [], $debug);
        
        if (!empty($mediaEdges)) {
            $mediaRids = array_unique(array_column($mediaEdges, 'media_rid'));
            $mediaNameMap = GraphRetriever::fetchNodeNames($mediaRids, $debug, 'Media');
            
            foreach ($mediaEdges as $mediaEdge) {
                $rawCount++;
                $meProps = EvidenceCorrelator::extractIdentifiers($mediaEdge);
                
                $mediaName = isset($mediaNameMap[$mediaEdge['media_rid']]) ? $mediaNameMap[$mediaEdge['media_rid']] : 'Unknown Media';
                
                $dedupKey = KnowledgeNormalizer::generateDeduplicationKey($methodName, $mediaName, $meProps);
                
                if (!isset($uniqueResults[$dedupKey])) {
                    $uniqueResults[$dedupKey] = [
                        'method' => $methodName,
                        'media' => $mediaName,
                        'properties' => $meProps
                    ];
                }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$mediaEdge]);
            }
        }
        
        $debug['raw_records'] = $rawCount;
        $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
        $debug['final_count'] = count($uniqueResults);
        
        $path = ['Shodhana_Method', 'USES_MEDIA', 'Media'];
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
    $process = isset($_GET['entity']) ? trim($_GET['entity']) : (isset($_GET['process']) ? trim($_GET['process']) : '');
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    
    if ($process === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing process parameter']);
        exit;
    }
    
    echo json_encode(execute_process_media($process, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
