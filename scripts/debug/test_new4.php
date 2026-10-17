<?php
require 'api/core/ChatbotOrchestrator.php';
$res = ChatbotOrchestrator::process("What methods detoxify Arsenic?");
echo "Endpoint: " . $res['routing']['endpoint'] . "\n";
echo "Results: " . count($res['retrieval']['results'] ?? []) . "\n";
