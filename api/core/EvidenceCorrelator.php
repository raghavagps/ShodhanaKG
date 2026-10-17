<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . "/ReferenceClassifier.php";
/**
 * Extracts and matches evidence identifiers to reject uncorrelated graph paths.
 */
class EvidenceCorrelator
{
    /**
     * Extracts reference identifiers and canonical edge properties.
     * Maps capitalization variants correctly.
     * 
     * @param array $edge The raw ArcadeDB edge
     * @return array Array containing procedure, result, pubmed_id, doi, ayush_id
     */
    public static function extractIdentifiers($edge, $edgeId = null)
    {
        $edgeId = PhpCompatibility::value($edgeId, 'string', true);
        $references = ReferenceClassifier::resolve($edge, $edgeId);
        $pubmedId = '';
        $doi = '';
        $ayushId = '';
        $unclassified = [];
        $conflict = in_array('conflict', array_column($references, 'status'), true);
        foreach ($references as $reference) {
            if ($conflict || !$reference['namespace']) {
                $unclassified[] = $reference['raw'];
                continue;
            }
            if ($reference['namespace'] === 'PMID') {
                $pubmedId = $reference['identifier'];
            } elseif ($reference['namespace'] === 'DOI') {
                $doi = $reference['identifier'];
            } elseif ($reference['namespace'] === 'AYUSH') {
                $ayushId = $reference['identifier'];
            }
        }
        $procedure = '';
        if (!empty($edge['Procedure'])) {
            $procedure = $edge['Procedure'];
        } elseif (!empty($edge['procedure'])) {
            $procedure = $edge['procedure'];
        }
        $result = '';
        if (!empty($edge['Result'])) {
            $result = $edge['Result'];
        } elseif (!empty($edge['result'])) {
            $result = $edge['result'];
        }
        $identifiers = ['procedure' => (string) $procedure, 'result' => (string) $result, 'pubmed_id' => (string) $pubmedId, 'doi' => (string) $doi, 'ayush_id' => (string) $ayushId, 'references' => $references];
        if ($unclassified) {
            $identifiers['unclassified_reference'] = implode('; ', array_unique($unclassified));
        }
        return $identifiers;
    }
    /**
     * Checks if two sets of edge properties share the same evidence reference.
     * Implements strict deterministic correlation rules.
     * 
     * @param array $props1 Canonical properties of edge 1
     * @param array $props2 Canonical properties of edge 2
     * @return bool True if they share the same reference, false otherwise
     */
    public static function correlate($props1, $props2)
    {
        return self::matchingIdentifier($props1, $props2) !== null;
    }
    /** The first comparable identifier controls the decision, including mismatch. */
    public static function matchingIdentifier(array $props1, array $props2)
    {
        foreach (['pubmed_id', 'doi', 'ayush_id'] as $type) {
            $a = (string) (isset($props1[$type]) ? $props1[$type] : '');
            $b = (string) (isset($props2[$type]) ? $props2[$type] : '');
            if ($a !== '' && $b !== '') {
                return PhpCompatibility::value(($a === $b ? ['type' => $type, 'value' => $a] : null), 'array', true);
            }
        }
        return PhpCompatibility::value(null, 'array', true);
    }
}
