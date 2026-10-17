<?php
require 'api/core/ChatbotOrchestrator.php';
$history = [
    ['role' => 'user', 'text' => 'what are the symptoms of venous ulcer?'],
    ['role' => 'bot', 'text' => '...'],
    ['role' => 'user', 'text' => 'what assessment parameters are used for it?'],
    ['role' => 'bot', 'text' => '...'],
    ['role' => 'user', 'text' => 'what are the symptoms of tubal bloackage?'],
    ['role' => 'bot', 'text' => '...']
];
$orch = ChatbotOrchestrator::process('what assessment parameters used for it?', $history);
print_r($orch);
