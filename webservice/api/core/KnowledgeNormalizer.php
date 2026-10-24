<?php
require_once __DIR__ . '/LegacyEvidence.php';

/**
 * KnowledgeNormalizer
 * 
 * Reusable normalization layer for packaging and deduplicating
 * retrieval results without flattening graph semantics.
 */
class KnowledgeNormalizer {
    
    /**
     * Generates a deterministic deduplication key for a retrieved fact.
     * 
     * @param string $primaryNode Name of the primary node (e.g. Method)
     * @param string $secondaryNode Name of the secondary node (e.g. Media or Study)
     * @param array $properties Canonical properties array
     * @return string Unique MD5 hash for deduplication
     */
    public static function generateDeduplicationKey($primaryNode, $secondaryNode, $properties) {
        // We hash the JSON representation of the properties to securely handle dynamic structures
        // (like nested validation properties vs safety evidence blocks)
        $keyString = $primaryNode . '|' . $secondaryNode . '|' . md5(json_encode($properties));
        return md5($keyString);
    }

    /**
     * Formats the final structured canonical Knowledge Object.
     * 
     * @param array $entity The seed entity ['name' => ..., 'type' => ...]
     * @param array $path The logical graph path
     * @param array $results The deduplicated result list
     * @param array|null $debug Optional debug information
     * @return array Canonical Knowledge Object envelope
     */
    public static function formatCanonical($entity, $path, $results, $debug = null) {
        $response = [
            'status' => empty($results) ? 'no_data' : 'success',
            'entity' => [
                'name' => $entity['name'],
                'type' => $entity['type']
            ],
            'path' => $path,
            'results' => array_values($results) // Ensure it serializes to a JSON array
        ];
        
        if ($debug !== null) {
            $response['debug'] = $debug;
        }
        
        return isset($entity['_evidence']) ? $entity['_evidence']->attach($response) : $response;
    }
}
