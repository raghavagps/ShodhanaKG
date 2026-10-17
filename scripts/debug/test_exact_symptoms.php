<?php
require 'api/core/EntityResolver.php';
$types = ['Disease', 'Compound', 'Symptom', 'Therapy', 'Formulation', 'Assessment_Parameter', 'Dosha', 'Ayurvedic_Diagnosis'];
foreach ($types as $t) {
    $res = EntityResolver::resolve($t, 'symptoms');
    if ($res) {
        echo "EXACT MATCH 'symptoms' to $t: {$res['name']}\n";
    }
}
