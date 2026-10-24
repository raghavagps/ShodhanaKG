<?php

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/graph_service.php';

api_handle_cors_preflight();

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
if ($method !== 'GET') {
  api_fail('method_not_allowed', 'Use GET', 405);
}

$domain = isset($_GET['domain']) ? trim($_GET['domain']) : '';

if (!in_array($domain, array('disease', 'compound', 'integrated'), true)) {
  api_fail('invalid_domain', 'Domain must be disease, compound, or integrated', 400);
}

try {
  $counts = array();
  $types = vertex_types_for_domain($domain);
  foreach ($types as $type) {
    $sql = sprintf("SELECT COUNT(*) AS cnt FROM `%s`", $type);
    try {
      $res = arcade_command($sql, 'sql');
      $results = isset($res['result']) ? $res['result'] : array();
      $counts[normalize_type($type)] = (int) (isset($results[0]['cnt']) ? $results[0]['cnt'] : 0);
    } catch (Exception $e) {
      $counts[normalize_type($type)] = 0;
    }
  }
  api_ok(array('counts' => $counts));
} catch (Exception $e) {
  api_fail('counts_failed', 'Counts query failed', 500, array(
    'message' => $e->getMessage(),
  ));
}
