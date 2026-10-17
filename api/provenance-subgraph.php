<?php

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/graph_service.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
api_handle_cors_preflight();
$referenceType = (isset($_GET['referenceType']) ? $_GET['referenceType'] : '');
$referenceId = (isset($_GET['referenceId']) ? $_GET['referenceId'] : '');
if (!is_string($referenceType) || !is_string($referenceId) || ($referenceType !== '' || $referenceId !== '') && (!in_array($referenceType, ['PMID', 'DOI', 'AYUSH'], true) || trim($referenceId) === '')) {
    api_fail('invalid_reference_filter', 'Supply referenceType (PMID, DOI, AYUSH) and referenceId together', 400);
}
$referenceId = trim($referenceId);
if ($referenceType === 'DOI') {
    $referenceId = strtolower($referenceId);
}
$method = (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '');
if ($method !== 'GET') {
    api_fail('method_not_allowed', 'Use GET', 405);
}
$diseaseId = (isset($_GET['diseaseId']) ? trim($_GET['diseaseId']) : '');
$pubmedId = (isset($_GET['pubmedId']) ? trim($_GET['pubmedId']) : '');
$limit = min(max((isset($_GET['limit']) ? (int) $_GET['limit'] : 2000), 1), 5000);
if ($diseaseId === '') {
    api_fail('missing_disease_id', 'diseaseId parameter is required', 400);
}
try {
    $startTime = microtime(true);
    $diseaseVertex = null;
    if (substr($diseaseId, 0, 1) === '#') {
        if (!preg_match('/^#\d+:\d+$/D', $diseaseId)) {
            api_fail('invalid_id', 'Invalid disease record ID', 400);
        }
        $sql = sprintf("SELECT FROM %s LIMIT 1", $diseaseId);
        $res = arcade_command($sql, 'sql');
        $results = (isset($res['result']) ? $res['result'] : array());
        $diseaseVertex = (isset($results[0]) ? $results[0] : null);
    } else {
        $sql = sprintf("SELECT FROM Disease WHERE id = '%s' LIMIT 1", arcade_escape($diseaseId));
        $res = arcade_command($sql, 'sql');
        $results = (isset($res['result']) ? $res['result'] : array());
        $diseaseVertex = (isset($results[0]) ? $results[0] : null);
        if (!$diseaseVertex) {
            $sql = sprintf("SELECT FROM Disease WHERE coalesce(Disease, coalesce(name, '')).toLowerCase() = '%s' LIMIT 1", strtolower(arcade_escape($diseaseId)));
            $res = arcade_command($sql, 'sql');
            $results = (isset($res['result']) ? $res['result'] : array());
            $diseaseVertex = (isset($results[0]) ? $results[0] : null);
        }
    }
    if (!$diseaseVertex || (isset($diseaseVertex['@type']) ? $diseaseVertex['@type'] : '') !== 'Disease') {
        api_fail('disease_not_found', "Disease not found: {$diseaseId}", 404);
    }
    $diseaseRid = (isset($diseaseVertex['@rid']) ? $diseaseVertex['@rid'] : '');
    if (!$diseaseRid) {
        api_fail('disease_no_rid', 'Disease vertex missing @rid', 500);
    }
    $diseaseNode = build_node($diseaseVertex);
    $diseaseToTherapyEdges = array();
    $therapyRids = array();
    $edgeTypes1 = array('PRE_TREATED_BY', 'POST_TREATED_BY', 'TREATED_BY');
    foreach ($edgeTypes1 as $edgeType) {
        $sql = sprintf("SELECT FROM `%s` WHERE @out = %s LIMIT 1000", $edgeType, $diseaseRid);
        try {
            $res = arcade_command($sql, 'sql');
            $results = (isset($res['result']) ? $res['result'] : array());
            foreach ($results as $edge) {
                $therapyRid = (isset($edge['@in']) ? $edge['@in'] : '');
                $props = EvidenceCorrelator::extractIdentifiers($edge);
                if ($pubmedId !== '' && $props['pubmed_id'] !== $pubmedId) {
                    continue;
                }
                if ($therapyRid && ($props['pubmed_id'] !== '' || $props['doi'] !== '' || $props['ayush_id'] !== '')) {
                    $diseaseToTherapyEdges[] = array('edge' => $edge, 'therapyRid' => $therapyRid, 'props' => $props, 'edgeType' => $edgeType);
                    $therapyRids[$therapyRid] = true;
                }
            }
        } catch (Exception $e) {
        }
    }
    if (empty($diseaseToTherapyEdges)) {
        api_ok(array('paths' => array(), 'meta' => array('totalPaths' => 0, 'uniquePubmedIds' => array(), 'queryMs' => (int) ((microtime(true) - $startTime) * 1000), 'diseaseId' => $diseaseId)));
    }
    $therapyToFormulationEdges = array();
    $formulationRids = array();
    if (!empty($therapyRids)) {
        $therapyRidList = implode(',', array_keys($therapyRids));
        $sql = sprintf("SELECT FROM USES_FORMULATION WHERE @out IN [%s] LIMIT 5000", $therapyRidList);
        try {
            $res = arcade_command($sql, 'sql');
            $results = (isset($res['result']) ? $res['result'] : array());
            foreach ($results as $edge) {
                $therapyRid = (isset($edge['@out']) ? $edge['@out'] : '');
                $formulationRid = (isset($edge['@in']) ? $edge['@in'] : '');
                $props = EvidenceCorrelator::extractIdentifiers($edge);
                if ($pubmedId !== '' && $props['pubmed_id'] !== $pubmedId) {
                    continue;
                }
                if ($therapyRid && $formulationRid && ($props['pubmed_id'] !== '' || $props['doi'] !== '' || $props['ayush_id'] !== '')) {
                    if (!isset($therapyToFormulationEdges[$therapyRid])) {
                        $therapyToFormulationEdges[$therapyRid] = array();
                    }
                    $therapyToFormulationEdges[$therapyRid][] = array('edge' => $edge, 'formulationRid' => $formulationRid, 'props' => $props);
                    $formulationRids[$formulationRid] = true;
                }
            }
        } catch (Exception $e) {
        }
    }
    $therapyMap = array();
    $formulationMap = array();
    if (!empty($therapyRids)) {
        $therapyMap = fetch_vertex_map(implode(',', array_keys($therapyRids)));
    }
    if (!empty($formulationRids)) {
        $formulationMap = fetch_vertex_map(implode(',', array_keys($formulationRids)));
    }
    $paths = array();
    $seenPmids = array();
    $seenReferences = array();
    $pathCount = 0;
    foreach ($diseaseToTherapyEdges as $dtEdge) {
        if ($pathCount >= $limit) {
            break;
        }
        $therapyRid = $dtEdge['therapyRid'];
        $props1 = $dtEdge['props'];
        if (!isset($therapyToFormulationEdges[$therapyRid])) {
            continue;
        }
        foreach ($therapyToFormulationEdges[$therapyRid] as $tfEdge) {
            if ($pathCount >= $limit) {
                break;
            }
            $props2 = $tfEdge['props'];
            if (!EvidenceCorrelator::correlate($props1, $props2)) {
                continue;
            }
            $therapyNode = (isset($therapyMap[$therapyRid]) ? $therapyMap[$therapyRid] : null);
            $formulationNode = (isset($formulationMap[$tfEdge['formulationRid']]) ? $formulationMap[$tfEdge['formulationRid']] : null);
            if (!$therapyNode || !$formulationNode) {
                continue;
            }
            $edge1Props = map_edge_props($dtEdge['edge']);
            $edge2Props = map_edge_props($tfEdge['edge']);
            $match = EvidenceCorrelator::matchingIdentifier($props1, $props2);
            $filterNamespace = $referenceType;
            $filterId = $referenceId;
            $typeMap = ['PMID' => 'pubmed_id', 'DOI' => 'doi', 'AYUSH' => 'ayush_id'];
            if ($filterId !== '' && ($match['type'] !== (isset($typeMap[$filterNamespace]) ? $typeMap[$filterNamespace] : null) || $match['value'] !== $filterId)) {
                continue;
            }
            $ref = ($props1['pubmed_id'] !== '' ? $props1['pubmed_id'] : ($props1['doi'] !== '' ? $props1['doi'] : $props1['ayush_id']));
            $paths[] = array('disease' => array('id' => $diseaseNode['id'], 'label' => $diseaseNode['label'], 'type' => $diseaseNode['type'], 'description' => (isset($diseaseNode['description']) ? $diseaseNode['description'] : null)), 'edge1' => array('id' => (isset($dtEdge['edge']['@rid']) ? $dtEdge['edge']['@rid'] : ''), 'type' => normalize_edge_type($dtEdge['edgeType']), 'pubmedId' => ($props1['pubmed_id'] !== '' ? $props1['pubmed_id'] : null), 'references' => $props1['references'], 'source' => $dtEdge['edge']['@out'], 'target' => $dtEdge['edge']['@in'], 'raw_type' => $dtEdge['edge']['@type'], 'properties' => $edge1Props['properties'], 'display_properties' => $edge1Props['display_properties'], 'treatment_phase' => $edge1Props['treatment_phase'], 'doi' => ($props1['doi'] !== '' ? $props1['doi'] : null), 'ayushId' => ($props1['ayush_id'] !== '' ? $props1['ayush_id'] : null)), 'therapy' => array('id' => $therapyNode['id'], 'label' => $therapyNode['label'], 'type' => $therapyNode['type'], 'description' => (isset($therapyNode['description']) ? $therapyNode['description'] : null)), 'edge2' => array('id' => (isset($tfEdge['edge']['@rid']) ? $tfEdge['edge']['@rid'] : ''), 'type' => 'USES_FORMULATION', 'pubmedId' => ($props2['pubmed_id'] !== '' ? $props2['pubmed_id'] : null), 'references' => $props2['references'], 'source' => $tfEdge['edge']['@out'], 'target' => $tfEdge['edge']['@in'], 'raw_type' => $tfEdge['edge']['@type'], 'properties' => $edge2Props['properties'], 'display_properties' => $edge2Props['display_properties'], 'treatment_phase' => $edge2Props['treatment_phase'], 'doi' => ($props2['doi'] !== '' ? $props2['doi'] : null), 'ayushId' => ($props2['ayush_id'] !== '' ? $props2['ayush_id'] : null), 'dose' => (isset($edge2Props['dose']) ? $edge2Props['dose'] : null), 'duration' => (isset($edge2Props['duration']) ? $edge2Props['duration'] : null), 'adjuvant' => (isset($edge2Props['adjuvant']) ? $edge2Props['adjuvant'] : null)), 'formulation' => array('id' => $formulationNode['id'], 'label' => $formulationNode['label'], 'type' => $formulationNode['type'], 'description' => (isset($formulationNode['description']) ? $formulationNode['description'] : null)), 'provenance' => array('pubmedId' => ($props1['pubmed_id'] ?  : null), 'reference' => $ref, 'references' => $props1['references'], 'confidence' => 'high'));
            if ($props1['pubmed_id'] !== '') {
                $seenPmids[$props1['pubmed_id']] = true;
            }
            foreach ($props1['references'] as $reference) {
                if ($reference['namespace']) {
                    $seenReferences[$reference['namespace'] . ':' . $reference['identifier']] = $reference;
                }
            }
            $pathCount++;
        }
    }
    $elapsed = (int) ((microtime(true) - $startTime) * 1000);
    api_ok(array('paths' => $paths, 'meta' => array('totalPaths' => count($paths), 'uniquePubmedIds' => array_keys($seenPmids), 'uniqueReferences' => array_values($seenReferences), 'queryMs' => $elapsed, 'diseaseId' => $diseaseId)));
} catch (Exception $e) {
    api_fail('provenance_query_failed', 'Failed to extract provenance subgraph', 500, array('message' => $e->getMessage()));
}
