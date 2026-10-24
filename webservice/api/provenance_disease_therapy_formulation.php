<?php

require_once __DIR__ . '/core/EvidenceTraversal.php';
function execute_provenance_disease_therapy_formulation($entity, $debugMode = false)
{
    return (new EvidenceTraversal())->run('provenance_disease_therapy_formulation', $entity, (bool) $debugMode);
}
if (basename(__FILE__) === basename((isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : ''))) {
    EvidenceTraversal::serve('provenance_disease_therapy_formulation');
}
