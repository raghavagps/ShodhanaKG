<?php
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';
$routing = ChatbotOrchestrator::process("what formulations are used to treat alopecoia?");
var_dump($routing);
