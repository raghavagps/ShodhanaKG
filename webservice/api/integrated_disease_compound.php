<?php

require_once __DIR__ . '/core/PhpCompatibility.php';
require_once __DIR__ . '/core/SemanticQuery.php';
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';
function execute_integrated_disease_compound($entity, $debugMode = false, $offset = 0, $limit = 1000)
{
    $offset = PhpCompatibility::value($offset, 'int', false);
    $limit = PhpCompatibility::value($limit, 'int', false);
    return SemanticQuery::run('Disease', $entity, 'Compound', [], $offset, $limit);
}
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $disease = (isset($_GET['disease']) ? trim($_GET['disease']) : '');
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    if ($disease === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing parameter']);
        exit;
    }
    echo json_encode(execute_integrated_disease_compound($disease, $debugMode, (int) (isset($_GET['offset']) ? $_GET['offset'] : 0), (int) (isset($_GET['limit']) ? $_GET['limit'] : 1000)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
