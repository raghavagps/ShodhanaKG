<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_toxic_substance_method($entityName, $debugMode = false) {
    if ($entityName === '') { return ['status' => 'error', 'message' => 'Missing parameter']; }
    $debug = ['entity_resolution' => [], 'sql_queries' => [], 'raw_records' => 0, 'duplicates_filtered' => 0, 'final_count' => 0];

    try {
        $entityNode = EntityResolver::resolve('Toxic_Substance', $entityName, $debug);
        $entity = ['name' => isset($entityNode['name']) ? $entityNode['name'] : $entityName, 'type' => 'Toxic_Substance'];
        if (!$entityNode) { return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity]; }
        
        $entity['_evidence'] = new LegacyEvidence($entityNode);
        
        $entityRid = $entityNode['rid'];

        $sql = "SELECT FROM `DETOXIFIES` WHERE @in = :entity_rid";
        $edges = GraphRetriever::execute($sql, ['entity_rid' => $entityRid], $debug);

        if (empty($edges)) {
            return KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        }
        
        $targetRids = array_unique(array_column($edges, '@out'));
        $targetNameMap = GraphRetriever::fetchNodeNames($targetRids, $debug);

        $uniqueResults = [];
        $rawCount = 0;
        foreach ($edges as $edge) {
            $rawCount++;
            $targetRid = $edge['@out'];
            $targetName = isset($targetNameMap[$targetRid]) ? $targetNameMap[$targetRid] : 'Unknown';
            
            // Read every stored alias, including legacy ref, before deduplication.
            $props = array_intersect_key(
                EvidenceCorrelator::extractIdentifiers($edge),
                array_flip(['pubmed_id', 'doi', 'ayush_id', 'unclassified_reference'])
            );
            
            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey('DETOXIFIES', $targetName, $props);
            
            if (!isset($uniqueResults[$dedupKey])) {
                $uniqueResults[$dedupKey] = [
                    'related_entity' => $targetName,
                    'properties' => $props
                ];
            }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$edge]);
        }
        
        $debug['raw_records'] = $rawCount;
        $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
        $debug['final_count'] = count($uniqueResults);
        
        return KnowledgeNormalizer::formatCanonical(
            $entity, 
            ['Toxic_Substance', '<- DETOXIFIES', 'Shodhana_Method'], 
            array_values($uniqueResults), 
            $debugMode ? $debug : null
        );
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage(), 'debug' => $debug];
    }
}