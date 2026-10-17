<?php
require 'api/core/ChatbotOrchestrator.php';
$res = ChatbotOrchestrator::process("What diseases use Triphala Churna?");
print_r($res);
