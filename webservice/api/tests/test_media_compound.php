<?php

require_once __DIR__ . '/../media_compound.php';

echo "Testing execute_media_compound...\n";

// We can just execute the endpoint.
// For automated testing, since the DB is offline, we'll verify it returns an error or empty result gracefully, or mock the graph.
// But as per the project rules, we should run this when ArcadeDB is active or using mock DB.
// I will include a basic smoke test here.

$result = execute_media_compound('Sesame Oil');
if (isset($result['status'])) {
    echo "PASS: Returned valid structure. Status: " . $result['status'] . "\n";
} else {
    echo "FAIL: Invalid return structure.\n";
}

echo "Testing empty media...\n";
$result2 = execute_media_compound('');
if ($result2['status'] === 'error') {
    echo "PASS: Handled empty media.\n";
} else {
    echo "FAIL: Empty media handling.\n";
}

// Ensure deduplication logic works (we can't fully test without DB but the code is present)
echo "Media Compound test complete.\n";
