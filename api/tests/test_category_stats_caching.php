<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../category-stats.php';

function test_sort_and_slice() {
    $sampleRows = array(
        array('id' => 1, 'name' => 'Amalaki', 'total' => 15, 'symptoms' => 5),
        array('id' => 2, 'name' => 'Bibhitaki', 'total' => 25, 'symptoms' => 10),
        array('id' => 3, 'name' => 'Haritaki', 'total' => 20, 'symptoms' => 8),
        array('id' => 4, 'name' => 'Guggulu', 'total' => 25, 'symptoms' => 4),
    );

    // Test sorting by total DESC
    $sortedDesc = sort_and_slice_rows($sampleRows, 'total', 'DESC', 10, 0);
    assert(count($sortedDesc) === 4, 'Should return 4 rows');
    assert($sortedDesc[0]['name'] === 'Bibhitaki', 'Highest total first (tied Bibhitaki vs Guggulu, alphabetical)');
    assert($sortedDesc[1]['name'] === 'Guggulu', 'Second highest total (tied)');
    assert($sortedDesc[2]['name'] === 'Haritaki', 'Third highest total');
    assert($sortedDesc[3]['name'] === 'Amalaki', 'Lowest total');

    // Test pagination slicing: limit 2, offset 2
    $page2 = sort_and_slice_rows($sampleRows, 'total', 'DESC', 2, 2);
    assert(count($page2) === 2, 'Page 2 should have 2 items');
    assert($page2[0]['name'] === 'Haritaki', 'Page 2 first item is Haritaki');
    assert($page2[1]['name'] === 'Amalaki', 'Page 2 second item is Amalaki');

    // Test sort by name ASC
    $sortedNameAsc = sort_and_slice_rows($sampleRows, 'name', 'ASC', 2, 0);
    assert($sortedNameAsc[0]['name'] === 'Amalaki', 'Alphabetical first');
    assert($sortedNameAsc[1]['name'] === 'Bibhitaki', 'Alphabetical second');

    echo "PASS: sort_and_slice_rows logic verified\n";
}

function test_caching_behavior() {
    global $API_CACHE_DIR;
    $cacheDir = !empty($API_CACHE_DIR) ? $API_CACHE_DIR : (__DIR__ . '/../storage/cache');
    $vertexType = 'MockEntity';
    $q = 'test_query';
    $cacheKey = 'cat_stats_' . md5($vertexType . '|' . $q);
    $cacheFile = $cacheDir . '/' . $cacheKey . '.cache';

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    if (!is_writable($cacheDir)) {
        $cacheDir = sys_get_temp_dir() . '/shodhanakg_cache';
        $cacheFile = $cacheDir . '/' . $cacheKey . '.cache';
    }

    // Clean up any existing cache file
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }

    // Pre-populate cache file to simulate cached aggregation
    $mockCached = array(
        array('id' => 101, 'name' => 'Cached Item 1', 'description' => 'Desc 1', 'total' => 12),
        array('id' => 102, 'name' => 'Cached Item 2', 'description' => 'Desc 2', 'total' => 8),
        array('id' => 103, 'name' => 'Cached Item 3', 'description' => 'Desc 3', 'total' => 20),
    );
    file_put_contents($cacheFile, serialize($mockCached));

    // Calling get_all_category_rows should return the cached data without hitting DB
    $edges = array();
    $rows = get_all_category_rows($vertexType, $q, $edges, false);
    assert(count($rows) === 3, 'Should load 3 items from cache');
    assert($rows[0]['id'] === 101, 'First row matches cached ID');
    assert($rows[2]['id'] === 103, 'Third row matches cached ID');

    // Pagination requests should slice from cache
    $p1 = sort_and_slice_rows($rows, 'total', 'DESC', 2, 0);
    assert(count($p1) === 2, 'Page 1 has 2 items');
    assert($p1[0]['id'] === 103, 'Page 1 first item is id 103 (total 20)');
    assert($p1[1]['id'] === 101, 'Page 1 second item is id 101 (total 12)');

    $p2 = sort_and_slice_rows($rows, 'total', 'DESC', 2, 2);
    assert(count($p2) === 1, 'Page 2 has 1 item');
    assert($p2[0]['id'] === 102, 'Page 2 item is id 102 (total 8)');

    // Clean up test cache file
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }

    echo "PASS: category stats caching verified\n";
}

test_sort_and_slice();
test_caching_behavior();
echo "All category stats tests passed successfully.\n";
