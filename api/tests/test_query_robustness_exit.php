<?php
/** Exercise the actual runner footer with synthetic failure summaries, without KG calls. */
$source = file_get_contents(__DIR__.'/test_query_robustness.php');
$marker = 'echo "==================================================\n";';
$offset = strrpos($source, $marker);
if ($offset === false) throw new RuntimeException('Cannot locate robustness report footer');
$footer = substr($source, $offset);
$checks = 0;
foreach (['none', 'STATE_CLASSIFICATION_FAILURE', 'PROVENANCE_FAILURE', 'SCHEMA_LIMITATION', 'ROUTER_FAILURE'] as $category) {
    $results = array_fill_keys(['ROUTER_FAILURE', 'ENTITY_RESOLUTION_FAILURE', 'ENDPOINT_SELECTION_FAILURE', 'DETERMINISTIC_RETRIEVAL_FAILURE', 'STATE_CLASSIFICATION_FAILURE', 'PROVENANCE_FAILURE', 'SCHEMA_LIMITATION'], 0);
    $expected = $category === 'none' ? 0 : 1;
    $results['failures'] = $expected;
    if ($expected) $results[$category] = 1;
    $code = '$results='.var_export($results, true).';'.$footer;
    $process = proc_open([PHP_BINARY, '-r', $code], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot run exit-code regression');
    fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process);
    if ($exit !== $expected || $error !== '') throw new RuntimeException("$category: expected exit $expected, got $exit: $error");
    $checks++;
}
echo "PASS $checks robustness runner exit contracts\n";
