<?php

require_once __DIR__ . '/core/PhpCompatibility.php';
require_once __DIR__ . '/core/SemanticQuery.php';
function execute_compound_methods($entity, $debugMode = false, $offset = 0, $limit = 1000)
{
    $offset = PhpCompatibility::value($offset, 'int', false);
    $limit = PhpCompatibility::value($limit, 'int', false);
    return SemanticQuery::run('Compound', $entity, 'Shodhana_Method', [], $offset, $limit);
}
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    require_once __DIR__ . '/cors.php';
    api_handle_cors_preflight();
    header('Content-Type: application/json');
    $entity = (isset($_GET['entity']) ? $_GET['entity'] : (isset($_GET['compound']) ? $_GET['compound'] : ''));
    echo json_encode((is_string($entity) && trim($entity) !== '' ? execute_compound_methods($entity, false, (int) (isset($_GET['offset']) ? $_GET['offset'] : 0), (int) (isset($_GET['limit']) ? $_GET['limit'] : 1000)) : ['status' => 'error', 'message' => 'Missing entity']));
}
