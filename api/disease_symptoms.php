<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_disease_symptoms($disease, $debugMode = false) {
if ($disease === '') { return ['status' => 'error', 'message' => 'Missing disease parameter']; }

$debug = [
    'entity_resolution' => [],
    'sql_queries' => [],
    'raw_records' => 0,
    'duplicates_filtered' => 0,
    'final_count' => 0
];

try {
    // 1. Entity Resolution
    $entityNode = EntityResolver::resolve('Disease', $disease, $debug);
    
    $entity = [
        'name' => isset($entityNode['name']) ? $entityNode['name'] : $disease,
        'type' => 'Disease'
    ];
    
    if (!$entityNode) {
        return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity];
    }
    
    $entity['_evidence'] = new LegacyEvidence($entityNode);
    
    $diseaseRid = $entityNode['rid'];

    // 2. Traversal Layer 1: Disease -> HAS_SYMPTOM -> Symptoms
    $sqlSymptoms = "SELECT @rid as edge_rid, @in as symptom_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId FROM `HAS_SYMPTOM` WHERE @out = :disease_rid";
    $symptomEdges = GraphRetriever::execute($sqlSymptoms, ['disease_rid' => $diseaseRid], $debug);

    if (empty($symptomEdges)) {
        $response = KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        return $response;
    }
    
    $symptomRids = array_unique(array_column($symptomEdges, 'symptom_rid'));
    $symptomNameMap = GraphRetriever::fetchNodeNames($symptomRids, $debug, 'Symptom');

    $uniqueResults = [];
    $rawCount = 0;
    
    foreach ($symptomEdges as $edge) {
        $rawCount++;
        $symptomRid = $edge['symptom_rid'];
        $symptomName = isset($symptomNameMap[$symptomRid]) ? $symptomNameMap[$symptomRid] : 'Unknown Symptom';
        
        $props = EvidenceCorrelator::extractIdentifiers($edge);
        
        // Single hop pattern: no evidence correlation required with another edge,
        // but we deduplicate based on symptom and provenance properties.
        $dedupKey = KnowledgeNormalizer::generateDeduplicationKey('HAS_SYMPTOM', $symptomName, $props);
        
        if (!isset($uniqueResults[$dedupKey])) {
            $uniqueResults[$dedupKey] = [
                'symptom' => $symptomName,
                'properties' => $props
            ];
        }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$edge]);
    }
    
    $debug['raw_records'] = $rawCount;
    $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
    $debug['final_count'] = count($uniqueResults);
    
    $path = ['Disease', 'HAS_SYMPTOM', 'Symptom'];
    $response = KnowledgeNormalizer::formatCanonical($entity, $path, $uniqueResults, $debugMode ? $debug : null);

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => $debug
    ];
}

return $response;
}


if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $disease = isset($_GET['disease']) ? trim($_GET['disease']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    
    if ($disease === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing disease parameter']);
        exit;
    }
    
    echo json_encode(execute_disease_symptoms($disease, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
