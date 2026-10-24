<?php

function run_search($domain, $q) {
    $script = realpath(__DIR__ . '/../search.php');
    $phpCode = '<?php $_SERVER["REQUEST_METHOD"] = "GET"; $_GET["domain"] = ' . var_export($domain, true) . '; $_GET["q"] = ' . var_export($q, true) . '; require ' . var_export($script, true) . ';';
    $descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $process = proc_open('php', $descriptors, $pipes);
    fwrite($pipes[0], $phpCode);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    return json_decode($stdout, true);
}

function test_search_query_length() {
    // 1. Test ASCII query > 200 characters: must return 400 and code 'query_too_long'
    $longQ = str_repeat('a', 201);
    $json1 = run_search('disease', $longQ);
    assert(is_array($json1), "Output should be valid JSON array");
    assert($json1['ok'] === false, "ok should be false");
    assert($json1['error']['code'] === 'query_too_long', "Error code should be 'query_too_long'");
    assert($json1['error']['message'] === 'Query exceeds 200 characters', "Message should match");

    // 2. Test Multibyte Unicode query > 200 characters (e.g. 201 Devanagari characters)
    $longUnicode = str_repeat('ध', 201);
    $jsonU = run_search('disease', $longUnicode);
    assert(is_array($jsonU), "Output should be valid JSON array");
    assert($jsonU['ok'] === false, "ok should be false");
    assert($jsonU['error']['code'] === 'query_too_long', "Unicode query > 200 chars should be rejected");

    // 3. Test empty query: triggers empty_query
    $json3 = run_search('disease', '');
    assert(is_array($json3), "Output should be valid JSON for empty query");
    assert($json3['error']['code'] === 'empty_query', "Empty query should trigger 'empty_query'");

    echo "PASS: Search query length validation verified\n";
}

test_search_query_length();
