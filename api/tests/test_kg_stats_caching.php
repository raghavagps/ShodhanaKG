<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../kg-stats.php';

function test_kg_stats_caching() {
    global $API_CACHE_DIR;
    $cacheDir = !empty($API_CACHE_DIR) ? $API_CACHE_DIR : (__DIR__ . '/../storage/cache');
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    if (!is_writable($cacheDir)) {
        $cacheDir = sys_get_temp_dir() . '/shodhanakg_cache';
    }
    $cacheFile = $cacheDir . '/kg_stats.json';

    // Clear test cache file if exists
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }

    $calls = 0;
    $mockCollector = function() use (&$calls) {
        $calls++;
        return array(
            'totalNodes' => 250,
            'totalRelations' => 10500,
            'totalStudies' => 412,
            'totalReferences' => 412,
            'referenceCountBasis' => 'Mock test count'
        );
    };

    // 1. Cold fetch: collector called once
    $stats1 = get_cached_kg_stats(true, $mockCollector);
    assert($calls === 1, "Collector should be called on cold fetch (calls=$calls)");
    assert($stats1['totalNodes'] === 250, "totalNodes should match");
    assert(file_exists($cacheFile), "Cache file should be created");

    // 2. Warm fetch: collector should NOT be called again
    $stats2 = get_cached_kg_stats(false, $mockCollector);
    assert($calls === 1, "Collector should NOT be called on warm fetch (calls=$calls)");
    assert($stats2['totalRelations'] === 10500, "Cached totalRelations should match");

    // 3. Forced refresh: collector called again
    $stats3 = get_cached_kg_stats(true, $mockCollector);
    assert($calls === 2, "Collector should be called on forced refresh (calls=$calls)");

    // 4. Stale fallback on error
    $failingCollector = function() {
        throw new RuntimeException("ArcadeDB offline");
    };
    $stats4 = get_cached_kg_stats(true, $failingCollector);
    assert($stats4['totalNodes'] === 250, "Should fall back to stale cache on collector exception");

    // Clean up
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }

    echo "PASS: KG stats caching and fallback verified\n";
}

test_kg_stats_caching();
