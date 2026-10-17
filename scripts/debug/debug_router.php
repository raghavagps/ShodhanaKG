<?php
require 'api/core/ChatbotOrchestrator.php';
$q = 'what assessment parameters are used for it?';
$history = [
    ['role'=>'user', 'text'=>'what are the symptoms of venous ulcer?'],
    ['role'=>'bot', 'text'=>'...'],
    ['role'=>'user', 'text'=>$q]
];
print_r(QuestionRouter::route($q, $history));
