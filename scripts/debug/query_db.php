<?php
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/graph_service.php';

$integratedEdges = edge_types_for_domain('integrated');
$uniqueRefs = array();

foreach ($integratedEdges as $et) {
    try {
        $sql = "SELECT ref, pubmedId, doi, `Pubmed ID` FROM `$et` LIMIT 10000";
        $res = arcade_command($sql, 'sql');
        $results = isset($res['result']) ? $res['result'] : array();
        foreach ($results as $row) {
            $ref = '';
            if (!empty($row['ref'])) $ref = $row['ref'];
            elseif (!empty($row['pubmedId'])) $ref = $row['pubmedId'];
            elseif (!empty($row['Pubmed ID'])) $ref = $row['Pubmed ID'];
            elseif (!empty($row['doi'])) $ref = $row['doi'];
            
            $ref = trim((string)$ref);
            // remove trailing .0 if present
            $ref = rtrim(rtrim($ref, '0'), '.');
            
            if ($ref !== '') {
                $uniqueRefs[$ref] = true;
            }
        }
    } catch (Exception $e) {}
}

echo "Total Studies: " . count($uniqueRefs) . "\n";
