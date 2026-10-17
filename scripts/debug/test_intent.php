<?php
require 'api/core/QuestionRouter.php';
$reflector = new ReflectionClass('QuestionRouter');
$method = $reflector->getMethod('classifyIntentContextually');
$method->setAccessible(true);
echo "Shodhana_Method: " . $method->invoke(null, "how is swedana classified?", "Shodhana_Method") . "\n";
echo "Therapy: " . $method->invoke(null, "how is swedana classified?", "Therapy") . "\n";
