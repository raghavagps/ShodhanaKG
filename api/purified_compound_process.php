<?php

require_once __DIR__ . '/core/EvidenceTraversal.php';
function execute_purified_compound_process($entity, $debugMode = false)
{
    return (new EvidenceTraversal())->run('purified_compound_process', $entity, (bool) $debugMode);
}
if (basename(__FILE__) === basename((isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : ''))) {
    EvidenceTraversal::serve('purified_compound_process');
}
