<?php
$files = glob(__DIR__ . '/api/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    $changed = false;
    
    // Replace 'USES' with 'USES_FORMULATION'
    if (strpos($content, "'USES'") !== false) {
        $content = str_replace("'USES'", "'USES_FORMULATION'", $content);
        $changed = true;
    }
    
    // Replace `USES` with `USES_FORMULATION`
    if (strpos($content, "`USES`") !== false) {
        $content = str_replace("`USES`", "`USES_FORMULATION`", $content);
        $changed = true;
    }
    
    // Replace "USES" with "USES_FORMULATION"
    if (strpos($content, "\"USES\"") !== false) {
        $content = str_replace("\"USES\"", "\"USES_FORMULATION\"", $content);
        $changed = true;
    }

    if ($file === __DIR__ . '/api/graph_service.php') {
        // Fix the normalization edge type logic
        $content = str_replace("case 'USES_FORMULATION': return 'USES_FORMULATION';", "case 'USES_FORMULATION': return 'USES_FORMULATION';", $content); // I will just let it be USES_FORMULATION if the frontend wants USES_FORMULATION!
    }

    if ($changed) {
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}
echo "Done.\n";
