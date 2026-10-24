<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_purified_compound_disease($purified, $debugMode = false) {
    if ($purified === '') { return ['status' => 'error', 'message' => 'Missing parameter']; }
    $debug = ['entity_resolution' => [], 'sql_queries' => [], 'raw_records' => 0, 'duplicates_filtered' => 0, 'final_count' => 0];

    try {
        $entityNode = EntityResolver::resolve('Purified_Compound', $purified, $debug);
        $entity = ['name' => isset($entityNode['name']) ? $entityNode['name'] : $purified, 'type' => 'Purified_Compound'];
        if (!$entityNode) { return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity]; }
        
        $entity['_evidence'] = new LegacyEvidence($entityNode);
        
        $purifiedRid = $entityNode['rid'];
        $sql1 = "SELECT @rid as edge_rid, @in as target_rid, 'USED_FOR_TREATMENT' as edge_class, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `USED_FOR_TREATMENT` WHERE @out = :purified_rid";
        $sql2 = "SELECT @rid as edge_rid, @in as target_rid, 'TREATS' as edge_class, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `TREATS` WHERE @out = :purified_rid";
        $edges1 = GraphRetriever::execute($sql1, ['purified_rid' => $purifiedRid], $debug);
        $edges2 = GraphRetriever::execute($sql2, ['purified_rid' => $purifiedRid], $debug);
        $edges = array_merge($edges1, $edges2);

        if (empty($edges)) return KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        
        $targetRids = array_unique(array_column($edges, 'target_rid'));
        // Targets may be Disease or Indication; resolve the actual record IDs.
        $targetNameMap = GraphRetriever::fetchNodeNames($targetRids, $debug);

        $uniqueResults = [];
        $rawCount = 0;
        foreach ($edges as $edge) {
            $rawCount++;
            $targetRid = $edge['target_rid'];
            $targetName = isset($targetNameMap[$targetRid]) ? $targetNameMap[$targetRid] : 'Unknown Disease/Indication';
            $props = EvidenceCorrelator::extractIdentifiers($edge);
            // The flat contract groups identical names/evidence across both relations.
            // Keep every underlying edge below, even when the displayed row is shared.
            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey('disease', $targetName, $props);
            
            if (!isset($uniqueResults[$dedupKey])) {
                $uniqueResults[$dedupKey] = ['disease' => $targetName, 'properties' => $props, 'supporting_edges' => []];
            }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$edge]);
            $uniqueResults[$dedupKey]['supporting_edges'][] = [
                'id' => $edge['edge_rid'],
                'type' => $edge['edge_class'],
                'source' => $purifiedRid,
                'target' => $targetRid
            ];
        }
        
        $debug['raw_records'] = $rawCount;
        $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
        $debug['final_count'] = count($uniqueResults);
        // Keep technical edge metadata outside display properties, mapped to its row.
        $supportingEdges = [];
        $rows = array_values($uniqueResults);
        foreach ($rows as $index => &$row) {
            foreach ($row['supporting_edges'] as $support) {
                $support['result_index'] = $index;
                $supportingEdges[] = $support;
            }
            unset($row['supporting_edges']);
        }
        unset($row);
        $response = KnowledgeNormalizer::formatCanonical($entity, ['Purified_Compound', 'USED_FOR_TREATMENT/TREATS', 'Disease'], $rows, $debugMode ? $debug : null);
        $response['supporting_edges'] = $supportingEdges;
        return $response;
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage(), 'debug' => $debug];
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $purified = isset($_GET['purified']) ? trim($_GET['purified']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    if ($purified === '') { echo json_encode(['status' => 'error', 'message' => 'Missing parameter']); exit; }
    echo json_encode(execute_purified_compound_disease($purified, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
