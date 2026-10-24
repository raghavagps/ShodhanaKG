<?php

require_once __DIR__ . '/core/EvidenceTraversal.php';
function execute_provenance_formulation_therapy_disease($entity, $debugMode = false)
{
    return (new EvidenceTraversal())->run('provenance_formulation_therapy_disease', $entity, (bool) $debugMode);
}
if (basename(__FILE__) === basename((isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : ''))) {
    EvidenceTraversal::serve('provenance_formulation_therapy_disease');
}
