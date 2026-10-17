<?php
require 'api/core/QuestionRouter.php';
$reflector = new ReflectionClass('QuestionRouter');
$method = $reflector->getMethod('extractEntityCandidates');
$method->setAccessible(true);
print_r($method->invoke(null, 'Tell me about the symptoms of Eczema.'));
