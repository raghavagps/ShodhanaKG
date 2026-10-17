<?php
require 'api/core/QuestionRouter.php';
$reflector = new ReflectionClass('QuestionRouter');
$method = $reflector->getMethod('route');
$method->setAccessible(true);
$originalQ = 'What diseases does it treat?';
$routing = $method->invoke(null, $originalQ);
print_r($routing);
