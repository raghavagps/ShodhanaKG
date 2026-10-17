<?php
$files = glob(__DIR__ . '/api/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'USES_FORMULATION') !== false) {
        $content = str_replace('USES_FORMULATION', 'USES', $content);
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
echo "Done.\n";
