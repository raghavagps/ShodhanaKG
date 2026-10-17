<?php
require 'api/core/ChatbotOrchestrator.php';
$res = ChatbotOrchestrator::process("What diseases is Shirodhara used for?");
print_r($res);
