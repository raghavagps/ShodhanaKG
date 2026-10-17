<?php

require_once __DIR__ . '/core/EvidenceTraversal.php';
function execute_media_process($entity, $debugMode = false)
{
    return (new EvidenceTraversal())->run('media_process', $entity, (bool) $debugMode);
}
if (basename(__FILE__) === basename((isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : ''))) {
    EvidenceTraversal::serve('media_process');
}
