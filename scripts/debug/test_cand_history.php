<?php
require 'api/core/QuestionRouter.php';
$reflector = new ReflectionClass('QuestionRouter');
$method = $reflector->getMethod('route');
$method->setAccessible(true);
$history = [
    ['role' => 'user', 'text' => 'what are the symptoms of venous ulcer?'],
    ['role' => 'assistant', 'text' => 'Based on the provided knowledge context, the symptoms of Venous Ulcer are...']
];
// Hack to print candidates
$content = file_get_contents('api/core/QuestionRouter.php');
eval(str_replace('public static function route(', 'public static function dummy_route(', $content));
