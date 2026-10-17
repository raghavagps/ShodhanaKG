<?php
require 'api/core/ChatbotOrchestrator.php';
$history = [
    ['role' => 'user', 'text' => 'what are the symptoms of tubal bloackage?'],
    ['role' => 'bot', 'text' => 'Based on the provided Knowledge Context, the symptoms of Tubal Blockage are...']
];
$orch = ChatbotOrchestrator::process('what assessment parameters used for it?', $history);
print_r($orch);
