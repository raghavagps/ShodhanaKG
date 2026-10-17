<?php
/** Exercise the real endpoint guard in an isolated CLI process without shell quoting. */
function test_endpoint_output(string $script, array $params): string {
    $code = '$_SERVER["SCRIPT_FILENAME"]=$argv[1]; parse_str($argv[2],$_GET); require $argv[1];';
    $process = proc_open([PHP_BINARY, '-r', $code, $script, http_build_query($params)],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start endpoint test process');
    fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process);
    if ($exit!==0) throw new RuntimeException('Endpoint process failed: '.$error);
    $decoded=json_decode($output,true,512,JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || !isset($decoded['status'])) throw new RuntimeException('Missing endpoint response envelope');
    return $output;
}
