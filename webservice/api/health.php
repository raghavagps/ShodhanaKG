<?php

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';

api_handle_cors_preflight();

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
if ($method !== 'GET') {
  api_fail('method_not_allowed', 'Use GET', 405);
}

try {
  $queryRes = arcade_command('SELECT 1', 'sql');
  $result = isset($queryRes['result'][0]) ? $queryRes['result'][0] : null;
  api_ok(array(
    'service' => 'shodhana-api',
    'version' => isset($GLOBALS['API_VERSION']) ? $GLOBALS['API_VERSION'] : 'v1',
    'arcade' => array(
      'connected' => true,
      'test' => array(
        'query' => 'SELECT 1',
        'result' => $result,
      ),
    ),
  ));
} catch (Exception $e) {
  api_fail('arcade_unavailable', 'ArcadeDB test query failed', 500, array(
    'exception' => get_class($e),
    'message' => $e->getMessage(),
  ));
}

