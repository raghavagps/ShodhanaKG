<?php

require_once __DIR__ . '/core/EvidenceTraversal.php';
function execute_provenance_compound_method_media($entity, $debugMode = false)
{
    return (new EvidenceTraversal())->run('provenance_compound_method_media', $entity, (bool) $debugMode);
}
if (basename(__FILE__) === basename((isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : ''))) {
    EvidenceTraversal::serve('provenance_compound_method_media');
}
