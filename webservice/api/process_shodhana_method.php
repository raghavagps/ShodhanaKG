<?php

require_once __DIR__ . '/core/EvidenceTraversal.php';
function execute_process_shodhana_method($entity, $debugMode = false)
{
    return (new EvidenceTraversal())->run('process_shodhana_method', $entity, (bool) $debugMode);
}
if (basename(__FILE__) === basename((isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : ''))) {
    EvidenceTraversal::serve('process_shodhana_method');
}
