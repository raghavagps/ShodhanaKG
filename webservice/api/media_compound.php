<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/LegacyProvenance.php';

function execute_media_compound($entity, $debugMode = false) {
    return LegacyProvenance::run('provenance_media_method_compound', $entity, (bool)$debugMode);
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $media = isset($_GET['media']) ? trim($_GET['media']) : '';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    
    if ($media === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing media parameter']);
        exit;
    }
    
    echo json_encode(execute_media_compound($media, $debugMode), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
