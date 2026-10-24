<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_purified_compound_validation($compound, $debugMode = false) {
    if ($compound === '') { return ['status' => 'error', 'message' => 'Missing parameter']; }
    $debug = ['entity_resolution' => [], 'sql_queries' => [], 'raw_records' => 0, 'duplicates_filtered' => 0, 'final_count' => 0];

    try {
        $entityNode = EntityResolver::resolve('Purified_Compound', $compound, $debug);
        $entity = ['name' => isset($entityNode['name']) ? $entityNode['name'] : $compound, 'type' => 'Purified_Compound'];
        
        if (!$entityNode) { return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity]; }
        
        $entity['_evidence'] = new LegacyEvidence($entityNode);
        
        $compoundRid = $entityNode['rid'];

        // Vanga Bhasma -> CONFIRMS_SAFETY_OF is inbound from Confirmation_Study
        $sqlSafety = "SELECT FROM `CONFIRMS_SAFETY_OF` WHERE @in = :compound_rid";
        $safetyEdges = GraphRetriever::execute($sqlSafety, ['compound_rid' => $compoundRid], $debug);

        if (empty($safetyEdges)) {
            return KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        }
        
        $studyRids = array_unique(array_column($safetyEdges, '@out'));
        $studyNameMap = GraphRetriever::fetchNodeNames($studyRids, $debug, 'Confirmation_Study');

        $uniqueResults = [];
        $rawCount = 0;
        foreach ($safetyEdges as $edge) {
            $rawCount++;
            $studyRid = $edge['@out'];
            $studyName = isset($studyNameMap[$studyRid]) ? $studyNameMap[$studyRid] : 'Unknown Study';
            
            $edgeProperties = EvidenceCorrelator::extractIdentifiers($edge);
            $props = array_intersect_key(
                $edgeProperties,
                array_flip(['pubmed_id', 'doi', 'ayush_id', 'unclassified_reference'])
            );
            
            $result = $edgeProperties['result'];
            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey('CONFIRMS_SAFETY_OF', $studyName, array_merge($props, ['result' => $result]));
            
            if (!isset($uniqueResults[$dedupKey])) {
                $uniqueResults[$dedupKey] = [
                    'study' => $studyName,
                    'result' => $result,
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
            ['Purified_Compound', 'CONFIRMS_SAFETY_OF', 'Confirmation_Study'], 
            array_values($uniqueResults), 
            $debugMode ? $debug : null
        );
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage(), 'debug' => $debug];
    }
}
