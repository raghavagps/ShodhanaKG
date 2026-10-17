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

$domain = isset($_GET['domain']) && is_string($_GET['domain']) ? trim($_GET['domain']) : '';
$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$limit = max(isset($_GET['limit']) ? (int)$_GET['limit'] : 100, 1);
$typeFilter = isset($_GET['type']) && is_string($_GET['type']) ? trim($_GET['type']) : '';

if (!in_array($domain, array('disease', 'compound', 'integrated'), true)) {
  api_fail('invalid_domain', 'Domain must be disease, compound, or integrated', 400);
}
if ($q === '') {
  api_fail('empty_query', 'Search query is required', 400);
}
if (mb_strlen($q, 'UTF-8') > 200) {
  api_fail('query_too_long', 'Query exceeds 200 characters', 400);
}

try {
  $escaped = arcade_escape($q);
  $nodes = array();
  $limitPerType = $limit * 2;

  $allowedTypes = vertex_types_for_domain($domain);
  if ($typeFilter !== '') {
    $mappedType = db_type_for_frontend_type($typeFilter);
    if (!in_array($mappedType, $allowedTypes, true)) {
      api_ok(array('nodes' => array()));
    }
    $typesToSearch = array($mappedType);
  } else {
    $typesToSearch = $allowedTypes;
  }

  require_once __DIR__ . '/core/EntityResolution.php';
  $index = EntityResolution::live();
  $candidates = $index->search($q, $typesToSearch, min(100, $limit));
  foreach ($candidates as $candidate) {
    $node = build_node($index->node($candidate['id']));
    $node['match'] = $candidate;
    $nodes[] = $node;
  }
  api_ok(['nodes' => $nodes, 'resolution' => $index->resolve($q, $typesToSearch)]);

} catch (Exception $e) {
  api_fail('search_failed', 'Search query failed', 500, array(
    'message' => $e->getMessage(),
  ));
}
