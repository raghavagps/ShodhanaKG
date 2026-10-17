<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/SocketHttp.php';

function arcade_command($command, $language = 'sql', $params = array(), $timeout = 30) {
  global $ARCADE_URL, $ARCADE_DB, $ARCADE_USER, $ARCADE_PASS, $last_arcade_command;
  $last_arcade_command = $command;
  $timeout = max(1, min(30, (int)$timeout));
  $deadline = microtime(true) + $timeout;

  $url = rtrim($ARCADE_URL, '/') . '/api/v1/command/' . rawurlencode($ARCADE_DB);
  $body = array('language' => $language, 'command' => $command);
  if (!empty($params)) $body['params'] = $params;
  $payload = json_encode($body);

  // Legacy PHP stream wrappers can apply their timeout separately while reading headers/body.
  // Prefer the explicitly deadline-bounded socket transport when cURL is unavailable.
  if (!function_exists('curl_init') && function_exists('stream_socket_client')) {
    return arcade_command_socket($url, $payload, $ARCADE_USER, $ARCADE_PASS, $timeout);
  }

    // Try cURL if available
  if (function_exists('curl_init')) {
    static $ch = null;
    if ($ch === null) {
      $ch = curl_init();
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt_array($ch, array(
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json; charset=utf-8',
        'Connection: keep-alive'
      ),
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_USERPWD => $ARCADE_USER . ':' . $ARCADE_PASS,
      CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_FORBID_REUSE => false,
      CURLOPT_FRESH_CONNECT => false
    ));
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // Do NOT close the handle
    if ($raw !== false) {
      return handle_arcade_response($raw, $httpCode);
    }
  }

  if (microtime(true) >= $deadline) throw new RuntimeException('ArcadeDB transport timed out');

  // Fallback to file_get_contents if cURL fails or is missing
  $auth = base64_encode($ARCADE_USER . ':' . $ARCADE_PASS);
  $options = array(
    'http' => array(
      'method'  => 'POST',
      'header'  => array(
        'Content-Type: application/json; charset=utf-8',
        "Authorization: Basic $auth"
      ),
      'content' => $payload,
      'timeout' => max(0.1, $deadline - microtime(true)),
      'ignore_errors' => true
    )
  );

  $context = stream_context_create($options);
  $raw = @file_get_contents($url, false, $context);
  
  if ($raw !== false) {
    $httpCode = 0;
    if (@$http_response_header && preg_match('{HTTP\/\S+\s+(\d+)}', @$http_response_header[0], $matches)) {
      $httpCode = (int) $matches[1];
    }
    if ($httpCode !== 0) {
      return handle_arcade_response($raw, $httpCode);
    }
  }

  if (microtime(true) >= $deadline) throw new RuntimeException('ArcadeDB transport timed out');

  // Fallback to raw sockets
  if (function_exists('fsockopen')) {
    return arcade_command_socket($url, $payload, $ARCADE_USER, $ARCADE_PASS, max(1, (int)ceil($deadline - microtime(true))));
  }

  $err = error_get_last();
  $msg = isset($err['message']) ? $err['message'] : 'unknown';
  throw new Exception('All ArcadeDB connection methods failed (cURL missing, url_fopen disabled, and sockets failed). Last error: ' . $msg);
}

function arcade_command_socket($url, $payload, $user, $pass, $timeout = 30) {
  $response = SocketHttp::request($url, 'POST', $payload, $timeout,
    array('Authorization' => 'Basic ' . base64_encode($user . ':' . $pass)), 64 * 1024 * 1024);
  return handle_arcade_response($response['body'], $response['status']);
}

function handle_arcade_response($raw, $httpCode) {
  $json = json_decode((string)$raw, true);
  if (!is_array($json)) {
    throw new Exception('ArcadeDB returned non-JSON');
  }
  if ($httpCode < 200 || $httpCode >= 300) {
    global $last_arcade_command;
    throw new Exception('ArcadeDB error: ' . (isset($json['error']) ? $json['error'] : 'unknown') . ' (' . $httpCode . ') [Query: ' . $last_arcade_command . ']');
  }
  return $json;
}

