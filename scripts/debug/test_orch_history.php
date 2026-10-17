<?php
require 'api/core/ChatbotOrchestrator.php';
$history = [
    ['role' => 'user', 'text' => 'what are the symptoms of venous ulcer?'],
    ['role' => 'assistant', 'text' => 'Based on the provided knowledge context, the symptoms of Venous Ulcer are...']
];
$response = ChatbotOrchestrator::process('what assessment parameters used for it?', $history);
print_r($response);
