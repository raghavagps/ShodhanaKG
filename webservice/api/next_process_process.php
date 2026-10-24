<?php

require_once __DIR__ . '/core/EvidenceTraversal.php';
function execute_next_process_process($entity, $debugMode = false)
{
    return (new EvidenceTraversal())->run('next_process_process', $entity, (bool) $debugMode);
}
if (basename(__FILE__) === basename((isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : ''))) {
    EvidenceTraversal::serve('next_process_process');
}
