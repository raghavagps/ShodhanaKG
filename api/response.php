<?php

function api_send_json($status, $payload) {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload);
  exit;
}

function api_ok($data, $status = 200) {
  api_send_json($status, array_merge(array('ok' => true), $data));
}

function api_fail($code, $message, $status = 400, $details = array()) {
  if ($status >= 500) {
    $logMsg = isset($details['message']) ? $details['message'] : (isset($details['error']) ? $details['error'] : $message);
    error_log("ShodhanaKG API [$code] ($status): " . (is_string($logMsg) ? $logMsg : json_encode($logMsg)));
    $details = array();
    if (strpos($message, '[Query:') !== false || strpos($message, 'ArcadeDB') !== false) {
      $message = 'Internal database query failed';
    }
  }
  api_send_json($status, array(
    'ok' => false,
    'error' => array(
      'code' => $code,
      'message' => $message,
      'details' => $details,
    ),
  ));
}

