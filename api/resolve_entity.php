<?php

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/core/EntityResolution.php';
api_handle_cors_preflight();
if ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') !== 'GET') {
    api_fail('method_not_allowed', 'Use GET', 405);
}
$query = trim((string) (isset($_GET['q']) ? $_GET['q'] : ''));
$type = (string) (isset($_GET['type']) ? $_GET['type'] : '');
if ($query === '' || mb_strlen($query) > 200) {
    api_fail('invalid_query', 'Supply an entity mention of at most 200 characters', 400);
}
if ($type !== '' && !in_array($type, get_schema_vertex_types(), true)) {
    api_fail('invalid_type', 'Unknown KG entity type', 400);
}
try {
    api_ok(['resolution' => EntityResolution::live()->resolve($query, ($type === '' ? [] : [$type]))]);
} catch (Exception $e) {
    api_fail('resolution_failed', 'Entity resolution is temporarily unavailable', 503);
} catch (Throwable $e) {
    api_fail('resolution_failed', 'Entity resolution is temporarily unavailable', 503);
}
