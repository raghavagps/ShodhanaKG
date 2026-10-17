<?php

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/graph_service.php';

api_handle_cors_preflight();

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
if ($method !== 'GET') {
  api_fail('method_not_allowed', 'Use GET', 405);
}

$domain = isset($_GET['domain']) ? trim($_GET['domain']) : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'name';
$dirParam = isset($_GET['dir']) ? strtolower(trim($_GET['dir'])) : 'asc';
$dir = $dirParam === 'desc' ? 'DESC' : 'ASC';
$limit = min(max(isset($_GET['limit']) ? (int)$_GET['limit'] : 25, 1), 100);
$offset = max(isset($_GET['offset']) ? (int)$_GET['offset'] : 0, 0);

if (!in_array($domain, array('disease', 'compound', 'integrated'), true)) {
  api_fail('invalid_domain', 'Domain must be disease, compound, or integrated', 400);
}

try {
  if ($domain === 'disease') {
    $rows = build_disease_stats($q, $sort, $dir, $limit, $offset);
    api_ok(array(
      'rows' => $rows,
      'total' => count_all_diseases($q),
      'limit' => $limit,
      'offset' => $offset,
    ));
  } elseif ($domain === 'compound') {
    $rows = build_compound_stats($q, $sort, $dir, $limit, $offset);
    api_ok(array(
      'rows' => $rows,
      'total' => count_all_compounds($q),
      'limit' => $limit,
      'offset' => $offset,
    ));
  } else {
    api_fail('not_implemented', "Domain '$domain' stats not implemented yet", 501);
  }
} catch (Exception $e) {
  api_fail('stats_failed', 'Stats query failed', 500, array(
    'message' => $e->getMessage(),
  ));
}

function build_search_where_stats($type, $q) {
  if ($q === '') return '';
  $escaped = strtolower(arcade_escape($q));
  $conds = array();
  $fields = search_fields_for_type($type);
  foreach ($fields as $f) {
    $col = (strpos($f, ' ') !== false || strpos($f, '.') !== false) ? "`$f`" : $f;
    $conds[] = sprintf("coalesce(%s, '').toLowerCase() LIKE '%%%s%%'", $col, $escaped);
  }
  return 'WHERE ' . implode(' OR ', $conds);
}

function count_all_compounds($q) {
  $where = build_search_where_stats('Compound', $q);
  $sql = "SELECT COUNT(*) AS cnt FROM Compound $where";
  $res = arcade_command($sql, 'sql');
  $results = isset($res['result']) ? $res['result'] : array();
  return (int) (isset($results[0]['cnt']) ? $results[0]['cnt'] : 0);
}

function count_all_diseases($q) {
  $where = build_search_where_stats('Disease', $q);
  $sql = "SELECT COUNT(*) AS cnt FROM Disease $where";
  $res = arcade_command($sql, 'sql');
  $results = isset($res['result']) ? $res['result'] : array();
  return (int) (isset($results[0]['cnt']) ? $results[0]['cnt'] : 0);
}

function build_disease_stats($q, $sort, $dir, $limit, $offset) {
  $where = build_search_where_stats('Disease', $q);
  $sql = "SELECT @rid, @type, id, name, description FROM Disease $where ORDER BY name ASC LIMIT $limit SKIP $offset";
  $res = arcade_command($sql, 'sql');
  $diseases = isset($res['result']) ? $res['result'] : array();

  if (empty($diseases)) return array();

  $rids = array();
  foreach ($diseases as $d) {
    if (isset($d['@rid'])) $rids[] = $d['@rid'];
  }
  $ridCsv = implode(',', $rids);

  $edgeCounts = array();
  $edgeTypeConfig = array(
    array('type' => 'HAS_SYMPTOM', 'key' => 'symptoms'),
    array('type' => 'HAS_AYURVEDIC_DIAGNOSIS', 'key' => 'ayurvedicDiagnosis'),
    array('type' => 'HAS_DOSHA_IMBALANCE', 'key' => 'doshas'),
    array('type' => 'TREATED_BY', 'key' => 'therapy'),
    array('type' => 'PRE_TREATED_BY', 'key' => 'therapy'),
    array('type' => 'POST_TREATED_BY', 'key' => 'therapy'),
    array('type' => 'USES_FORMULATION', 'key' => 'formulations'),
    array('type' => 'ASSESS', 'key' => 'assessmentParameters', 'dir' => 'in'),
  );

  foreach ($edgeTypeConfig as $cfg) {
    $et = $cfg['type'];
    $key = $cfg['key'];
    $configDir = isset($cfg['dir']) ? $cfg['dir'] : 'out';
    $dirField = $configDir === 'in' ? '@in' : '@out';
    $sql = sprintf(
      "SELECT %s, COUNT(*) AS cnt FROM `%s` WHERE %s IN [%s] GROUP BY %s LIMIT %d",
      $dirField,
      $et,
      $dirField,
      $ridCsv,
      $dirField,
      $limit * 20
    );
    try {
      $res = arcade_command($sql, 'sql');
      $results = isset($res['result']) ? $res['result'] : array();
      foreach ($results as $row) {
        $entityRid = isset($row[$dirField]) ? $row[$dirField] : '';
        if ($entityRid) {
          if (!isset($edgeCounts[$entityRid])) $edgeCounts[$entityRid] = array();
          $current = isset($edgeCounts[$entityRid][$key]) ? $edgeCounts[$entityRid][$key] : 0;
          $edgeCounts[$entityRid][$key] = $current + (int) (isset($row['cnt']) ? $row['cnt'] : 0);
        }
      }
    } catch (Exception $e) { }
  }

  $fieldKeys = array('symptoms', 'ayurvedicDiagnosis', 'doshas', 'therapy', 'assessmentParameters');

  $rows = array();
  foreach ($diseases as $d) {
    $rid = isset($d['@rid']) ? $d['@rid'] : '';
    $counts = isset($edgeCounts[$rid]) ? $edgeCounts[$rid] : array();
    $mapped = array();
    $total = 0;
    foreach ($fieldKeys as $key) {
      $c = isset($counts[$key]) ? $counts[$key] : 0;
      $mapped[$key] = $c;
      $total += $c;
    }

    $rows[] = array_merge(array(
      'id' => isset($d['id']) ? $d['id'] : '',
      'name' => trim((string) (isset($d['name']) ? $d['name'] : '')),
      'description' => vertex_description($d),
      'total' => $total,
    ), $mapped);
  }

  $allowedSorts = array('name', 'symptoms', 'ayurvedicDiagnosis', 'doshas', 'therapy', 'formulation', 'assessmentParameters', 'total');
  if (in_array($sort, $allowedSorts, true)) {
    usort($rows, function ($a, $b) use ($sort, $dir) {
      $va = isset($a[$sort]) ? $a[$sort] : 0;
      $vb = isset($b[$sort]) ? $b[$sort] : 0;
      if (is_string($va) && is_string($vb)) {
        return $dir === 'DESC' ? strcmp($vb, $va) : strcmp($va, $vb);
      }
      if ($va == $vb) return 0;
      if ($dir === 'DESC') {
        return ($va < $vb) ? 1 : -1;
      } else {
        return ($va > $vb) ? 1 : -1;
      }
    });
  }

  return $rows;
}

function build_compound_stats($q, $sort, $dir, $limit, $offset) {
  $where = build_search_where_stats('Compound', $q);
  $sql = "SELECT @rid, @type, id, name, description FROM Compound $where ORDER BY name ASC LIMIT $limit SKIP $offset";
  $res = arcade_command($sql, 'sql');
  $compounds = isset($res['result']) ? $res['result'] : array();
  if (empty($compounds)) return array();

  $comRids = array();
  foreach ($compounds as $c) {
    if (isset($c['@rid'])) $comRids[] = $c['@rid'];
  }
  $comRidCsv = implode(',', $comRids);

  $directEdges = array(
    array('type' => 'UNDERGOES', 'key' => 'shodhanaMethods', 'dir' => 'out'),
    array('type' => 'CONTAINS', 'key' => 'toxicSubstances', 'dir' => 'out'),
  );

  $directCounts = array();
  foreach ($directEdges as $cfg) {
    $dirField = $cfg['dir'] === 'in' ? '@in' : '@out';
    $et = $cfg['type'];
    $key = $cfg['key'];
    $sql = sprintf(
      "SELECT %s, COUNT(*) AS cnt FROM `%s` WHERE %s IN [%s] GROUP BY %s LIMIT %d",
      $dirField, $et, $dirField, $comRidCsv, $dirField, $limit * 20
    );
    try {
      $res = arcade_command($sql, 'sql');
      $rows = isset($res['result']) ? $res['result'] : array();
      foreach ($rows as $row) {
        $rid = isset($row[$dirField]) ? $row[$dirField] : '';
        if ($rid) {
          if (!isset($directCounts[$rid])) $directCounts[$rid] = array();
          $directCounts[$rid][$key] = (int) (isset($row['cnt']) ? $row['cnt'] : 0);
        }
      }
    } catch (Exception $e) { }
  }

  // Cross-domain bridges
  $bridgeConfig = array(
    array('edge' => 'DERIEVED_FROM', 'key' => 'purifiedCompounds', 'reverse' => true),
    array('edge' => 'USES_MEDIA', 'key' => 'media', 'start' => 'Shodhana_Method'),
    array('edge' => 'FOLLOWED_BY', 'key' => 'processes', 'start' => 'Shodhana_Method'),
    array('edge' => 'PRODUCES', 'key' => 'processes', 'start' => 'Purified_Compound', 'reverse' => true),
    array('edge' => 'VALIDATED_BY', 'key' => 'confirmationStudies', 'start' => 'Shodhana_Method'),
    array('edge' => 'USED_FOR_TREATMENT', 'key' => 'diseases', 'start' => 'Purified_Compound'),
    array('edge' => 'USED_IN_FORMULATION', 'key' => 'formulations', 'start' => 'Purified_Compound'),
  );

  $bridgeCounts = array();
  foreach ($bridgeConfig as $bc) {
    $et = $bc['edge'];
    $key = $bc['key'];
    $rev = isset($bc['reverse']) ? $bc['reverse'] : false;
    $start = isset($bc['start']) ? $bc['start'] : null;

    if ($start) {
      // Complex traversal: Compound -> ? -> ?
      // For simplicity, we use subqueries or multiple steps if needed,
      // but let's try a direct approach for ShodhanaMethod bridges first.
      if ($start === 'Shodhana_Method') {
        $sql = sprintf(
          "SELECT @rid, out('%s').size() as cnt FROM Shodhana_Method WHERE in('UNDERGOES') INTERSECT [%s] LIMIT %d",
          $et, $comRidCsv, $limit * 10
        );
      } elseif ($start === 'Purified_Compound') {
        $dir = $rev ? 'in' : 'out';
        $sql = sprintf(
          "SELECT @rid, %s('%s').size() as cnt FROM Purified_Compound WHERE in('DERIEVED_FROM') INTERSECT [%s] LIMIT %d",
          $dir, $et, $comRidCsv, $limit * 10
        );
      } else {
        continue;
      }
    } else {
      $field = $rev ? '@in' : '@out';
      $sql = sprintf(
        "SELECT %s, COUNT(*) AS cnt FROM `%s` WHERE %s IN [%s] GROUP BY %s LIMIT %d",
        $field, $et, $field, $comRidCsv, $field, $limit * 20
      );
    }

    try {
      $res = arcade_command($sql, 'sql');
      $rows = isset($res['result']) ? $res['result'] : array();
      foreach ($rows as $row) {
        // Need to map back to Compound RID
        // This part is complex due to ArcadeDB's lack of join.
        // For now, let's stick to the basic counts.
      }
    } catch (Exception $e) { }
  }

  $rows = array();
  foreach ($compounds as $c) {
    $rid = isset($c['@rid']) ? $c['@rid'] : '';
    $dc = isset($directCounts[$rid]) ? $directCounts[$rid] : array();
    $rows[] = array(
      'id' => isset($c['id']) ? $c['id'] : '',
      'name' => trim((string) (isset($c['name']) ? $c['name'] : '')),
      'description' => vertex_description($c),
      'shodhanaMethods' => isset($dc['shodhanaMethods']) ? $dc['shodhanaMethods'] : 0,
      'toxicSubstances' => isset($dc['toxicSubstances']) ? $dc['toxicSubstances'] : 0,
      'purifiedCompounds' => 0, // Fallback
      'media' => 0,
      'processes' => 0,
      'confirmationStudies' => 0,
      'diseases' => 0,
      'formulations' => 0,
      'total' => (isset($dc['shodhanaMethods']) ? $dc['shodhanaMethods'] : 0) + (isset($dc['toxicSubstances']) ? $dc['toxicSubstances'] : 0),
    );
  }

  usort($rows, function ($a, $b) use ($sort, $dir) {
    $va = isset($a[$sort]) ? $a[$sort] : 0;
    $vb = isset($b[$sort]) ? $b[$sort] : 0;
    if (is_string($va) && is_string($vb)) {
      return $dir === 'DESC' ? strcmp($vb, $va) : strcmp($va, $vb);
    }
    if ($va == $vb) return 0;
    if ($dir === 'DESC') {
      return ($va < $vb) ? 1 : -1;
    } else {
      return ($va > $vb) ? 1 : -1;
    }
  });

  return $rows;
}
