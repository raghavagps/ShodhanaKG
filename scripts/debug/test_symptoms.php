<?php
require 'api/core/EntityResolver.php';
$types = ['Disease', 'Compound', 'Symptom', 'Therapy', 'Formulation', 'Assessment_Parameter', 'Dosha', 'Ayurvedic_Diagnosis'];
foreach ($types as $t) {
    $res = EntityResolver::fuzzyResolve($t, 'symptoms');
    if ($res) {
        echo "Matched 'symptoms' to $t: {$res['name']}\n";
    }
}
