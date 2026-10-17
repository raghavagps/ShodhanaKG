<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_indication_compound($indicationName, $debugMode = false) {
    if ($indicationName === '') { return ['status' => 'error', 'message' => 'Missing indication parameter']; }

    $debug = [
        'entity_resolution' => [],
        'sql_queries' => [],
        'raw_records' => 0,
        'duplicates_filtered' => 0,
        'final_count' => 0
    ];

    try {
        $entityNode = EntityResolver::resolve('Indication', $indicationName, $debug);
        
        $entity = [
            'name' => isset($entityNode['name']) ? $entityNode['name'] : $indicationName,
            'type' => 'Indication'
        ];
        
        if (!$entityNode) {
        return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity];
    }
        
        $entity['_evidence'] = new LegacyEvidence($entityNode);
        
        $indicationRid = $entityNode['rid'];
        $indicationRealName = $entity['name'];
        $entityType = $entity['type'];

        $uniqueResults = [];
        $rawCount = 0;
        
        // Layer 1: Purified_Compound -> TREATS/USED_FOR_TREATMENT -> Indication/Disease
        if ($entityType === 'Disease') {
            // In ShodhanaKG, it might be Compound -> USED_FOR_TREATMENT -> Disease
            $sqlTreats = "SELECT @rid as edge_rid, @out as purified_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `USED_FOR_TREATMENT` WHERE @in = " . $indicationRid;
        } else {
            $sqlTreats = "SELECT @rid as edge_rid, @out as purified_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `TREATS` WHERE @in = " . $indicationRid;
        }
        
        $treatsEdges = GraphRetriever::execute($sqlTreats, [], $debug);
        
        if (!empty($treatsEdges)) {
            $purifiedRids = array_unique(array_column($treatsEdges, 'purified_rid'));
            $purifiedNameMap = GraphRetriever::fetchNodeNames($purifiedRids, $debug, 'Purified_Compound');
            
            foreach ($treatsEdges as $tEdge) {
                $purifiedRid = $tEdge['purified_rid'];
                $purifiedName = isset($purifiedNameMap[$purifiedRid]) ? $purifiedNameMap[$purifiedRid] : 'Unknown Purified Compound';
                $tProps = EvidenceCorrelator::extractIdentifiers($tEdge);
                
                // Enforce strict path provenance correlation
                $sqlProduces = "SELECT @rid as edge_rid, @out as shodhana_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `PRODUCES` WHERE @in = " . $purifiedRid;
                $producesEdges = GraphRetriever::execute($sqlProduces, [], $debug);
                
                if (empty($producesEdges)) {
                    $rawCount++;
                    $dedupKey = KnowledgeNormalizer::generateDeduplicationKey($purifiedName, $indicationRealName, $tProps);
                    if (!isset($uniqueResults[$dedupKey])) {
                        $uniqueResults[$dedupKey] = [
                            'compound' => null,
                            'shodhana_method' => null,
                            'purified_compound' => $purifiedName,
                            'indication' => $indicationRealName,
                            'properties' => $tProps
                        ];
                    }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$tEdge]);
                } else {
                    foreach ($producesEdges as $pEdge) {
                        $pProps = EvidenceCorrelator::extractIdentifiers($pEdge);
                        
                        // The PRODUCES edge MUST correlate with the TREATS edge to belong to the same path
                        if (!EvidenceCorrelator::correlate($tProps, $pProps)) {
                            continue;
                        }
                        
                        $shodhanaRid = $pEdge['shodhana_rid'];
                        $sqlUndergoes = "SELECT @rid as edge_rid, @out as compound_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId, procedure FROM `UNDERGOES` WHERE @in = " . $shodhanaRid;
                        $undergoesEdges = GraphRetriever::execute($sqlUndergoes, [], $debug);
                        
                        if (empty($undergoesEdges)) {
                            $rawCount++;
                            $shodhanaNameMap = GraphRetriever::fetchNodeNames([$shodhanaRid], $debug);
                            $shodhanaName = isset($shodhanaNameMap[$shodhanaRid]) ? $shodhanaNameMap[$shodhanaRid] : null;
                            
                            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey($purifiedName, $indicationRealName, $pProps) . '|' . $shodhanaRid;
                            if (!isset($uniqueResults[$dedupKey])) {
                                $uniqueResults[$dedupKey] = [
                                    'compound' => null,
                                    'shodhana_method' => $shodhanaName,
                                    'purified_compound' => $purifiedName,
                                    'indication' => $indicationRealName,
                                    'properties' => $pProps
                                ];
                            }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$tEdge, $pEdge]);
                        } else {
                            foreach ($undergoesEdges as $uEdge) {
                                $uProps = EvidenceCorrelator::extractIdentifiers($uEdge);
                                
                                // The UNDERGOES edge MUST correlate with the PRODUCES edge
                                if (!EvidenceCorrelator::correlate($pProps, $uProps)) {
                                    continue;
                                }
                                
                                $rawCount++;
                                $compoundRid = $uEdge['compound_rid'];
                                
                                $nodeMap = GraphRetriever::fetchNodeNames([$shodhanaRid, $compoundRid], $debug);
                                $shodhanaName = isset($nodeMap[$shodhanaRid]) ? $nodeMap[$shodhanaRid] : null;
                                $compoundName = isset($nodeMap[$compoundRid]) ? $nodeMap[$compoundRid] : null;
                                
                                $finalProps = $tProps;
                                if (!empty($uEdge['procedure'])) {
                                    $finalProps['procedure'] = $uEdge['procedure'];
                                }
                                
                                $dedupKey = KnowledgeNormalizer::generateDeduplicationKey($purifiedName, $indicationRealName, $finalProps) . '|' . $shodhanaRid . '|' . $compoundRid;
                                
                                if (!isset($uniqueResults[$dedupKey])) {
                                    $uniqueResults[$dedupKey] = [
                                        'compound' => $compoundName,
                                        'shodhana_method' => $shodhanaName,
                                        'purified_compound' => $purifiedName,
                                        'indication' => $indicationRealName,
                                        'properties' => $finalProps
                                    ];
                                }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$tEdge, $pEdge, $uEdge]);
                            }
                        }
                    }
                }
            }
        }
        
        $debug['raw_records'] = $rawCount;
        $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
        $debug['final_count'] = count($uniqueResults);
        
        $path = [$entityType, $entityType === 'Disease' ? 'USED_FOR_TREATMENT' : 'TREATS', 'Purified_Compound', 'PRODUCES', 'Shodhana_Method', 'UNDERGOES', 'Compound'];
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
    $indication = isset($_GET['entity']) ? trim($_GET['entity']) : (isset($_GET['indication']) ? trim($_GET['indication']) : '');
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    
    if ($indication === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing indication parameter']);
        exit;
    }
    
    echo json_encode(execute_indication_compound($indication, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
