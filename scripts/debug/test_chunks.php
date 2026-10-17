<?php
require 'api/core/QuestionRouter.php';
$reflector = new ReflectionClass('QuestionRouter');
$method = $reflector->getMethod('route');
$method->setAccessible(true);
$originalQ = 'What diseases does it treat?';
$cleanQ = preg_replace('/[^\w\s]/', '', $originalQ);
$words = array_values(array_filter(explode(' ', $cleanQ)));
print_r($words);
