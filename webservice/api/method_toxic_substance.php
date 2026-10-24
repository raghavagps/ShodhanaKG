<?php

require_once __DIR__ . '/core/EvidenceTraversal.php';
function execute_method_toxic_substance($entity, $debugMode = false)
{
    return (new EvidenceTraversal())->run('method_toxic_substance', $entity, (bool) $debugMode);
}
if (basename(__FILE__) === basename((isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : ''))) {
    EvidenceTraversal::serve('method_toxic_substance');
}
