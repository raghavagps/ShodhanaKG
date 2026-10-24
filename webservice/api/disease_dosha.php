<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_disease_dosha($disease, $debugMode = false) {
    if ($disease === '') { return ['status' => 'error', 'message' => 'Missing disease parameter']; }

    $debug = [
        'entity_resolution' => [],
        'sql_queries' => [],
        'raw_records' => 0,
        'duplicates_filtered' => 0,
        'final_count' => 0
    ];

    try {
        $entityNode = EntityResolver::resolve('Disease', $disease, $debug);
        
        $entity = [
            'name' => isset($entityNode['name']) ? $entityNode['name'] : $disease,
            'type' => 'Disease'
        ];
        
        if (!$entityNode) {
            return KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        }
        
        $entity['_evidence'] = new LegacyEvidence($entityNode);
        
        $diseaseRid = $entityNode['rid'];

        $sqlDoshas = "SELECT @rid as edge_rid, @in as target_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `HAS_DOSHA_IMBALANCE` WHERE @out = :disease_rid";
        $edges = GraphRetriever::execute($sqlDoshas, ['disease_rid' => $diseaseRid], $debug);

        if (empty($edges)) {
            return KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        }
        
        $targetRids = array_unique(array_column($edges, 'target_rid'));
        $targetNameMap = GraphRetriever::fetchNodeNames($targetRids, $debug, 'Dosha');

        $uniqueResults = [];
        $rawCount = 0;
        
        foreach ($edges as $edge) {
            $rawCount++;
            $targetRid = $edge['target_rid'];
            $targetName = isset($targetNameMap[$targetRid]) ? $targetNameMap[$targetRid] : 'Unknown Dosha';
            
            $props = EvidenceCorrelator::extractIdentifiers($edge);
            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey('HAS_DOSHA_IMBALANCE', $targetName, $props);
            
            if (!isset($uniqueResults[$dedupKey])) {
                $uniqueResults[$dedupKey] = [
                    'dosha' => $targetName,
                    'properties' => $props
                ];
            }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$edge]);
        }
        
        $debug['raw_records'] = $rawCount;
        $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
        $debug['final_count'] = count($uniqueResults);
        
        $path = ['Disease', 'HAS_DOSHA_IMBALANCE', 'Dosha'];
        return KnowledgeNormalizer::formatCanonical($entity, $path, $uniqueResults, $debugMode ? $debug : null);

    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage(), 'debug' => $debug];
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $disease = isset($_GET['disease']) ? trim($_GET['disease']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    if ($disease === '') { echo json_encode(['status' => 'error', 'message' => 'Missing disease parameter']); exit; }
    echo json_encode(execute_disease_dosha($disease, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
