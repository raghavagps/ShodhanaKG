<?php

require_once __DIR__ . '/core/EvidenceTraversal.php';
function execute_therapy_formulation($entity, $debugMode = false)
{
    return (new EvidenceTraversal())->run('therapy_formulation', $entity, (bool) $debugMode);
}
if (basename(__FILE__) === basename((isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : ''))) {
    EvidenceTraversal::serve('therapy_formulation');
}
