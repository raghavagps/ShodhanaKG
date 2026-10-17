<?php
require 'api/core/ChatbotOrchestrator.php';
$tests = [
    "What methods detoxify lead?",
    "What purified compounds are derived from vanga?",
    "How is Swedana classified?",
    "What diseases match the diagnosis of Rajayakshma?"
];
foreach ($tests as $q) {
    echo "--- Q: $q ---\n";
    $res = ChatbotOrchestrator::process($q);
    if (isset($res['routing']) && is_array($res['routing'])) {
        echo "Endpoint: " . $res['routing']['endpoint'] . "\n";
        echo "Results: " . count($res['retrieval']['results'] ?? []) . "\n";
    } else {
        echo "Endpoint: NONE (status: " . $res['status'] . ")\n";
    }
}
