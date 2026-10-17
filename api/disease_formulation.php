<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/LegacyProvenance.php';

function execute_disease_formulation($entity, $debugMode = false) {
    return LegacyProvenance::run('provenance_disease_therapy_formulation', $entity, (bool)$debugMode);
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $disease = isset($_GET['disease']) ? trim($_GET['disease']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    
    if ($disease === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing disease parameter']);
        exit;
    }
    
    echo json_encode(execute_disease_formulation($disease, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

