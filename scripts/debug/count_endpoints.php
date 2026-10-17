<?php
$content = file_get_contents('api/core/QuestionRouter.php');
preg_match_all("/'([a-z_]+)'\s*=>\s*'([a-z_]+)'/", $content, $matches);
$endpoints = array_unique($matches[2]);
print_r($endpoints);
