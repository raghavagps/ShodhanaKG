<?php

require_once __DIR__ . '/config.php';

function api_cors_allow() {
  global $ALLOWED_ORIGINS;

  $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
  $allowAll = trim($ALLOWED_ORIGINS) === '*';

  if (!$origin) return;

  if ($allowAll) {
    header('Access-Control-Allow-Origin: *');
  } else {
    $allowed = array_map('trim', explode(',', $ALLOWED_ORIGINS));
    if (in_array($origin, $allowed, true)) {
      header('Access-Control-Allow-Origin: ' . $origin);
    }
  }

  header('Vary: Origin');
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization');
  header('Access-Control-Max-Age: 600');
}

function api_handle_cors_preflight() {
  api_cors_allow();
  $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
  if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
  }
}

