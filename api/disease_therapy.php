<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_disease_therapy($disease, $debugMode = false) {
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
        return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity];
    }
    
    $entity['_evidence'] = new LegacyEvidence($entityNode);
    
    $diseaseRid = $entityNode['rid'];

    $edgeClasses = ['TREATED_BY', 'PRE_TREATED_BY', 'POST_TREATED_BY'];
    $allEdges = [];
    
    foreach ($edgeClasses as $edgeClass) {
        $sql = "SELECT @rid as edge_rid, @in as therapy_rid, pubmedId, doi, ref, ayush_id, Ayush_ID, ayushId, '{$edgeClass}' as phase FROM `{$edgeClass}` WHERE @out = :disease_rid";
        $edges = GraphRetriever::execute($sql, ['disease_rid' => $diseaseRid], $debug);
        $allEdges = array_merge($allEdges, $edges);
    }

    if (empty($allEdges)) {
        $response = KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        return $response;
    }
    
    $therapyRids = array_unique(array_column($allEdges, 'therapy_rid'));
    $therapyNameMap = GraphRetriever::fetchNodeNames($therapyRids, $debug, 'Therapy');

    $uniqueResults = [];
    $rawCount = 0;
    
    foreach ($allEdges as $edge) {
        $rawCount++;
        $therapyRid = $edge['therapy_rid'];
        $therapyName = isset($therapyNameMap[$therapyRid]) ? $therapyNameMap[$therapyRid] : 'Unknown Therapy';
        
        $props = EvidenceCorrelator::extractIdentifiers($edge);
        $props['phase'] = $edge['phase'];
        
        $dedupKey = KnowledgeNormalizer::generateDeduplicationKey('Therapy', $therapyName, $props);
        
        if (!isset($uniqueResults[$dedupKey])) {
            $uniqueResults[$dedupKey] = [
                'therapy' => $therapyName,
                'properties' => $props
            ];
        }
$entity['_evidence']->record($uniqueResults[$dedupKey], [$edge]);
    }
    
    $debug['raw_records'] = $rawCount;
    $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
    $debug['final_count'] = count($uniqueResults);
    
    $path = ['Disease', '[PRE/POST]_TREATED_BY', 'Therapy'];
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
    
    echo json_encode(execute_disease_therapy($disease, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
