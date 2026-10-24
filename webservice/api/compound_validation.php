<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_compound_validation($compound, $debugMode = false) {
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

    // 2. Traversal Layer 1: Compound -> UNDERGOES
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
    
    // Safety evidence lookup function
    $resolveSafetyEvidence = function($studyRid, $vProps, &$selectedEdge) use (&$debug) {
        $selectedEdge = null;
        $sqlConfirms = "SELECT @rid as edge_rid, @in as target_rid, result, Result, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `CONFIRMS_SAFETY_OF` WHERE @out = :study_rid";
        $confirmsEdges = GraphRetriever::execute($sqlConfirms, ['study_rid' => $studyRid], $debug);
        
        foreach ($confirmsEdges as $confEdge) {
            $cProps = EvidenceCorrelator::extractIdentifiers($confEdge);
            if (EvidenceCorrelator::correlate($vProps, $cProps)) {
                $targetNameMap = GraphRetriever::fetchNodeNames([$confEdge['target_rid']], $debug);
                $targetName = isset($targetNameMap[$confEdge['target_rid']]) ? $targetNameMap[$confEdge['target_rid']] : 'Unknown Target';
                
                $selectedEdge = $confEdge;
                return [
                    'target_entity' => $targetName,
                    'result' => $cProps['result'],
                    'properties' => [
                        'pubmed_id' => $cProps['pubmed_id'],
                        'doi' => $cProps['doi'],
                        'ayush_id' => $cProps['ayush_id']
                    ]
                ];
            }
        }
        return null;
    };
    
    foreach ($undergoesEdges as $methodEdge) {
        $methodRid = $methodEdge['method_rid'];
        $methodName = isset($methodNameMap[$methodRid]) ? $methodNameMap[$methodRid] : 'Unknown Method';
        
        $mProps = EvidenceCorrelator::extractIdentifiers($methodEdge);
        
        // ==========================================
        // PATTERN A: Direct Validation
        // ==========================================
        $sqlValidationA = "SELECT @rid as edge_rid, @in as study_rid, procedure, Procedure, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId, result, Result FROM `VALIDATED_BY` WHERE @out = :method_rid";
        $validationEdgesA = GraphRetriever::execute($sqlValidationA, ['method_rid' => $methodRid], $debug);
        
        foreach ($validationEdgesA as $valEdge) {
            $rawCount++;
            $vProps = EvidenceCorrelator::extractIdentifiers($valEdge);
            
            // Correlation 1: VALIDATED_BY matches UNDERGOES
            if (!EvidenceCorrelator::correlate($mProps, $vProps)) {
                continue;
            }
            
            $studyRid = $valEdge['study_rid'];
            $studyNameMap = GraphRetriever::fetchNodeNames([$studyRid], $debug, 'Confirmation_Study');
            $studyName = isset($studyNameMap[$studyRid]) ? $studyNameMap[$studyRid] : 'Unknown Study';
            
            $safety_evidence = $resolveSafetyEvidence($studyRid, $vProps, $safetyEdge);
            
            $cleanValidationProps = [
                'procedure' => $vProps['procedure'],
                'pubmed_id' => $vProps['pubmed_id'],
                'doi' => $vProps['doi'],
                'ayush_id' => $vProps['ayush_id']
            ];
            
            $resultObj = [
                'validation_path' => ['Shodhana_Method', 'VALIDATED_BY', 'Confirmation_Study'],
                'method' => $methodName,
                'study' => $studyName,
                'validation_properties' => $cleanValidationProps
            ];
            
            if ($safety_evidence !== null) {
                $resultObj['safety_evidence'] = $safety_evidence;
            }
            
            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey($methodName, $studyName, $resultObj);
            
            if (!isset($uniqueResults[$dedupKey])) {
                $uniqueResults[$dedupKey] = $resultObj;
            }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$methodEdge, $valEdge, $safetyEdge]);
        }
        
        // ==========================================
        // PATTERN B: Process-based Validation
        // ==========================================
        $sqlFollowedBy = "SELECT @rid as edge_rid, @in as process_rid, procedure, Procedure, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `FOLLOWED_BY` WHERE @out = :method_rid";
        $followedByEdges = GraphRetriever::execute($sqlFollowedBy, ['method_rid' => $methodRid], $debug);
        
        foreach ($followedByEdges as $followedEdge) {
            $fProps = EvidenceCorrelator::extractIdentifiers($followedEdge);
            
            // Correlation: FOLLOWED_BY matches UNDERGOES
            if (!EvidenceCorrelator::correlate($mProps, $fProps)) {
                continue; // Reject cross-joined processes entirely
            }
            
            $processRid = $followedEdge['process_rid'];
            $processNameMap = GraphRetriever::fetchNodeNames([$processRid], $debug, 'Process');
            $processName = isset($processNameMap[$processRid]) ? $processNameMap[$processRid] : 'Unknown Process';
            
            // Now find VALIDATED_BY on the Process node
            $sqlValidationB = "SELECT @rid as edge_rid, @in as study_rid, procedure, Procedure, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId, result, Result FROM `VALIDATED_BY` WHERE @out = :process_rid";
            $validationEdgesB = GraphRetriever::execute($sqlValidationB, ['process_rid' => $processRid], $debug);
            
            foreach ($validationEdgesB as $valEdgeB) {
                $rawCount++;
                $vPropsB = EvidenceCorrelator::extractIdentifiers($valEdgeB);
                
                // Correlation: VALIDATED_BY matches FOLLOWED_BY (which matches UNDERGOES)
                if (!EvidenceCorrelator::correlate($fProps, $vPropsB)) {
                    continue;
                }
                
                $studyRidB = $valEdgeB['study_rid'];
                $studyNameMapB = GraphRetriever::fetchNodeNames([$studyRidB], $debug, 'Confirmation_Study');
                $studyNameB = isset($studyNameMapB[$studyRidB]) ? $studyNameMapB[$studyRidB] : 'Unknown Study';
                
                $safety_evidence_b = $resolveSafetyEvidence($studyRidB, $vPropsB, $safetyEdgeB);
                
                $cleanValidationPropsB = [
                    'procedure' => $vPropsB['procedure'],
                    'pubmed_id' => $vPropsB['pubmed_id'],
                    'doi' => $vPropsB['doi'],
                    'ayush_id' => $vPropsB['ayush_id']
                ];
                
                $resultObjB = [
                    'validation_path' => ['Shodhana_Method', 'FOLLOWED_BY', 'Process', 'VALIDATED_BY', 'Confirmation_Study'],
                    'method' => $methodName,
                    'process' => $processName,
                    'study' => $studyNameB,
                    'validation_properties' => $cleanValidationPropsB
                ];
                
                if ($safety_evidence_b !== null) {
                    $resultObjB['safety_evidence'] = $safety_evidence_b;
                }
                
                $dedupKeyB = KnowledgeNormalizer::generateDeduplicationKey($methodName, $studyNameB, $resultObjB);
                
                if (!isset($uniqueResults[$dedupKeyB])) {
                    $uniqueResults[$dedupKeyB] = $resultObjB;
                }
$entity['_evidence']->record($uniqueResults[$dedupKeyB], [$methodEdge, $followedEdge, $valEdgeB, $safetyEdgeB]);
            }
        }
    }
    
    $debug['raw_records'] = $rawCount;
    $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
    $debug['final_count'] = count($uniqueResults);
    
    // We pass a generalized root path to indicate multiple patterns
    $generalizedPath = ['Compound', 'UNDERGOES', 'Shodhana_Method', '...[Direct/Process]...', 'VALIDATED_BY', 'Confirmation_Study'];
    $response = KnowledgeNormalizer::formatCanonical($entity, $generalizedPath, array_values($uniqueResults), $debugMode ? $debug : null);

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
    
    echo json_encode(execute_compound_validation($compound, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
