<?php

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/graph_service.php';
require_once __DIR__ . '/core/SemanticQuery.php';
require_once __DIR__ . '/core/GraphRead.php';
api_handle_cors_preflight();
$method = (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '');
if ($method !== 'GET') {
    api_fail('method_not_allowed', 'Use GET', 405);
}
foreach ($_GET as $value) if (!is_string($value) || strlen($value) > 20000) api_fail('invalid_parameter', 'Invalid graph parameter', 400);
$domain = (isset($_GET['domain']) ? trim($_GET['domain']) : '');
$seedsRaw = (isset($_GET['seeds']) ? trim($_GET['seeds']) : '');
$limitNodes = min(max((isset($_GET['limitNodes']) ? (int) $_GET['limitNodes'] : 120), 1), 500);
$limitEdges = min(max((isset($_GET['limitEdges']) ? (int) $_GET['limitEdges'] : 300), 1), 2000);
$integratedMode = (isset($_GET['integratedMode']) ? trim($_GET['integratedMode']) : '1hop');
$depth = min(max((isset($_GET['depth']) ? (int) $_GET['depth'] : 1), 0), 2);
if (!in_array($domain, array('disease', 'compound', 'integrated'), true)) {
    api_fail('invalid_domain', 'Domain must be disease, compound, or integrated', 400);
}
if ($seedsRaw === '') {
    api_fail('no_seeds', 'At least one seed ID is required', 400);
}
$progressive = isset($_GET['progressive']) && $_GET['progressive'] === '1';
if ($progressive) { $limitNodes = 100000; $limitEdges = 100000; }
$seedIds = array_filter(array_map('trim', explode(',', $seedsRaw)));
function get_bridge_edges()
{
    return array('USED_FOR_TREATMENT', 'USED_IN_FORMULATION', 'TREATS');
}
function get_compound_edges()
{
    return array('UNDERGOES', 'CONTAINS', 'VALIDATED_BY', 'USES_MEDIA', 'CONFIRMS_SAFETY_OF', 'DERIEVED_FROM', 'DETOXIFIES', 'CLASSIFIED_AS', 'PRODUCES', 'FOLLOWED_BY');
}
function get_disease_edges()
{
    return array('ASSESS', 'HAS_AYURVEDIC_DIAGNOSIS', 'HAS_DOSHA_IMBALANCE', 'HAS_SYMPTOM', 'POST_TREATED_BY', 'PRE_TREATED_BY', 'RESOLVED_BY', 'TREATED_BY', 'USES_FORMULATION');
}
function get_compound_db_types()
{
    return array('Compound', 'Shodhana_Method', 'Toxic_Substance', 'Confirmation_Study', 'Media', 'Purified_Compound', 'Shodhana_Type', 'Process', 'Indication', 'Ayurvedic_Formulation');
}
function get_disease_db_types()
{
    return array('Disease', 'Dosha', 'Ayurvedic_Diagnosis', 'Symptoms', 'Therapy', 'Formulation', 'Assessment_Parameter');
}
function query_edges($frontierRids, $edgeTypes, $limit)
{
    return GraphRead::edges($frontierRids, $edgeTypes, $limit, 'arcade_command');
}
function integrate_edges($rawEdges, &$nodeMap, &$edgeMap, $limitNodes, $limitEdges, &$rawTypeMap)
{
    $newRids = array();
    foreach ($rawEdges as $e) {
        foreach (array('@out', '@in') as $side) {
            $r = (isset($e[$side]) ? $e[$side] : '');
            if ($r && !isset($nodeMap[$r])) {
                $newRids[$r] = true;
            }
        }
    }
    if (empty($newRids)) {
        _flush_edges($rawEdges, $nodeMap, $edgeMap, $limitEdges);
        return array(array(), array());
    }
    $nRidCsv = implode(',', array_keys($newRids));
    $sql = sprintf('SELECT FROM %s LIMIT %d', $nRidCsv, max(2000, $limitNodes));
    $res = arcade_command($sql, 'sql');
    $newCompound = array();
    $newDisease = array();
    $results = (isset($res['result']) ? $res['result'] : array());
    foreach ($results as $rec) {
        if (count($nodeMap) >= $limitNodes) {
            break;
        }
        $rid = (isset($rec['@rid']) ? $rec['@rid'] : '');
        if (!$rid) {
            continue;
        }
        $rawType = (isset($rec['@type']) ? $rec['@type'] : '');
        $rawTypeMap[$rid] = $rawType;
        $nodeMap[$rid] = build_node($rec);
        $compTypes = get_compound_db_types();
        if (in_array($rawType, $compTypes, true)) {
            $newCompound[] = $rid;
        } else {
            $newDisease[] = $rid;
        }
    }
    _flush_edges($rawEdges, $nodeMap, $edgeMap, $limitEdges);
    return array($newCompound, $newDisease);
}
function _flush_edges($rawEdges, &$nodeMap, &$edgeMap, $limitEdges)
{
    foreach ($rawEdges as $e) {
        if (count($edgeMap) >= $limitEdges) {
            break;
        }
        $edge = build_edge($e, $nodeMap);
        if ($edge !== null) {
            $edgeMap[$edge['id']] = $edge;
        }
    }
}
try {
    $startTime = microtime(true);
    $seedVertices = resolve_seeds($seedIds, $domain);
    if (empty($seedVertices)) {
        api_fail('seeds_not_found', 'No seed vertices could be resolved', 404);
    }
    $nodeMap = array();
    $edgeMap = array();
    $truncated = false;
    $truncReason = null;
    $evidencePaths = [];
    $continuations = [];
    if ($domain === 'integrated' && $integratedMode === 'paths') {
        foreach ($seedVertices as $seed) {
            $type = $seed['@type'];
            $targets = ($type === 'Compound' ? ['Disease', 'Indication'] : (in_array($type, ['Disease', 'Indication'], true) ? ['Compound'] : ($type === 'Purified_Compound' ? ['Disease', 'Indication'] : [])));
            $nodeMap[$seed['@rid']] = build_node($seed);
            foreach ($targets as $target) {
                $response = SemanticQuery::run($type, vertex_label($seed), $target, [], (int) (isset($_GET['pathOffset']) ? $_GET['pathOffset'] : 0), 1000);
                if ($progressive) {
                    while (!empty($response['pagination']['has_more'])) {
                        $next = SemanticQuery::run($type, vertex_label($seed), $target, [], $response['pagination']['next_offset'], 1000);
                        if ($next['status'] === 'error') throw new RuntimeException('Path continuation failed');
                        $response['results'] = array_merge($response['results'], $next['results']);
                        $response['pagination'] = $next['pagination'];
                        if (count($response['results']) >= 100000) throw new RuntimeException('Path read budget reached');
                    }
                }
                if ($response['status'] === 'error') {
                    throw new RuntimeException((isset($response['message']) ? $response['message'] : 'Path query failed'));
                }
                foreach ((isset($response['results']) ? $response['results'] : []) as $path) {
                    foreach ($path['edges'] as &$pathEdge) {
                        $pathEdge += EdgePresentation::metadata($pathEdge);
                    }
                    unset($pathEdge);
                    $evidencePaths[] = $path;
                    $newNodes = array_diff(array_column($path['nodes'], 'id'), array_keys($nodeMap));
                    $newEdges = array_diff(array_column($path['edges'], 'id'), array_keys($edgeMap));
                    if (count($nodeMap) + count($newNodes) > $limitNodes || count($edgeMap) + count($newEdges) > $limitEdges) {
                        $truncated = true;
                        $truncReason = 'graph_display_cap';
                        continue;
                    }
                    foreach ($path['nodes'] as $node) {
                        $nodeMap[$node['id']] = ['id' => $node['id'], 'label' => $node['label'], 'type' => normalize_type($node['type']), 'raw_type' => $node['type']];
                    }
                    foreach ($path['edges'] as $edge) {
                        $raw = $edge['properties'] + ['@rid' => $edge['id'], '@type' => $edge['type'], '@out' => $edge['source'], '@in' => $edge['target']];
                        $edgeMap[$edge['id']] = build_edge($raw, $nodeMap);
                    }
                }
                if (!empty($response['pagination']['has_more'])) {
                    $truncated = true;
                    $truncReason = 'path_page';
                    $continuations[] = ['source_type' => $type, 'entity' => vertex_label($seed), 'result_type' => $target, 'offset' => $response['pagination']['next_offset']];
                }
            }
        }
    } elseif ($domain === 'integrated') {
        $rawTypeMap = array();
        $compoundFront = array();
        $diseaseFront = array();
        foreach ($seedVertices as $v) {
            $rid = (isset($v['@rid']) ? $v['@rid'] : '');
            if (!$rid) {
                continue;
            }
            $rawType = (isset($v['@type']) ? $v['@type'] : '');
            $rawTypeMap[$rid] = $rawType;
            $nodeMap[$rid] = build_node($v);
            $compTypes = get_compound_db_types();
            if (in_array($rawType, $compTypes, true)) {
                $compoundFront[] = $rid;
            } else {
                $diseaseFront[] = $rid;
            }
        }
        $bridgeRaw = query_edges(array_merge($compoundFront, $diseaseFront), get_bridge_edges(), $limitEdges);
        $res = integrate_edges($bridgeRaw, $nodeMap, $edgeMap, $limitNodes, $limitEdges, $rawTypeMap);
        $nc = $res[0];
        $nd = $res[1];
        $compoundFront = array_unique(array_merge($compoundFront, $nc));
        $diseaseFront = array_unique(array_merge($diseaseFront, $nd));
        if ($integratedMode !== 'bridge') {
            $hops = 1;
            for ($h = 0; $h < $hops; $h++) {
                if (count($nodeMap) >= $limitNodes) {
                    $truncated = true;
                    $truncReason = 'node_cap';
                    break;
                }
                $newCompFront = array();
                if (!empty($compoundFront)) {
                    $edges = query_edges($compoundFront, get_compound_edges(), $limitEdges);
                    $res2 = integrate_edges($edges, $nodeMap, $edgeMap, $limitNodes, $limitEdges, $rawTypeMap);
                    $newCompFront = $res2[0];
                }
                $newDisFront = array();
                if (!empty($diseaseFront)) {
                    $edges = query_edges($diseaseFront, get_disease_edges(), $limitEdges);
                    $res3 = integrate_edges($edges, $nodeMap, $edgeMap, $limitNodes, $limitEdges, $rawTypeMap);
                    $newDisFront = $res3[1];
                }
                $compoundFront = array_unique($newCompFront);
                $diseaseFront = array_unique($newDisFront);
                if (empty($compoundFront) && empty($diseaseFront)) {
                    break;
                }
                if (count($edgeMap) >= $limitEdges) {
                    $truncated = true;
                    $truncReason = 'edge_cap';
                    break;
                }
            }
        }
    } else {
        $edgeTypes = edge_types_for_domain($domain);
        $frontier = array();
        foreach ($seedVertices as $v) {
            $rid = (isset($v['@rid']) ? $v['@rid'] : '');
            if ($rid && !isset($nodeMap[$rid])) {
                $nodeMap[$rid] = build_node($v);
                $frontier[] = $rid;
            }
        }
        for ($d = 0; $d < $depth; $d++) {
            if (empty($frontier)) {
                break;
            }
            $allEdges = query_edges($frontier, $edgeTypes, $limitEdges);
            $neighborRids = array();
            foreach ($allEdges as $e) {
                foreach (array('@out', '@in') as $side) {
                    $r = (isset($e[$side]) ? $e[$side] : '');
                    if ($r && !isset($nodeMap[$r])) {
                        $neighborRids[$r] = true;
                    }
                }
            }
            // Edges between already-loaded nodes still belong to this traversal.
            $neighborMap = array();
            foreach (array_chunk(array_keys($neighborRids), 1000) as $ridBatch) {
                $neighborMap += fetch_vertex_map(implode(',', $ridBatch));
            }
            $newFrontier = array();
            foreach ($neighborMap as $rid => $node) {
                if (count($nodeMap) >= $limitNodes) {
                    $truncated = true;
                    $truncReason = 'node_cap';
                    break;
                }
                $nodeMap[$rid] = $node;
                $newFrontier[] = $rid;
            }
            foreach ($allEdges as $e) {
                if (count($edgeMap) >= $limitEdges) {
                    $truncated = true;
                    $truncReason = 'edge_cap';
                    break;
                }
                $edge = build_edge($e, $nodeMap);
                if ($edge !== null) {
                    $edgeMap[$edge['id']] = $edge;
                }
            }
            if ($truncated) {
                break;
            }
            if (count($nodeMap) >= $limitNodes) {
                $truncated = true;
                $truncReason = 'node_cap';
                break;
            }
            if (count($edgeMap) >= $limitEdges) {
                $truncated = true;
                $truncReason = 'edge_cap';
                break;
            }
            $frontier = $newFrontier;
        }
    }
    if ($progressive) {
        require_once __DIR__ . '/core/GraphPage.php';
        if ($truncated || !empty($continuations)) api_fail('query_incomplete', 'Traversal read budget reached; narrow the query before loading all.', 422);
        $options = $_GET;
        $options['queryKey'] = $domain . ':' . $depth . ':' . $integratedMode;
        $options['evidencePaths'] = $evidencePaths;
        $page = GraphPage::render(array_values($nodeMap), array_values($edgeMap), array_column($seedVertices, '@rid'), $options);
        $page['meta']['continuations'] = $continuations;
        api_ok($page);
    }
    $elapsed = (int) ((microtime(true) - $startTime) * 1000);
    $nodesOut = array_slice(array_values($nodeMap), 0, $limitNodes);
    $edgesOut = array_slice(array_values($edgeMap), 0, $limitEdges);
    if (count($nodesOut) < count($nodeMap) || count($edgesOut) < count($edgeMap)) {
        $truncated = true;
        $truncReason = ($truncReason !== null ? $truncReason : (count($nodesOut) < count($nodeMap) ? 'node_cap' : 'edge_cap'));
    }
    api_ok(array('nodes' => $nodesOut, 'edges' => $edgesOut, 'meta' => array('depth' => ($domain === 'integrated' ? $integratedMode : $depth), 'truncated' => $truncated, 'truncatedReason' => $truncReason, 'totalNodes' => count($nodesOut), 'totalEdges' => count($edgesOut), 'queryMs' => $elapsed, 'evidencePaths' => $evidencePaths, 'continuations' => $continuations)));
} catch (InvalidArgumentException $e) {
    api_fail('invalid_graph_cursor', $e->getMessage(), 400);
} catch (Exception $e) {
    api_fail('graph_failed', 'Graph query failed', 500, array('message' => $e->getMessage()));
}
