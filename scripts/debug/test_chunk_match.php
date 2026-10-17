<?php
require 'api/core/EntityResolver.php';
$cleanLastQ = preg_replace('/[^\w\s]/', '', 'what assessment parameters are used for it');
$lastWords = array_values(array_filter(explode(' ', $cleanLastQ)));
for ($size = 4; $size >= 1; $size--) {
    for ($idx = 0; $idx <= count($lastWords) - $size; $idx++) {
        $chunk = implode(' ', array_slice($lastWords, $idx, $size));
        if (strlen($chunk) >= 3) {
            $res = EntityResolver::fuzzyResolve('Assessment_Parameter', $chunk);
            if ($res) {
                echo "Matched $chunk to {$res['name']}\n";
            }
        }
    }
}
