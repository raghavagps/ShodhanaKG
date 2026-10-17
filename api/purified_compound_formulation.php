<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_purified_compound_formulation($purified, $debugMode = false) {
    if ($purified === '') { return ['status' => 'error', 'message' => 'Missing parameter']; }
    $debug = ['entity_resolution' => [], 'sql_queries' => [], 'raw_records' => 0, 'duplicates_filtered' => 0, 'final_count' => 0];

    try {
        $entityNode = EntityResolver::resolve('Purified_Compound', $purified, $debug);
        $entity = ['name' => isset($entityNode['name']) ? $entityNode['name'] : $purified, 'type' => 'Purified_Compound'];
        if (!$entityNode) { return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity]; }
        
        $entity['_evidence'] = new LegacyEvidence($entityNode);
        
        $purifiedRid = $entityNode['rid'];
        $sql = "SELECT @rid as edge_rid, @in as target_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `USED_IN_FORMULATION` WHERE @out = :purified_rid";
        $edges = GraphRetriever::execute($sql, ['purified_rid' => $purifiedRid], $debug);

        if (empty($edges)) return KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        
        $targetRids = array_unique(array_column($edges, 'target_rid'));
        // Both Formulation and Ayurvedic_Formulation are valid targets.
        $targetNameMap = GraphRetriever::fetchNodeNames($targetRids, $debug);

        $uniqueResults = [];
        $rawCount = 0;
        foreach ($edges as $edge) {
            $rawCount++;
            $targetRid = $edge['target_rid'];
            $targetName = isset($targetNameMap[$targetRid]) ? $targetNameMap[$targetRid] : 'Unknown Formulation';
            $props = EvidenceCorrelator::extractIdentifiers($edge);
            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey('USED_IN_FORMULATION', $targetName, $props);
            
            if (!isset($uniqueResults[$dedupKey])) {
                $uniqueResults[$dedupKey] = ['formulation' => $targetName, 'properties' => $props];
            }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$edge]);
        }
        
        $debug['raw_records'] = $rawCount;
        $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
        $debug['final_count'] = count($uniqueResults);
        return KnowledgeNormalizer::formatCanonical($entity, ['Purified_Compound', 'USED_IN_FORMULATION', 'Formulation'], $uniqueResults, $debugMode ? $debug : null);
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage(), 'debug' => $debug];
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $purified = isset($_GET['purified']) ? trim($_GET['purified']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    if ($purified === '') { echo json_encode(['status' => 'error', 'message' => 'Missing parameter']); exit; }
    echo json_encode(execute_purified_compound_formulation($purified, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
