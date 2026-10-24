<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_dosha_disease($dosha, $debugMode = false) {
    if ($dosha === '') { return ['status' => 'error', 'message' => 'Missing parameter']; }
    $debug = ['entity_resolution' => [], 'sql_queries' => [], 'raw_records' => 0, 'duplicates_filtered' => 0, 'final_count' => 0];

    try {
        $entityNode = EntityResolver::resolve('Dosha', $dosha, $debug);
        $entity = ['name' => isset($entityNode['name']) ? $entityNode['name'] : $dosha, 'type' => 'Dosha'];
        if (!$entityNode) { return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity]; }
        
        $entity['_evidence'] = new LegacyEvidence($entityNode);
        
        $doshaRid = $entityNode['rid'];
        $sql = "SELECT @rid as edge_rid, @out as target_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `HAS_DOSHA_IMBALANCE` WHERE @in = :dosha_rid";
        $edges = GraphRetriever::execute($sql, ['dosha_rid' => $doshaRid], $debug);

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
            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey('HAS_DOSHA_IMBALANCE', $targetName, $props);
            
            if (!isset($uniqueResults[$dedupKey])) {
                $uniqueResults[$dedupKey] = ['disease' => $targetName, 'properties' => $props];
            }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$edge]);
        }
        
        $debug['raw_records'] = $rawCount;
        $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
        $debug['final_count'] = count($uniqueResults);
        return KnowledgeNormalizer::formatCanonical($entity, ['Dosha', '<- HAS_DOSHA_IMBALANCE', 'Disease'], $uniqueResults, $debugMode ? $debug : null);
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage(), 'debug' => $debug];
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $dosha = isset($_GET['dosha']) ? trim($_GET['dosha']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    if ($dosha === '') { echo json_encode(['status' => 'error', 'message' => 'Missing parameter']); exit; }
    echo json_encode(execute_dosha_disease($dosha, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
