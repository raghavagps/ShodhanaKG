<?php

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/graph_service.php';
require_once __DIR__ . '/core/KgStatistics.php';

api_handle_cors_preflight();

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
if ($method !== 'GET') {
  if (php_sapi_name() === 'cli' && empty($method)) {
    return;
  }
  api_fail('method_not_allowed', 'Use GET', 405);
}

function get_cached_kg_stats($force = false, callable $collector = null)
{
  global $API_CACHE_DIR;
  $apcuKey = 'shodhanakg_kg_stats';
  $ttl = 86400; // 24 hours

  if (!$force && function_exists('apcu_fetch')) {
    $hit = false;
    $val = apcu_fetch($apcuKey, $hit);
    if ($hit && is_array($val) && isset($val['totalNodes'])) {
      return $val;
    }
  }

  $cacheDir = !empty($API_CACHE_DIR) ? $API_CACHE_DIR : (__DIR__ . '/storage/cache');
  if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
  }
  if (!is_writable($cacheDir)) {
    $cacheDir = sys_get_temp_dir() . '/shodhanakg_cache';
    if (!is_dir($cacheDir)) {
      @mkdir($cacheDir, 0777, true);
    }
  }

  $cacheFile = $cacheDir . '/kg_stats.json';
  if (!$force && is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
    $content = @file_get_contents($cacheFile);
    if ($content !== false) {
      $val = @json_decode($content, true);
      if (is_array($val) && isset($val['totalNodes'])) {
        if (function_exists('apcu_store')) {
          apcu_store($apcuKey, $val, $ttl);
        }
        return $val;
      }
    }
  }

  // Cache miss: collect live statistics
  if ($collector === null) {
    $collector = function () {
      return KgStatistics::collect('arcade_command', get_schema_vertex_types());
    };
  }

  try {
    $stats = call_user_func($collector);
  } catch (Exception $e) {
    // Stale cache fallback if database is unavailable
    if (is_file($cacheFile)) {
      $content = @file_get_contents($cacheFile);
      if ($content !== false) {
        $val = @json_decode($content, true);
        if (is_array($val) && isset($val['totalNodes'])) {
          return $val;
        }
      }
    }
    throw $e;
  }

  if (function_exists('apcu_store')) {
    apcu_store($apcuKey, $stats, $ttl);
  }
  if (is_dir($cacheDir) && is_writable($cacheDir)) {
    $tmp = $cacheFile . '.' . uniqid('tmp', true);
    if (@file_put_contents($tmp, json_encode($stats, JSON_PRETTY_PRINT)) !== false) {
      @rename($tmp, $cacheFile);
    }
  }

  return $stats;
}

try {
  $force = !empty($_GET['refresh']) || !empty($_GET['nocache']);
  api_ok(get_cached_kg_stats($force));
} catch (Exception $e) {
  api_fail('kg_stats_failed', 'KG stats query failed', 500);
}
