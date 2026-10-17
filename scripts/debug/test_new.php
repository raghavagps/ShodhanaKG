<?php
require 'api/core/ChatbotOrchestrator.php';
$tests = [
    "What methods detoxify lead?",
    "What purified compounds are derived from Ashwagandha?",
    "How is Swedana classified?",
    "What diseases match the diagnosis of Rajayakshma?"
];
foreach ($tests as $q) {
    echo "--- Q: $q ---\n";
    $res = ChatbotOrchestrator::process($q);
    echo "Endpoint: " . (isset($res['routing']['endpoint']) ? $res['routing']['endpoint'] : 'NONE') . "\n";
    echo "Status: " . $res['status'] . "\n";
}
