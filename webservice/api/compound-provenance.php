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
$compoundId = (isset($_GET['compoundId']) ? trim($_GET['compoundId']) : '');
$limit = min(max((isset($_GET['limit']) ? (int) $_GET['limit'] : 2000), 1), 5000);
if ($compoundId === '') {
    api_fail('missing_compound_id', 'compoundId parameter is required', 400);
}
try {
    $startTime = microtime(true);
    $compoundVertex = null;
    if (substr($compoundId, 0, 1) === '#') {
        if (!preg_match('/^#\d+:\d+$/D', $compoundId)) {
            api_fail('invalid_id', 'Invalid compound record ID', 400);
        }
        $sql = sprintf("SELECT FROM %s LIMIT 1", $compoundId);
        $res = arcade_command($sql, 'sql');
        $results = (isset($res['result']) ? $res['result'] : array());
        $compoundVertex = (isset($results[0]) ? $results[0] : null);
    } else {
        $sql = sprintf("SELECT FROM Compound WHERE id = '%s' LIMIT 1", arcade_escape($compoundId));
        $res = arcade_command($sql, 'sql');
        $results = (isset($res['result']) ? $res['result'] : array());
        $compoundVertex = (isset($results[0]) ? $results[0] : null);
        if (!$compoundVertex) {
            $sql = sprintf("SELECT FROM Compound WHERE coalesce(name, '').toLowerCase() = '%s' LIMIT 1", strtolower(arcade_escape($compoundId)));
            $res = arcade_command($sql, 'sql');
            $results = (isset($res['result']) ? $res['result'] : array());
            $compoundVertex = (isset($results[0]) ? $results[0] : null);
        }
    }
    if (!$compoundVertex || (isset($compoundVertex['@type']) ? $compoundVertex['@type'] : '') !== 'Compound') {
        api_fail('compound_not_found', "Compound not found: {$compoundId}", 404);
    }
    $compoundRid = (isset($compoundVertex['@rid']) ? $compoundVertex['@rid'] : '');
    if (!$compoundRid) {
        api_fail('compound_no_rid', 'Compound vertex missing @rid', 500);
    }
    $compoundNode = build_node($compoundVertex);
    $compoundToMethodEdges = array();
    $methodRids = array();
    $sql = sprintf("SELECT FROM UNDERGOES WHERE @out = %s LIMIT 1000", $compoundRid);
    try {
        $res = arcade_command($sql, 'sql');
        $results = (isset($res['result']) ? $res['result'] : array());
        foreach ($results as $edge) {
            $methodRid = (isset($edge['@in']) ? $edge['@in'] : '');
            $props = EvidenceCorrelator::extractIdentifiers($edge);
            if ($methodRid && ($props['pubmed_id'] !== '' || $props['doi'] !== '' || $props['ayush_id'] !== '')) {
                $compoundToMethodEdges[] = array('edge' => $edge, 'methodRid' => $methodRid, 'props' => $props);
                $methodRids[$methodRid] = true;
            }
        }
    } catch (Exception $e) {
    }
    if (empty($compoundToMethodEdges)) {
        api_ok(array('paths' => array(), 'meta' => array('totalPaths' => 0, 'uniquePubmedIds' => array(), 'queryMs' => (int) ((microtime(true) - $startTime) * 1000), 'compoundId' => $compoundId)));
    }
    $methodToMediaEdges = array();
    $mediaRids = array();
    if (!empty($methodRids)) {
        $methodRidList = implode(',', array_keys($methodRids));
        $sql = sprintf("SELECT FROM USES_MEDIA WHERE @out IN [%s] LIMIT 5000", $methodRidList);
        try {
            $res = arcade_command($sql, 'sql');
            $results = (isset($res['result']) ? $res['result'] : array());
            foreach ($results as $edge) {
                $methodRid = (isset($edge['@out']) ? $edge['@out'] : '');
                $mediaRid = (isset($edge['@in']) ? $edge['@in'] : '');
                $props = EvidenceCorrelator::extractIdentifiers($edge);
                if ($methodRid && $mediaRid && ($props['pubmed_id'] !== '' || $props['doi'] !== '' || $props['ayush_id'] !== '')) {
                    if (!isset($methodToMediaEdges[$methodRid])) {
                        $methodToMediaEdges[$methodRid] = array();
                    }
                    $methodToMediaEdges[$methodRid][] = array('edge' => $edge, 'mediaRid' => $mediaRid, 'props' => $props);
                    $mediaRids[$mediaRid] = true;
                }
            }
        } catch (Exception $e) {
        }
    }
    $methodMap = array();
    $mediaMap = array();
    if (!empty($methodRids)) {
        $methodMap = fetch_vertex_map(implode(',', array_keys($methodRids)));
    }
    if (!empty($mediaRids)) {
        $mediaMap = fetch_vertex_map(implode(',', array_keys($mediaRids)));
    }
    $paths = array();
    $seenPmids = array();
    $seenReferences = array();
    $pathCount = 0;
    foreach ($compoundToMethodEdges as $cmEdge) {
        if ($pathCount >= $limit) {
            break;
        }
        $methodRid = $cmEdge['methodRid'];
        $props1 = $cmEdge['props'];
        if (!isset($methodToMediaEdges[$methodRid])) {
            continue;
        }
        foreach ($methodToMediaEdges[$methodRid] as $mmEdge) {
            if ($pathCount >= $limit) {
                break;
            }
            $props2 = $mmEdge['props'];
            if (!EvidenceCorrelator::correlate($props1, $props2)) {
                continue;
            }
            $methodNode = (isset($methodMap[$methodRid]) ? $methodMap[$methodRid] : null);
            $mediaNode = (isset($mediaMap[$mmEdge['mediaRid']]) ? $mediaMap[$mmEdge['mediaRid']] : null);
            if (!$methodNode || !$mediaNode) {
                continue;
            }
            $edge1Props = map_edge_props($cmEdge['edge']);
            $edge2Props = map_edge_props($mmEdge['edge']);
            $match = EvidenceCorrelator::matchingIdentifier($props1, $props2);
            $filterNamespace = $referenceType;
            $filterId = $referenceId;
            $typeMap = ['PMID' => 'pubmed_id', 'DOI' => 'doi', 'AYUSH' => 'ayush_id'];
            if ($filterId !== '' && ($match['type'] !== (isset($typeMap[$filterNamespace]) ? $typeMap[$filterNamespace] : null) || $match['value'] !== $filterId)) {
                continue;
            }
            $ref = ($props1['pubmed_id'] !== '' ? $props1['pubmed_id'] : ($props1['doi'] !== '' ? $props1['doi'] : $props1['ayush_id']));
            $paths[] = array('compound' => array('id' => $compoundNode['id'], 'label' => $compoundNode['label'], 'type' => $compoundNode['type'], 'description' => (isset($compoundNode['description']) ? $compoundNode['description'] : null)), 'edge1' => array('id' => (isset($cmEdge['edge']['@rid']) ? $cmEdge['edge']['@rid'] : ''), 'type' => 'UNDERGOES', 'pubmedId' => ($props1['pubmed_id'] !== '' ? $props1['pubmed_id'] : null), 'references' => $props1['references'], 'source' => $cmEdge['edge']['@out'], 'target' => $cmEdge['edge']['@in'], 'raw_type' => $cmEdge['edge']['@type'], 'properties' => $edge1Props['properties'], 'display_properties' => $edge1Props['display_properties'], 'treatment_phase' => $edge1Props['treatment_phase'], 'doi' => ($props1['doi'] !== '' ? $props1['doi'] : null), 'ayushId' => ($props1['ayush_id'] !== '' ? $props1['ayush_id'] : null), 'procedure' => (isset($edge1Props['procedure']) ? $edge1Props['procedure'] : null)), 'shodhanaMethod' => array('id' => $methodNode['id'], 'label' => $methodNode['label'], 'type' => $methodNode['type'], 'description' => (isset($methodNode['description']) ? $methodNode['description'] : null)), 'edge2' => array('id' => (isset($mmEdge['edge']['@rid']) ? $mmEdge['edge']['@rid'] : ''), 'type' => 'USES_MEDIA', 'pubmedId' => ($props2['pubmed_id'] !== '' ? $props2['pubmed_id'] : null), 'references' => $props2['references'], 'source' => $mmEdge['edge']['@out'], 'target' => $mmEdge['edge']['@in'], 'raw_type' => $mmEdge['edge']['@type'], 'properties' => $edge2Props['properties'], 'display_properties' => $edge2Props['display_properties'], 'treatment_phase' => $edge2Props['treatment_phase'], 'doi' => ($props2['doi'] !== '' ? $props2['doi'] : null), 'ayushId' => ($props2['ayush_id'] !== '' ? $props2['ayush_id'] : null), 'procedure' => (isset($edge2Props['procedure']) ? $edge2Props['procedure'] : null), 'result' => (isset($edge2Props['result']) ? $edge2Props['result'] : null)), 'media' => array('id' => $mediaNode['id'], 'label' => $mediaNode['label'], 'type' => $mediaNode['type'], 'description' => (isset($mediaNode['description']) ? $mediaNode['description'] : null)), 'provenance' => array('pubmedId' => ($props1['pubmed_id'] ?  : null), 'reference' => $ref, 'references' => $props1['references'], 'confidence' => 'high'));
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
    api_ok(array('paths' => $paths, 'meta' => array('totalPaths' => count($paths), 'uniquePubmedIds' => array_keys($seenPmids), 'uniqueReferences' => array_values($seenReferences), 'queryMs' => $elapsed, 'compoundId' => $compoundId)));
} catch (Exception $e) {
    api_fail('provenance_query_failed', 'Failed to extract compound provenance subgraph', 500, array('message' => $e->getMessage()));
}
