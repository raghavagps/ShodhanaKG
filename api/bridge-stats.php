<?php

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/graph_service.php';
api_handle_cors_preflight();
$method = (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '');
if ($method !== 'GET') {
    api_fail('method_not_allowed', 'Use GET', 405);
}
$limit = min(max((isset($_GET['limit']) ? (int) $_GET['limit'] : 50), 1), 200);
$offset = max((isset($_GET['offset']) ? (int) $_GET['offset'] : 0), 0);
try {
    // Fetch only PurifiedCompounds that have direct disease bridge (USED_FOR_TREATMENT)
    $sql = "SELECT @rid, @type, id, name, description FROM Purified_Compound WHERE out('USED_FOR_TREATMENT').size() > 0 ORDER BY name ASC LIMIT {$limit} SKIP {$offset}";
    $res = arcade_command($sql, 'sql');
    $purifiedNodes = (isset($res['result']) ? $res['result'] : array());
    $totalSql = "SELECT COUNT(*) AS cnt FROM Purified_Compound WHERE out('USED_FOR_TREATMENT').size() > 0";
    $totalRes = arcade_command($totalSql, 'sql');
    $totalResults = (isset($totalRes['result']) ? $totalRes['result'] : array());
    $total = (int) (isset($totalResults[0]['cnt']) ? $totalResults[0]['cnt'] : 0);
    if (empty($purifiedNodes)) {
        api_ok(array('rows' => array(), 'total' => $total, 'limit' => $limit, 'offset' => $offset));
    }
    $pcRids = array();
    foreach ($purifiedNodes as $n) {
        if (isset($n['@rid'])) {
            $pcRids[] = $n['@rid'];
        }
    }
    $pcRidCsv = implode(',', $pcRids);
    // Stored direction: Purified_Compound -> Compound. Keep record identities.
    $edgeRecords = [];
    $compoundMap = array();
    // pcRid => [comRid, ...]
    if ($pcRidCsv) {
        $sql = sprintf("SELECT FROM DERIEVED_FROM WHERE @out IN [%s]", $pcRidCsv);
        try {
            $res = arcade_command($sql, 'sql');
            $rows = (isset($res['result']) ? $res['result'] : array());
            foreach ($rows as $row) {
                $pcRid = (isset($row['@out']) ? $row['@out'] : '');
                $comRid = (isset($row['@in']) ? $row['@in'] : '');
                $edgeRecords[$pcRid][] = $row;
                if ($pcRid && $comRid) {
                    if (!isset($compoundMap[$pcRid])) {
                        $compoundMap[$pcRid] = array();
                    }
                    $compoundMap[$pcRid][] = $comRid;
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
    }
    // Fetch USED_FOR_TREATMENT edges where @out = PurifiedCompound
    $diseaseMap = array();
    // pcRid => [diseaseRid, ...]
    if ($pcRidCsv) {
        $sql = sprintf("SELECT FROM USED_FOR_TREATMENT WHERE @out IN [%s]", $pcRidCsv);
        try {
            $res = arcade_command($sql, 'sql');
            $rows = (isset($res['result']) ? $res['result'] : array());
            foreach ($rows as $row) {
                $pcRid = (isset($row['@out']) ? $row['@out'] : '');
                $disRid = (isset($row['@in']) ? $row['@in'] : '');
                $edgeRecords[$pcRid][] = $row;
                if ($pcRid && $disRid) {
                    if (!isset($diseaseMap[$pcRid])) {
                        $diseaseMap[$pcRid] = array();
                    }
                    $diseaseMap[$pcRid][] = $disRid;
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
    }
    // Fetch USED_IN_FORMULATION edges where @out = PurifiedCompound
    $formulationMap = array();
    // pcRid => [formRid, ...]
    if ($pcRidCsv) {
        $sql = sprintf("SELECT FROM USED_IN_FORMULATION WHERE @out IN [%s]", $pcRidCsv);
        try {
            $res = arcade_command($sql, 'sql');
            $rows = (isset($res['result']) ? $res['result'] : array());
            foreach ($rows as $row) {
                $pcRid = (isset($row['@out']) ? $row['@out'] : '');
                $formRid = (isset($row['@in']) ? $row['@in'] : '');
                $edgeRecords[$pcRid][] = $row;
                if ($pcRid && $formRid) {
                    if (!isset($formulationMap[$pcRid])) {
                        $formulationMap[$pcRid] = array();
                    }
                    $formulationMap[$pcRid][] = $formRid;
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
    }
    // Collect all neighbor RIDs and fetch their labels
    $allNeighborRids = array();
    foreach ($compoundMap as $rids) {
        foreach ($rids as $r) {
            $allNeighborRids[$r] = true;
        }
    }
    foreach ($diseaseMap as $rids) {
        foreach ($rids as $r) {
            $allNeighborRids[$r] = true;
        }
    }
    foreach ($formulationMap as $rids) {
        foreach ($rids as $r) {
            $allNeighborRids[$r] = true;
        }
    }
    $neighborLabels = array();
    $neighborMap = [];
    if (!empty($allNeighborRids)) {
        $nRidCsv = implode(',', array_keys($allNeighborRids));
        $neighborMap = fetch_vertex_map($nRidCsv);
        foreach ($neighborMap as $rid => $node) {
            $neighborLabels[$rid] = $node['label'];
        }
    }
    $rows = array();
    foreach ($purifiedNodes as $pc) {
        $neighborMap[$pc['@rid']] = build_node($pc);
    }
    foreach ($purifiedNodes as $pc) {
        $rid = (isset($pc['@rid']) ? $pc['@rid'] : '');
        $comRids = (isset($compoundMap[$rid]) ? $compoundMap[$rid] : array());
        $disRids = (isset($diseaseMap[$rid]) ? $diseaseMap[$rid] : array());
        $formRids = (isset($formulationMap[$rid]) ? $formulationMap[$rid] : array());
        $compoundLabels = array();
        foreach ($comRids as $r) {
            $compoundLabels[] = (isset($neighborLabels[$r]) ? $neighborLabels[$r] : $r);
        }
        $compoundLabels = array_values(array_unique($compoundLabels));
        $diseaseLabels = array();
        foreach ($disRids as $r) {
            $diseaseLabels[] = (isset($neighborLabels[$r]) ? $neighborLabels[$r] : $r);
        }
        $diseaseLabels = array_values(array_unique($diseaseLabels));
        $formulationLabels = array();
        foreach ($formRids as $r) {
            $formulationLabels[] = (isset($neighborLabels[$r]) ? $neighborLabels[$r] : $r);
        }
        $formulationLabels = array_values(array_unique($formulationLabels));
        $studies = [];
        $edges = [];
        foreach ((isset($edgeRecords[$rid]) ? $edgeRecords[$rid] : []) as $edge) {
            $dto = build_edge($edge, $neighborMap);
            if ($dto) {
                $edges[] = $dto;
            }
            foreach (EvidenceCorrelator::extractIdentifiers($edge)['references'] as $ref) {
                if ($ref['namespace'] && in_array($ref['status'], ['verified', 'declared'], true)) {
                    $studies[$ref['namespace'] . ':' . $ref['identifier']] = true;
                }
            }
        }
        $rows[] = array('purifiedId' => $rid, 'purifiedLabel' => trim((string) (isset($pc['name']) ? $pc['name'] : '')), 'purifiedDescription' => vertex_description($pc), 'compoundIds' => array_values(array_unique($comRids)), 'compoundLabels' => $compoundLabels, 'diseaseCount' => count(array_unique($disRids)), 'formulationCount' => count(array_unique($formRids)), 'edges' => $edges, 'studies' => count($studies), 'studiesScope' => 'Distinct classified identifiers on the returned direct edges; not scientific validation', 'diseaseLabels' => $diseaseLabels, 'formulationLabels' => $formulationLabels);
    }
    api_ok(array('rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset));
} catch (Exception $e) {
    api_fail('bridge_stats_failed', 'Bridge stats query failed', 500, array('message' => $e->getMessage()));
}
