<?php
$file = 'api/core/EntityResolver.php';
$content = file_get_contents($file);
$content = str_replace(
    "'toxic substance', 'ayurvedic diagnosis', 'assessment parameter',",
    "'toxic substance', 'ayurvedic diagnosis', 'assessment parameter', 'assessment parameters',",
    $content
);
file_put_contents($file, $content);
echo "Fixed generic terms\n";
