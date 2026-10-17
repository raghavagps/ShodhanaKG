<?php
require 'api/core/QuestionRouter.php';
$reflector = new ReflectionClass('QuestionRouter');
$method = $reflector->getMethod('route');
$method->setAccessible(true);
$history = [
    ['role' => 'user', 'text' => 'what are the symptoms of venous ulcer?'],
    ['role' => 'assistant', 'text' => 'Based on the provided knowledge context, the symptoms of Venous Ulcer are...']
];
print_r($method->invoke(null, 'what assessment parameters used for it?', $history));
