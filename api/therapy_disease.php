<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_therapy_disease($therapy, $debugMode = false) {
    if ($therapy === '') { return ['status' => 'error', 'message' => 'Missing parameter']; }
    $debug = ['entity_resolution' => [], 'sql_queries' => [], 'raw_records' => 0, 'duplicates_filtered' => 0, 'final_count' => 0];

    try {
        $entityNode = EntityResolver::resolve('Therapy', $therapy, $debug);
        $entity = ['name' => isset($entityNode['name']) ? $entityNode['name'] : $therapy, 'type' => 'Therapy'];
        if (!$entityNode) { return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity]; }
        
        $entity['_evidence'] = new LegacyEvidence($entityNode);
        
        $therapyRid = $entityNode['rid'];
        $edges = [];
        foreach (['TREATED_BY', 'PRE_TREATED_BY', 'POST_TREATED_BY'] as $edgeClass) {
            $sql = "SELECT @rid as edge_rid, @out as target_rid, '$edgeClass' as edge_class, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `$edgeClass` WHERE @in = :therapy_rid";
            $classEdges = GraphRetriever::execute($sql, ['therapy_rid' => $therapyRid], $debug);
            if (is_array($classEdges)) {
                $edges = array_merge($edges, $classEdges);
            }
        }

        if (empty($edges)) return KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        
        $targetRids = array_unique(array_column($edges, 'target_rid'));
        $targetNameMap = GraphRetriever::fetchNodeNames($targetRids, $debug, 'Disease');

        $uniqueResults = [];
        $rawCount = 0;
        foreach ($edges as $edge) {
            $rawCount++;
            $targetRid = $edge['target_rid'];
            $targetName = isset($targetNameMap[$targetRid]) ? $targetNameMap[$targetRid] : 'Unknown Disease';
            $props = EvidenceCorrelator::extractIdentifiers($edge);
            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey($edge['edge_class'], $targetName, $props);
            
            if (!isset($uniqueResults[$dedupKey])) {
                $uniqueResults[$dedupKey] = ['disease' => $targetName, 'relationship' => $edge['edge_class'], 'properties' => $props];
            }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$edge]);
        }
        
        $debug['raw_records'] = $rawCount;
        $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
        $debug['final_count'] = count($uniqueResults);
        return KnowledgeNormalizer::formatCanonical($entity, ['Therapy', '<- TREATED_BY', 'Disease'], $uniqueResults, $debugMode ? $debug : null);
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage(), 'debug' => $debug];
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $therapy = isset($_GET['therapy']) ? trim($_GET['therapy']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    if ($therapy === '') { echo json_encode(['status' => 'error', 'message' => 'Missing parameter']); exit; }
    echo json_encode(execute_therapy_disease($therapy, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
