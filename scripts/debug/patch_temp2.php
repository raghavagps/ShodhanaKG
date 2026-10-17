<?php
$file = 'api/core/EntityResolver.php';
$content = file_get_contents($file);
$content = str_replace(
    'public static function fuzzyResolve($type, $name, &$debugCollector = null) { echo "Fuzzy resolving: type=$type, name=$name\n"; ',
    'public static function fuzzyResolve($type, $name, &$debugCollector = null) {',
    $content
);
file_put_contents($file, $content);
