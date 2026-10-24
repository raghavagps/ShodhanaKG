<?php

require_once __DIR__ . '/PhpCompatibility.php';
/** Schema-verified additions. Direction is relative to the input node. */
class TraversalCatalog
{
    public static function all()
    {
        $step = static function ($edges, $direction, $types) {
            return ['edges' => (array) $edges, 'direction' => $direction, 'types' => (array) $types];
        };
        $method = ['Shodhana_Method', 'Process'];
        $treatment = ['TREATED_BY', 'PRE_TREATED_BY', 'POST_TREATED_BY'];
        return PhpCompatibility::value(['therapy_formulation' => ['input' => ['Therapy'], 'steps' => [$step('USES_FORMULATION', 'out', 'Formulation')]], 'media_process' => ['input' => ['Media'], 'steps' => [$step('USES_MEDIA', 'in', $method)]], 'shodhana_method_process' => ['input' => ['Shodhana_Method'], 'steps' => [$step('FOLLOWED_BY', 'out', 'Process')]], 'process_shodhana_method' => ['input' => ['Process'], 'steps' => [$step('FOLLOWED_BY', 'in', 'Shodhana_Method')]], 'purified_compound_process' => ['input' => ['Purified_Compound'], 'steps' => [$step('PRODUCES', 'in', $method)]], 'method_toxic_substance' => ['input' => ['Shodhana_Method'], 'steps' => [$step('DETOXIFIES', 'out', 'Toxic_Substance')]], 'process_next_process' => ['input' => ['Process'], 'steps' => [$step('FOLLOWED_BY', 'out', 'Process')]], 'next_process_process' => ['input' => ['Process'], 'steps' => [$step('FOLLOWED_BY', 'in', 'Process')]], 'provenance_compound_method_media' => ['input' => ['Compound'], 'steps' => [$step('UNDERGOES', 'out', 'Shodhana_Method'), $step('USES_MEDIA', 'out', 'Media')]], 'provenance_media_method_compound' => ['input' => ['Media'], 'steps' => [$step('USES_MEDIA', 'in', 'Shodhana_Method'), $step('UNDERGOES', 'in', 'Compound')]], 'provenance_disease_therapy_formulation' => ['input' => ['Disease'], 'steps' => [$step($treatment, 'out', 'Therapy'), $step('USES_FORMULATION', 'out', 'Formulation')]], 'provenance_formulation_therapy_disease' => ['input' => ['Formulation'], 'steps' => [$step('USES_FORMULATION', 'in', 'Therapy'), $step($treatment, 'in', 'Disease')]]], 'array', false);
    }
    public static function intent($q, $type)
    {
        $q = PhpCompatibility::value($q, 'string', false);
        $type = PhpCompatibility::value($type, 'string', false);
        $evidence = preg_match('/\b(evidence|provenance|same study|same publication|shared reference)\b/i', $q);
        if ($evidence) {
            if ($type === 'Compound' && preg_match('/\b(media|medium)\b/i', $q)) {
                return PhpCompatibility::value('provenance_compound_method_media', 'string', true);
            }
            if ($type === 'Media' && preg_match('/\bcompounds?\b/i', $q)) {
                return PhpCompatibility::value('provenance_media_method_compound', 'string', true);
            }
            if ($type === 'Disease' && preg_match('/\bformulations?\b/i', $q)) {
                return PhpCompatibility::value('provenance_disease_therapy_formulation', 'string', true);
            }
            if ($type === 'Formulation' && preg_match('/\bdiseases?\b/i', $q)) {
                return PhpCompatibility::value('provenance_formulation_therapy_disease', 'string', true);
            }
        }
        if ($type === 'Therapy' && preg_match('/\bformulations?\b/i', $q)) {
            return PhpCompatibility::value('therapy_formulation', 'string', true);
        }
        if ($type === 'Media' && preg_match('/\b(methods?|process(?:es)?)\b/i', $q) && !preg_match('/\bcompounds?\b/i', $q)) {
            return PhpCompatibility::value('media_process', 'string', true);
        }
        if ($type === 'Purified_Compound' && preg_match('/\b(methods?|process(?:es)?)\b/i', $q) && !preg_match('/\b(stud(?:y|ies)|validat\w*)\b/i', $q)) {
            return PhpCompatibility::value('purified_compound_process', 'string', true);
        }
        if ($type === 'Shodhana_Method') {
            if (preg_match('/\b(detoxif\w*|toxic substances?|removes?)\b/i', $q)) {
                return PhpCompatibility::value('method_toxic_substance', 'string', true);
            }
            if (preg_match('/\b(follows?|after|next|subsequent|followed by)\b/i', $q)) {
                return PhpCompatibility::value('shodhana_method_process', 'string', true);
            }
        }
        if ($type === 'Process') {
            if (preg_match('/\b(before|previous|prior|precedes?|preceding)\b/i', $q) || preg_match('/\b(?:is|are)\s+followed\s+by\s+(?!what\b|which\b)/i', $q)) {
                return PhpCompatibility::value((preg_match('/\bmethods?\b/i', $q) ? 'process_shodhana_method' : 'next_process_process'), 'string', true);
            }
            if (preg_match('/\b(follows?|after|next|subsequent|followed by)\b/i', $q)) {
                return PhpCompatibility::value('process_next_process', 'string', true);
            }
        }
        return PhpCompatibility::value(null, 'string', true);
    }
}
