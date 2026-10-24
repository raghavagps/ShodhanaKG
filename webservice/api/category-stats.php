<?php

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/graph_service.php';
api_handle_cors_preflight();
$method = (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '');
if ($method !== 'GET') {
    if (php_sapi_name() === 'cli' && empty($method)) {
        return;
    }
    api_fail('method_not_allowed', 'Use GET', 405);
}
$domain = (isset($_GET['domain']) ? trim($_GET['domain']) : '');
$category = (isset($_GET['category']) ? trim($_GET['category']) : '');
$q = (isset($_GET['q']) ? trim($_GET['q']) : '');
$sort = (isset($_GET['sort']) ? trim($_GET['sort']) : 'name');
$dirParam = (isset($_GET['dir']) ? strtolower(trim($_GET['dir'])) : 'asc');
$dir = ($dirParam === 'desc' ? 'DESC' : 'ASC');
$limit = min(max((isset($_GET['limit']) ? (int) $_GET['limit'] : 25), 1), 100);
$offset = max((isset($_GET['offset']) ? (int) $_GET['offset'] : 0), 0);
if (!in_array($domain, array('disease', 'compound', 'integrated'), true)) {
    api_fail('invalid_domain', 'Domain must be disease, compound, or integrated', 400);
}
$vertexMap = array('Disease' => 'Disease', 'Symptom' => 'Symptom', 'AyurvedicDiagnosis' => 'Ayurvedic_Diagnosis', 'Dosha' => 'Dosha', 'Therapy' => 'Therapy', 'Formulation' => 'Formulation', 'AssessmentParameter' => 'Assessment_Parameter', 'Compound' => 'Compound', 'ShodhanaMethod' => 'Shodhana_Method', 'ToxicSubstance' => 'Toxic_Substance', 'PurifiedCompound' => 'Purified_Compound', 'ShodhanaType' => 'Shodhana_Type', 'Media' => 'Media', 'ConfirmationStudy' => 'Confirmation_Study', 'Process' => 'Process', 'Indication' => 'Indication', 'AyurvedicFormulation' => 'Ayurvedic_Formulation');
$edgeConfig = array('Disease' => array(array('edge' => 'HAS_AYURVEDIC_DIAGNOSIS', 'key' => 'ayurvedicDiagnosis'), array('edge' => 'HAS_DOSHA_IMBALANCE', 'key' => 'doshas'), array('edge' => 'TREATED_BY', 'key' => 'therapy'), array('edge' => 'PRE_TREATED_BY', 'key' => 'therapy'), array('edge' => 'POST_TREATED_BY', 'key' => 'therapy'), array('edge' => 'ASSESS', 'key' => 'assessmentParameters', 'reverse' => true), array('edge' => 'HAS_SYMPTOM', 'key' => 'symptoms'), array('edge' => 'USED_FOR_TREATMENT', 'key' => 'purifiedCompounds', 'reverse' => true)), 'Symptom' => array(array('edge' => 'HAS_SYMPTOM', 'key' => 'diseases', 'reverse' => true)), 'Ayurvedic_Diagnosis' => array(array('edge' => 'HAS_AYURVEDIC_DIAGNOSIS', 'key' => 'diseases', 'reverse' => true)), 'Dosha' => array(array('edge' => 'HAS_DOSHA_IMBALANCE', 'key' => 'diseases', 'reverse' => true)), 'Therapy' => array(array('edge' => 'TREATED_BY', 'key' => 'diseases', 'reverse' => true), array('edge' => 'PRE_TREATED_BY', 'key' => 'diseases', 'reverse' => true), array('edge' => 'POST_TREATED_BY', 'key' => 'diseases', 'reverse' => true), array('edge' => 'USES_FORMULATION', 'key' => 'formulations')), 'Formulation' => array(array('edge' => 'USES_FORMULATION', 'key' => 'therapies', 'reverse' => true)), 'Assessment_Parameter' => array(array('edge' => 'ASSESS', 'key' => 'diseases')), 'Compound' => array(array('edge' => 'UNDERGOES', 'key' => 'shodhanaMethods'), array('edge' => 'CONTAINS', 'key' => 'toxicSubstances'), array('edge' => 'DERIEVED_FROM', 'key' => 'purifiedCompounds', 'reverse' => true)), 'Shodhana_Method' => array(array('edge' => 'PRODUCES', 'key' => 'purifiedCompounds'), array('edge' => 'USES_MEDIA', 'key' => 'media'), array('edge' => 'FOLLOWED_BY', 'key' => 'processes'), array('edge' => 'VALIDATED_BY', 'key' => 'confirmationStudies'), array('edge' => 'DETOXIFIES', 'key' => 'toxicSubstances'), array('edge' => 'CLASSIFIED_AS', 'key' => 'shodhanaTypes'), array('edge' => 'UNDERGOES', 'key' => 'compounds', 'reverse' => true)), 'Toxic_Substance' => array(array('edge' => 'CONTAINS', 'key' => 'compounds', 'reverse' => true), array('edge' => 'DETOXIFIES', 'key' => 'shodhanaMethods', 'reverse' => true)), 'Purified_Compound' => array(array('edge' => 'DERIEVED_FROM', 'key' => 'compounds'), array('edge' => 'USED_FOR_TREATMENT', 'key' => 'diseases'), array('edge' => 'USED_IN_FORMULATION', 'key' => 'ayurvedicFormulations'), array('edge' => 'TREATS', 'key' => 'indications'), array('edge' => 'PRODUCES', 'key' => 'processes', 'reverse' => true, 'sourceType' => 'Process'), array('edge' => 'PRODUCES', 'key' => 'shodhanaMethods', 'reverse' => true, 'sourceType' => 'Shodhana_Method'), array('edge' => 'CONFIRMS_SAFETY_OF', 'key' => 'confirmationStudies', 'reverse' => true)), 'Shodhana_Type' => array(array('edge' => 'CLASSIFIED_AS', 'key' => 'shodhanaMethods', 'reverse' => true)), 'Media' => array(array('edge' => 'USES_MEDIA', 'key' => 'shodhanaMethods', 'reverse' => true, 'sourceType' => 'Shodhana_Method'), array('edge' => 'USES_MEDIA', 'key' => 'processes', 'reverse' => true, 'sourceType' => 'Process')), 'Confirmation_Study' => array(array('edge' => 'VALIDATED_BY', 'key' => 'shodhanaMethods', 'reverse' => true, 'sourceType' => 'Shodhana_Method'), array('edge' => 'VALIDATED_BY', 'key' => 'processes', 'reverse' => true, 'sourceType' => 'Process'), array('edge' => 'CONFIRMS_SAFETY_OF', 'key' => 'purifiedCompounds')), 'Process' => array(array('edge' => 'PRODUCES', 'key' => 'purifiedCompounds'), array('edge' => 'USES_MEDIA', 'key' => 'media'), array('edge' => 'VALIDATED_BY', 'key' => 'confirmationStudies'), array('edge' => 'FOLLOWED_BY', 'key' => 'nextProcesses'), array('edge' => 'FOLLOWED_BY', 'key' => 'previousSteps', 'reverse' => true, 'sourceType' => 'Process'), array('edge' => 'FOLLOWED_BY', 'key' => 'shodhanaMethods', 'reverse' => true, 'sourceType' => 'Shodhana_Method')), 'Indication' => array(array('edge' => 'TREATS', 'key' => 'purifiedCompounds', 'reverse' => true)), 'Ayurvedic_Formulation' => array(array('edge' => 'USED_IN_FORMULATION', 'key' => 'purifiedCompounds', 'reverse' => true)));
if (!isset($vertexMap[$category])) {
    $matched = null;
    foreach ($vertexMap as $key => $val) {
        if (strcasecmp($key, $category) === 0) {
            $matched = $key;
            break;
        }
    }
    if (!$matched) {
        api_fail('invalid_category', "Category '{$category}' is not valid", 400);
    }
    $category = $matched;
}
$vertexType = $vertexMap[$category];
$edges = (isset($edgeConfig[$vertexType]) ? $edgeConfig[$vertexType] : array());
try {
    $force = !empty($_GET['refresh']) || !empty($_GET['nocache']);
    $allRows = get_all_category_rows($vertexType, $q, $edges, $force);
    $total = count($allRows);
    $rows = sort_and_slice_rows($allRows, $sort, $dir, $limit, $offset);
    api_ok(array('rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset));
} catch (Exception $e) {
    api_fail('category_stats_failed', 'Category stats query failed', 500, array('message' => $e->getMessage()));
}
function count_entities($vertexType, $q)
{
    $where = build_search_where_stats_cat($vertexType, $q);
    $sql = "SELECT COUNT(*) AS cnt FROM `{$vertexType}` {$where}";
    $res = arcade_command($sql, 'sql');
    $results = (isset($res['result']) ? $res['result'] : array());
    return (int) (isset($results[0]['cnt']) ? $results[0]['cnt'] : 0);
}
function build_search_where_stats_cat($vertexType, $q)
{
    if ($q === '') {
        return '';
    }
    $escaped = strtolower(arcade_escape($q));
    $conds = array();
    $fields = search_fields_for_type($vertexType);
    foreach ($fields as $f) {
        $col = (strpos($f, ' ') !== false || strpos($f, '.') !== false ? "`{$f}`" : $f);
        $conds[] = sprintf("coalesce(%s, '').toLowerCase() LIKE '%%%s%%'", $col, $escaped);
    }
    if (empty($conds)) {
        return '';
    }
    return 'WHERE ' . implode(' OR ', $conds);
}
function get_all_category_rows($vertexType, $q, $edges, $force = false)
{
    global $API_CACHE_DIR;
    $cacheKey = 'cat_stats_' . md5($vertexType . '|' . $q);
    $ttl = 600;

    if (!$force && function_exists('apcu_fetch')) {
        $hit = false;
        $val = apcu_fetch($cacheKey, $hit);
        if ($hit && is_array($val)) {
            return $val;
        }
    }

    $cacheDir = !empty($API_CACHE_DIR) ? $API_CACHE_DIR : (__DIR__ . '/storage/cache');
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    if (!is_writable($cacheDir)) {
        $cacheDir = sys_get_temp_dir() . '/shodhanakg_cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
    }

    $cacheFile = $cacheDir . '/' . $cacheKey . '.cache';
    if (!$force && is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
        $content = @file_get_contents($cacheFile);
        if ($content !== false) {
            $val = @unserialize($content);
            if (is_array($val)) {
                if (function_exists('apcu_store')) {
                    apcu_store($cacheKey, $val, $ttl);
                }
                return $val;
            }
        }
    }

    $where = build_search_where_stats_cat($vertexType, $q);
    // Aggregate before pagination so numeric sorting is global.
    $entities = [];
    $scanOffset = 0;
    do {
        $sql = "SELECT FROM `{$vertexType}` {$where} ORDER BY @rid LIMIT 1000 SKIP {$scanOffset}";
        $batch = call_user_func(function () use (&$sql) {
            $__kgCoalesce1 = arcade_command($sql, 'sql');
            return (isset($__kgCoalesce1['result']) ? $__kgCoalesce1['result'] : []);
        });
        $entities = array_merge($entities, $batch);
        $scanOffset += count($batch);
    } while (count($batch) === 1000);

    if (empty($entities)) {
        $result = array();
    } else {
        $rows = array();
        foreach ($entities as $e) {
            $rid = (isset($e['@rid']) ? $e['@rid'] : '');
            if (!$rid) {
                continue;
            }
            $type = (isset($e['@type']) ? $e['@type'] : $vertexType);
            $name = vertex_label($e);
            $desc = vertex_description($e);
            if ($type === 'Compound') {
                $desc = '';
            }
            $rows[$rid] = array(
                'id' => (isset($e['id']) ? $e['id'] : ''),
                'name' => $name,
                'description' => ($desc !== '' && strcasecmp($desc, $name) !== 0 ? $desc : ''),
            );
        }
        $rids = array_keys($rows);
        foreach ($edges as $ec) {
            $edgeType = $ec['edge'];
            $key = $ec['key'];
            $reverse = (isset($ec['reverse']) ? $ec['reverse'] : false);
            $counts = count_edges_stats_cat($edgeType, $rids, $reverse, (isset($ec['sourceType']) ? $ec['sourceType'] : null));
            foreach ($counts as $rid => $cnt) {
                if (isset($rows[$rid])) {
                    if (!isset($rows[$rid][$key])) {
                        $rows[$rid][$key] = 0;
                    }
                    $rows[$rid][$key] += $cnt;
                }
            }
        }
        $countKeys = array();
        foreach ($edges as $e) {
            $countKeys[] = $e['key'];
        }
        $countKeys = array_unique($countKeys);
        foreach ($rows as $rid => $row) {
            $rowTotal = 0;
            foreach ($countKeys as $k) {
                if (!isset($rows[$rid][$k])) {
                    $rows[$rid][$k] = 0;
                }
                $rowTotal += $rows[$rid][$k];
            }
            $rows[$rid]['total'] = $rowTotal;
        }
        $result = array_values($rows);
    }

    if (function_exists('apcu_store')) {
        apcu_store($cacheKey, $result, $ttl);
    }
    if (is_dir($cacheDir) && is_writable($cacheDir)) {
        $tmp = $cacheFile . '.' . uniqid('tmp', true);
        if (@file_put_contents($tmp, serialize($result)) !== false) {
            @rename($tmp, $cacheFile);
        }
    }

    return $result;
}
function sort_and_slice_rows($rows, $sort, $dir, $limit, $offset)
{
    usort($rows, function ($a, $b) use ($sort, $dir) {
        $va = (isset($a[$sort]) ? $a[$sort] : 0);
        $vb = (isset($b[$sort]) ? $b[$sort] : 0);
        if (is_string($va) && is_string($vb)) {
            return ($dir === 'DESC' ? strcmp($vb, $va) : strcmp($va, $vb));
        }
        if ($va == $vb) {
            return (strcmp($a['name'], $b['name']) ? : strcmp((string) $a['id'], (string) $b['id']));
        }
        if ($dir === 'DESC') {
            return ($va < $vb ? 1 : -1);
        } else {
            return ($va > $vb ? 1 : -1);
        }
    });
    return array_slice($rows, $offset, $limit);
}
function list_entities($vertexType, $q, $edges, $sort, $dir, $limit, $offset)
{
    $allRows = get_all_category_rows($vertexType, $q, $edges);
    return sort_and_slice_rows($allRows, $sort, $dir, $limit, $offset);
}
function count_edges_stats_cat($edgeType, $rids, $reverse, $sourceType = null)
{
    if (empty($rids) || !$edgeType) {
        return array();
    }
    $ridCsv = implode(',', $rids);
    $field = ($reverse ? '@in' : '@out');
    $otherField = ($reverse ? '@out' : '@in');
    if ($sourceType) {
        $sql = sprintf("SELECT %s, COUNT(*) AS cnt FROM `%s` WHERE %s IN [%s] AND %s.@type = '%s' GROUP BY %s", $field, $edgeType, $field, $ridCsv, $otherField, $sourceType, $field);
    } else {
        $sql = sprintf("SELECT %s, COUNT(*) AS cnt FROM `%s` WHERE %s IN [%s] GROUP BY %s", $field, $edgeType, $field, $ridCsv, $field);
    }
    try {
        $res = arcade_command($sql, 'sql');
        $counts = array();
        $results = (isset($res['result']) ? $res['result'] : array());
        foreach ($results as $row) {
            $key = (isset($row[$field]) ? $row[$field] : '');
            if ($key) {
                $counts[$key] = (int) (isset($row['cnt']) ? $row['cnt'] : 0);
            }
        }
        return $counts;
    } catch (Exception $e) {
        throw $e;
        // Never turn a failed count query into a displayed zero.
    }
}
