<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_toxic_substance_compound($toxic, $debugMode = false) {
    if ($toxic === '') { return ['status' => 'error', 'message' => 'Missing parameter']; }
    $debug = ['entity_resolution' => [], 'sql_queries' => [], 'raw_records' => 0, 'duplicates_filtered' => 0, 'final_count' => 0];

    try {
        $entityNode = EntityResolver::resolve('Toxic_Substance', $toxic, $debug);
        $entity = ['name' => isset($entityNode['name']) ? $entityNode['name'] : $toxic, 'type' => 'Toxic_Substance'];
        if (!$entityNode) { return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity]; }
        
        $entity['_evidence'] = new LegacyEvidence($entityNode);
        
        $toxicRid = $entityNode['rid'];
        $sql = "SELECT @rid as edge_rid, @out as target_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `CONTAINS` WHERE @in = :toxic_rid";
        $edges = GraphRetriever::execute($sql, ['toxic_rid' => $toxicRid], $debug);

        if (empty($edges)) return KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        
        $targetRids = array_unique(array_column($edges, 'target_rid'));
        $targetNameMap = GraphRetriever::fetchNodeNames($targetRids, $debug, 'Compound');

        $uniqueResults = [];
        $rawCount = 0;
        foreach ($edges as $edge) {
            $rawCount++;
            $targetRid = $edge['target_rid'];
            $targetName = isset($targetNameMap[$targetRid]) ? $targetNameMap[$targetRid] : 'Unknown Compound';
            $props = EvidenceCorrelator::extractIdentifiers($edge);
            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey('CONTAINS', $targetName, $props);
            
            if (!isset($uniqueResults[$dedupKey])) {
                $uniqueResults[$dedupKey] = ['compound' => $targetName, 'properties' => $props];
            }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$edge]);
        }
        
        $debug['raw_records'] = $rawCount;
        $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
        $debug['final_count'] = count($uniqueResults);
        return KnowledgeNormalizer::formatCanonical($entity, ['Toxic_Substance', '<- CONTAINS', 'Compound'], $uniqueResults, $debugMode ? $debug : null);
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage(), 'debug' => $debug];
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $toxic = isset($_GET['toxic']) ? trim($_GET['toxic']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    if ($toxic === '') { echo json_encode(['status' => 'error', 'message' => 'Missing parameter']); exit; }
    echo json_encode(execute_toxic_substance_compound($toxic, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
