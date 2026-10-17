<?php
require_once __DIR__ . '/../core/ChatResponseTransport.php';
$count = 0;
foreach (['success', 'no_data', 'grounding_error', 'llm_error'] as $status) {
    $response = ['status' => $status, 'answer' => 'Evidence response', 'context' => ['status' => $status, 'reason' => 'no_correlated_evidence', 'routing' => ['endpoint' => 'provenance_compound_method_media'], 'retrieval' => ['results' => []]]];
    ob_start(); ChatResponseTransport::respond($response, false); $json = ob_get_clean();
    if (json_decode($json, true) !== $response) throw new RuntimeException('JSON contract changed');
    ob_start(); ChatResponseTransport::respond($response, true); $sse = ob_get_clean();
    preg_match('/event: context\ndata: ([^\n]+)/', $sse, $context);
    preg_match('/event: done\ndata: ([^\n]+)/', $sse, $done);
    if (json_decode($context[1], true) !== $response['context']) throw new RuntimeException('SSE lost context');
    if (json_decode($done[1], true) !== $response) throw new RuntimeException('SSE lost final status/answer');
    $count++;
}
echo "PASS $count JSON/SSE status and context contracts\n";
