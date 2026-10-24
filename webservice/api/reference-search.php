<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/graph_service.php';
require_once __DIR__ . '/core/KgReadSnapshot.php';
require_once __DIR__ . '/core/ReferenceSearch.php';
api_handle_cors_preflight();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') api_fail('method_not_allowed', 'Use GET', 405);
try {
    foreach ($_GET as $value) if (!is_string($value) || strlen($value) > 1000) throw new InvalidArgumentException('Invalid search parameter');
    api_ok(ReferenceSearch::run(KgReadSnapshot::load(), $_GET));
} catch (InvalidArgumentException $e) { api_fail('invalid_search', $e->getMessage(), 400); }
catch (Exception $e) { api_fail('search_failed', 'Reference search could not be completed', 503); }
