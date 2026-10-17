<?php
$file = 'api/core/ChatbotOrchestrator.php';
$content = file_get_contents($file);

$endpoints = json_decode(file_get_contents('endpoints_config.json'), true);

$requires = "";
$mappings = "";

foreach ($endpoints as $ep) {
    $name = $ep['name'];
    $requires .= "require_once __DIR__ . '/../{$name}.php';\n";
    $mappings .= "          '{$name}' => 'execute_{$name}',\n";
}

// Insert requires
$content = preg_replace("/(require_once __DIR__ \. '\/\.\.\/toxic_substance_compound\.php';)/", "$1\n" . trim($requires), $content);

// Insert mappings
$content = preg_replace("/('toxic_substance_compound'\s*=>\s*'execute_toxic_substance_compound')/", "$1,\n" . rtrim($mappings, ",\n"), $content);

file_put_contents($file, $content);
echo "Updated ChatbotOrchestrator.php\n";
