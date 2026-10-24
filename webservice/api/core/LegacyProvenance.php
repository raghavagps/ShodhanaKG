<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/EvidenceTraversal.php';
/** Preserve the existing flat results while sharing the evidence-path retriever. */
class LegacyProvenance
{
    public static function run($endpoint, $name, $debug, $engine = null)
    {
        $endpoint = PhpCompatibility::value($endpoint, 'string', false);
        $name = PhpCompatibility::value($name, 'string', false);
        $debug = PhpCompatibility::value($debug, 'bool', false);
        $engine = PhpCompatibility::value($engine, 'EvidenceTraversal', true);
        $definition = TraversalCatalog::all()[$endpoint];
        // The legacy formulation endpoint also accepts Ayurvedic_Formulation seeds.
        if ($endpoint === 'provenance_formulation_therapy_disease') {
            $definition['input'][] = 'Ayurvedic_Formulation';
        }
        $engine = isset($engine) ? $engine : new EvidenceTraversal();
        $response = $engine->runDefinition($definition, $name, $debug);
        if (!isset($response['results'])) {
            return PhpCompatibility::value($response, 'array', false);
        }
        $results = [];
        $evidence = new LegacyEvidence();
        foreach ($response['results'] as $chain) {
            $nodes = $chain['nodes'];
            $compound = strpos($endpoint, 'compound') !== false;
            // Legacy properties prefer the media/formulation edge, regardless of direction.
            $edges = $chain['edges'];
            $preferredType = ($compound ? 'USES_MEDIA' : 'USES_FORMULATION');
            $preferred = ($edges[0]['type'] === $preferredType ? $edges[0] : $edges[1]);
            $other = ($edges[0]['type'] === $preferredType ? $edges[1] : $edges[0]);
            $props = $preferred['evidence'];
            if ($compound) {
                foreach (['procedure', 'result'] as $field) {
                    if ($props[$field] === '') {
                        $props[$field] = $other['evidence'][$field];
                    }
                }
                foreach (['temperature', 'duration', 'heating_cycles', 'repetitions', 'quantity', 'sequence'] as $field) {
                    $value = (isset($preferred['properties'][$field]) ? $preferred['properties'][$field] : (isset($other['properties'][$field]) ? $other['properties'][$field] : null));
                    if ($value !== null && $value !== '') {
                        $props[$field] = $value;
                    }
                }
                $entry = [($nodes[1]['type'] === 'Process' ? 'process' : 'method') => $nodes[1]['label']];
                $entry[($nodes[2]['type'] === 'Compound' ? 'compound' : 'media')] = $nodes[2]['label'];
            } else {
                foreach ($preferred['properties'] as $key => $value) {
                    if ($key !== 'ref' && strpos($key, '@') !== 0 && EdgePresentation::meaningful($value)) {
                        $props[$key] = $value;
                    }
                }
                $props['phase'] = $chain['phase'];
                $entry = ['therapy' => $nodes[1]['label'], ($nodes[2]['type'] === 'Disease' ? 'disease' : 'formulation') => $nodes[2]['label']];
            }
            $entry['properties'] = $props;
            $evidence->recordPath($entry, $chain);
            $results[KnowledgeNormalizer::generateDeduplicationKey($nodes[1]['label'], $nodes[2]['label'], $props)] = $entry;
        }
        $response['results'] = array_values($results);
        if (isset($response['debug'])) {
            $response['debug']['raw_records'] = $response['debug']['candidate_paths'];
            $response['debug']['duplicates_filtered'] = $response['debug']['candidate_paths'] - count($results);
            $response['debug']['final_count'] = count($results);
        }
        $paths = ['provenance_compound_method_media' => ['Compound', 'UNDERGOES', 'Method/Process', 'USES_MEDIA', 'Media'], 'provenance_media_method_compound' => ['Media', '<- USES_MEDIA', 'Method/Process', '<- UNDERGOES', 'Compound'], 'provenance_disease_therapy_formulation' => ['Disease', '[PRE/POST]_TREATED_BY', 'Therapy', 'USES_FORMULATION', 'Formulation'], 'provenance_formulation_therapy_disease' => ['Formulation', '<- USES_FORMULATION', 'Therapy', '<- [PRE/POST]_TREATED_BY', 'Disease']];
        $response['path'] = $paths[$endpoint];
        return PhpCompatibility::value($evidence->attach($response), 'array', false);
    }
}
