<?php
require 'api/core/EntityResolver.php';
$lastQ = 'what are the symptoms of tubal bloackage?';
$extracted = [];
$cleanLastQ = preg_replace('/[^\w\s]/', '', $lastQ);
$lastWords = array_values(array_filter(explode(' ', $cleanLastQ)));
for ($size = 4; $size >= 1; $size--) {
    for ($idx = 0; $idx <= count($lastWords) - $size; $idx++) {
        $chunk = implode(' ', array_slice($lastWords, $idx, $size));
        if (strlen($chunk) >= 3) {
            $extracted[] = $chunk;
        }
    }
}
$types = ['Disease', 'Compound', 'Symptom', 'Therapy', 'Formulation', 'Assessment_Parameter', 'Dosha', 'Ayurvedic_Diagnosis'];
foreach ($extracted as $candidate) {
    foreach ($types as $type) {
        $res = EntityResolver::fuzzyResolve($type, $candidate);
        if ($res) {
            echo "Matched chunk '$candidate' to $type: " . ($res['name'] ?? $res['label']) . "\n";
            exit; // This mimics break 2
        }
    }
}
