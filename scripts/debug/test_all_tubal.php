<?php
require 'api/core/EntityResolver.php';
$types = ['Disease', 'Compound', 'Symptom', 'Therapy', 'Formulation', 'Assessment_Parameter', 'Dosha', 'Ayurvedic_Diagnosis'];
foreach ($types as $t) {
    $res = EntityResolver::fuzzyResolve($t, 'tubal bloackage');
    if ($res) {
        echo "FUZZY MATCH 'tubal bloackage' to $t: {$res['name']}\n";
    }
}
