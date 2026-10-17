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
$nodeId = isset($_GET['id']) ? trim($_GET['id']) : '';
$neighborLimit = min(max(isset($_GET['neighborLimit']) ? (int)$_GET['neighborLimit'] : 50, 1), 200);

if (!in_array($domain, array('disease', 'compound', 'integrated'), true)) {
  api_fail('invalid_domain', 'Domain must be disease, compound, or integrated', 400);
}
if ($nodeId === '') {
  api_fail('missing_id', 'Node ID is required', 400);
}

try {
  $context = fetch_node_context($nodeId, $domain, $neighborLimit);
  if (!$context) {
    api_fail('node_not_found', "Node '$nodeId' not found", 404);
  }

  api_ok(array(
    'node' => $context['node'],
    'neighbors' => $context['neighbors'],
    'evidence' => $context['evidence'],
    'edges' => $context['edges'],
    'meta' => $context['meta'],
  ));
} catch (Exception $e) {
  api_fail('node_query_failed', 'Node query failed', 500, array(
    'message' => $e->getMessage(),
  ));
}
