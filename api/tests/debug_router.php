<?php
require_once __DIR__ . '/../core/QuestionRouter.php';
$routing = QuestionRouter::route("what formulations are used to treat alopecoia?");
var_dump($routing);
