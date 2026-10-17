<?php
require 'api/core/ChatbotOrchestrator.php';
require 'api/core/ResponseGenerator.php';
$history = [
    ['role' => 'user', 'text' => 'what are the symptoms of venous ulcer?'],
    ['role' => 'bot', 'text' => 'Based on the provided knowledge context, the symptoms of Venous Ulcer are...']
];
$orch = ChatbotOrchestrator::process('what assessment parameters are used for it?', $history);
$prompt = ResponseGenerator::buildPrompt('what assessment parameters are used for it?', $orch, $history);
echo $prompt;
