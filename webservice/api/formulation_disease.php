<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/LegacyProvenance.php';

function execute_formulation_disease($entity, $debugMode = false) {
    return LegacyProvenance::run('provenance_formulation_therapy_disease', $entity, (bool)$debugMode);
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $form = isset($_GET['formulation']) ? trim($_GET['formulation']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    if ($form === '') { echo json_encode(['status' => 'error', 'message' => 'Missing parameter']); exit; }
    echo json_encode(execute_formulation_disease($form, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
