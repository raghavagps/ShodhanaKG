<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/LegacyProvenance.php';

function execute_compound_shodhana_process($entity, $debugMode = false) {
    return LegacyProvenance::run('provenance_compound_method_media', $entity, (bool)$debugMode);
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $compound = isset($_GET['compound']) ? trim($_GET['compound']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    
    if ($compound === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing compound parameter']);
        exit;
    }
    
    echo json_encode(execute_compound_shodhana_process($compound, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
