<?php
$file = 'api/graph_service.php';
$content = file_get_contents($file);
$content = str_replace(
    "case 'Compound':",
    "case 'Compound':\n        return ['name', 'Ayurvedic_Name', 'Common_Name', 'label'];",
    $content
);
file_put_contents($file, $content);
echo "Added Ayurvedic_Name to Compound labels\n";
