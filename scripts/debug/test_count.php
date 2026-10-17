<?php
require_once 'api/core/QuestionRouter.php';
$router = new QuestionRouter();
$reflector = new ReflectionClass('QuestionRouter');
$property = $reflector->getProperty('intent_to_endpoint');
$property->setAccessible(true);
$map = $property->getValue($router);
$unique_endpoints = [];
foreach ($map as $entity => $intents) {
    foreach ($intents as $intent => $endpoint) {
        $unique_endpoints[] = $endpoint;
    }
}
echo count(array_unique($unique_endpoints));
