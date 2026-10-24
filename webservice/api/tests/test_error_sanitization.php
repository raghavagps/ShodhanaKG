<?php

// Test that 500 error responses are sanitized while preserving server-side error_log output
$cmd500 = 'php -r "require \'api/response.php\'; api_fail(\'query_err\', \'ArcadeDB error [Query: SELECT * FROM Secret]\', 500, [\'message\' => \'Secret details\']);"';
$output500 = shell_exec($cmd500);
$json500 = json_decode($output500, true);

if (!is_array($json500) || $json500['ok'] !== false) {
  fwrite(STDERR, "FAIL: Expected ok=false on 500\n");
  exit(1);
}
if (!empty($json500['error']['details'])) {
  fwrite(STDERR, "FAIL: Details leaked on 500 error: " . json_encode($json500['error']['details']) . "\n");
  exit(1);
}
if (strpos($json500['error']['message'], 'SELECT') !== false || strpos($json500['error']['message'], 'ArcadeDB') !== false) {
  fwrite(STDERR, "FAIL: Query leaked in message on 500 error: " . $json500['error']['message'] . "\n");
  exit(1);
}

// Test that 400 validation errors retain field details
$cmd400 = 'php -r "require \'api/response.php\'; api_fail(\'bad_input\', \'Field missing\', 400, [\'param\' => \'q\']);"';
$output400 = shell_exec($cmd400);
$json400 = json_decode($output400, true);

if (!is_array($json400) || $json400['ok'] !== false || !isset($json400['error']['details']['param']) || $json400['error']['details']['param'] !== 'q') {
  fwrite(STDERR, "FAIL: Validation details lost on 400 error\n");
  exit(1);
}

echo "PASS: Error sanitization tests passed\n";
exit(0);
