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

require_once __DIR__ . '/core/KgStatistics.php';
try {
  api_ok(KgStatistics::collect('arcade_command', get_schema_vertex_types()));
} catch (Exception $e) {
  api_fail('kg_stats_failed', 'KG stats query failed', 500);
}
