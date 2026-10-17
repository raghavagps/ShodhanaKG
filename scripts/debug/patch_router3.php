<?php
$file = 'api/core/QuestionRouter.php';
$content = file_get_contents($file);

// 1. Add mappings to the $map array

$content = str_replace(
    "'compound_therapeutic_use' => 'compound_therapeutic_use',",
    "'compound_therapeutic_use' => 'compound_therapeutic_use',\n                'compound_purified_forms' => 'compound_purified_forms',",
    $content
);

$content = str_replace(
    "'toxic_substance_compound' => 'toxic_substance_compound'",
    "'toxic_substance_compound' => 'toxic_substance_compound',\n                'toxic_substance_method' => 'toxic_substance_method'",
    $content
);

$content = str_replace(
    "'process_compound' => 'process_compound'",
    "'process_compound' => 'process_compound',\n                'process_purified_compound' => 'process_purified_compound',\n                'process_classification' => 'process_classification'",
    $content
);

$content = str_replace(
    "'formulation_disease' => 'formulation_disease'",
    "'formulation_disease' => 'formulation_disease',\n                'formulation_compound' => 'formulation_compound',\n                'formulation_therapy' => 'formulation_therapy'",
    $content
);

// Add missing Entity blocks in map
$missingMap = <<<EOD
              'Confirmation_Study' => [
                  'confirmation_study_method' => 'confirmation_study_method',
                  'confirmation_study_purified_compound' => 'confirmation_study_purified_compound'
              ],
              'Ayurvedic_Diagnosis' => [
                  'ayurvedic_diagnosis_disease' => 'ayurvedic_diagnosis_disease'
              ],
              'Assessment_Parameter' => [
                  'assessment_disease' => 'assessment_disease'
              ],
              'Shodhana_Type' => [
                  'classification_process' => 'classification_process'
              ],
EOD;
$content = preg_replace("/'Disease'\s*=>\s*\[/", $missingMap . "\n              'Disease' => [", $content);


// 2. Add Regex intents
$regexUpdates = <<<EOD
        if (\$entityType === 'Compound') {
            if (preg_match('/\b(purified compound|derived|derivatives|produce)\b/i', \$q)) return 'compound_purified_forms';
EOD;
$content = preg_replace("/if \(\\\$entityType === 'Compound'\) \{/", $regexUpdates, $content);

$regexUpdates = <<<EOD
        if (\$entityType === 'Toxic_Substance') {
            if (preg_match('/\b(shodhana|method|process|detoxif|remove)\b/i', \$q)) return 'toxic_substance_method';
EOD;
$content = preg_replace("/if \(\\\$entityType === 'Toxic_Substance'\) \{/", $regexUpdates, $content);

$regexUpdates = <<<EOD
        if (\$entityType === 'Shodhana_Method') {
            if (preg_match('/\b(classif|type|category)\b/i', \$q)) return 'process_classification';
            if (preg_match('/\b(purified compound|produce|result)\b/i', \$q)) return 'process_purified_compound';
EOD;
$content = preg_replace("/if \(\\\$entityType === 'Shodhana_Method'\) \{/", $regexUpdates, $content);

$regexUpdates = <<<EOD
        if (\$entityType === 'Formulation' || \$entityType === 'Ayurvedic_Formulation') {
            if (preg_match('/\b(compound|ingredient|purified|contain)\b/i', \$q)) return 'formulation_compound';
            if (preg_match('/\b(therap|treatment|used in)\b/i', \$q)) return 'formulation_therapy';
EOD;
$content = preg_replace("/if \(\\\$entityType === 'Formulation'\) \{/", $regexUpdates, $content);

$regexMissing = <<<EOD
        if (\$entityType === 'Confirmation_Study') {
            if (preg_match('/\b(method|process|shodhana)\b/i', \$q)) return 'confirmation_study_method';
            if (preg_match('/\b(compound|purified|safe)\b/i', \$q)) return 'confirmation_study_purified_compound';
            return 'confirmation_study_general';
        }
        if (\$entityType === 'Ayurvedic_Diagnosis') {
            if (preg_match('/\b(disease|modern|match)\b/i', \$q)) return 'ayurvedic_diagnosis_disease';
            return 'ayurvedic_diagnosis_general';
        }
        if (\$entityType === 'Assessment_Parameter') {
            if (preg_match('/\b(disease|assess)\b/i', \$q)) return 'assessment_disease';
            return 'assessment_general';
        }
        if (\$entityType === 'Shodhana_Type') {
            if (preg_match('/\b(method|process|classif)\b/i', \$q)) return 'classification_process';
            return 'classification_general';
        }

EOD;
$content = preg_replace("/if \(\\\$entityType === 'Disease'\) \{/", $regexMissing . "        if (\$entityType === 'Disease') {", $content);

file_put_contents($file, $content);
echo "Updated QuestionRouter.php with new intents\n";
