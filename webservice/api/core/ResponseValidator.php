<?php

require_once __DIR__ . '/PhpCompatibility.php';
class ResponseValidator
{
    private static $schemaEntities = ['Swedana', 'Bhavana', 'Mardana', 'Nirvapana', 'Dhalana', 'Bharjana', 'Shodhana', 'Marana', 'Cow Milk', 'Goat Milk', 'Water', 'Juice', 'Urine', 'Ghee', 'Honey', 'Decoction', 'Alopecia', 'Ankylosing Spondylitis', 'Diabetes', 'Fever', 'Cough', 'Asthma', 'Copper', 'Zinc', 'Croton tiglium Linn.', 'Datura metel L.', 'Mercury', 'Arsenic', 'Vati', 'Bhasma', 'Pishti', 'Guggulu', 'Ghrita', 'Taila', 'Churna', 'Kashaya', 'Asava', 'Arishta', 'Rasayana', 'Lepa'];
    public static function validate($llmOutput, array $context)
    {
        $llmOutput = PhpCompatibility::value($llmOutput, 'string', false);
        $contextJson = json_encode($context);
        // 1. Validate DOI
        if (preg_match_all('/10\.\d{4,9}\/[-._;()\/:A-Z0-9]+/i', $llmOutput, $matches)) {
            foreach ($matches[0] as $doi) {
                if (stripos($contextJson, $doi) === false) {
                    return PhpCompatibility::value(false, 'bool', false);
                    // Hallucinated DOI
                }
            }
        }
        // 2. Validate PMID
        if (preg_match_all('/\b(?:PMID|PubMed ID|PubMed)\s*:?\s*(\d{7,8})\b/i', $llmOutput, $matches)) {
            foreach ($matches[1] as $pmid) {
                if (stripos($contextJson, $pmid) === false) {
                    return PhpCompatibility::value(false, 'bool', false);
                    // Hallucinated PMID
                }
            }
        }
        // 3. Validate AYUSH ID
        if (preg_match_all('/\bAYUSH-[-a-zA-Z0-9]+\b/i', $llmOutput, $matches)) {
            foreach ($matches[0] as $ayush) {
                if (stripos($contextJson, $ayush) === false) {
                    return PhpCompatibility::value(false, 'bool', false);
                    // Hallucinated AYUSH ID
                }
            }
        }
        // 4. Validate Temperatures (Field-aware numeric)
        if (preg_match_all('/\b(\d+)\s*(?:°|degrees?)\s*[CF]?(?:\b|\s|$|\.)/i', $llmOutput, $matches)) {
            foreach ($matches[1] as $temp) {
                if (!preg_match('/\b' . preg_quote($temp, '/') . '\b/', $contextJson)) {
                    return PhpCompatibility::value(false, 'bool', false);
                    // Hallucinated temperature
                }
            }
        }
        // 5. Validate Quantitative Measurements (Field-aware numeric)
        if (preg_match_all('/\b(\d+)\s*(?:cycles?|times|hours?|days?|mins?|ml|mg|g|kg)\b/i', $llmOutput, $matches)) {
            foreach ($matches[1] as $qty) {
                if (!preg_match('/\b' . preg_quote($qty, '/') . '\b/', $contextJson)) {
                    return PhpCompatibility::value(false, 'bool', false);
                    // Hallucinated quantity
                }
            }
        }
        // 6. Validate Schema Entities
        foreach (self::$schemaEntities as $entity) {
            // If the LLM mentions a specific domain entity...
            if (preg_match('/\b' . preg_quote($entity, '/') . '\b/i', $llmOutput)) {
                // ...it MUST exist in the context
                if (stripos($contextJson, $entity) === false) {
                    return PhpCompatibility::value(false, 'bool', false);
                    // Hallucinated schema entity
                }
            }
        }
        return PhpCompatibility::value(true, 'bool', false);
    }
}
