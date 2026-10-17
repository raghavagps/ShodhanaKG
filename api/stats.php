<?php

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/response.php';

api_handle_cors_preflight();

$domain = isset($_GET['domain']) ? trim($_GET['domain']) : '';
if (!in_array($domain, array('disease', 'compound', 'integrated'), true)) {
  api_fail('invalid_domain', 'Domain must be disease, compound, or integrated', 400);
}

if ($domain === 'integrated') {
  api_fail('not_implemented', "Domain 'integrated' stats not implemented yet", 501);
}

// Delegate to category-stats.php with the default category for the domain so sorting is global across all entities
$_GET['category'] = ($domain === 'compound') ? 'Compound' : 'Disease';
require __DIR__ . '/category-stats.php';
