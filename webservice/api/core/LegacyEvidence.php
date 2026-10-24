<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/GraphRetriever.php';
require_once __DIR__ . '/EvidenceCorrelator.php';
require_once __DIR__ . '/PathEvidence.php';
require_once __DIR__ . '/EdgePresentation.php';
/** Request-local provenance, recorded at the point a legacy traversal accepts a row. */
final class LegacyEvidence
{
    private $seed;
    private $records = [];
    private $paths = [];
    private $query;
    public function __construct(array $seed = [], $query = null)
    {
        $query = PhpCompatibility::value($query, 'callable', true);
        $this->seed = $seed;
        $this->query = (isset($query) ? $query : static function ($sql) {
            return GraphRetriever::execute($sql);
        });
    }
    private static function key(array $row)
    {
        // Transitional technical metadata is not part of the legacy row identity.
        unset($row['supporting_edges'], $row['edge_properties']);
        return PhpCompatibility::value(hash('sha256', PhpCompatibility::jsonEncode($row, 0)), 'string', false);
    }
    private function raw($id)
    {
        $id = PhpCompatibility::value($id, 'string', false);
        if (!preg_match('/^#\d+:\d+$/D', $id)) {
            throw new RuntimeException('Invalid evidence record ID');
        }
        if (!isset($this->records[$id])) {
            $rows = call_user_func($this->query, 'SELECT FROM ' . $id);
            if (count($rows) !== 1 || (isset($rows[0]['@rid']) ? $rows[0]['@rid'] : null) !== $id) {
                throw new RuntimeException('Missing evidence record');
            }
            $this->records[$id] = $rows[0];
        }
        return PhpCompatibility::value($this->records[$id], 'array', false);
    }
    private function node($id)
    {
        $id = PhpCompatibility::value($id, 'string', false);
        $node = $this->raw($id);
        if ((isset($node['@cat']) ? $node['@cat'] : null) !== 'v') {
            throw new RuntimeException('Evidence endpoint is not a vertex');
        }
        return PhpCompatibility::value(['id' => $id, 'type' => $node['@type'], 'label' => vertex_label($node)], 'array', false);
    }
    public function record(array $row, array $acceptedEdges, $correlation = 'shared_study')
    {
        $correlation = PhpCompatibility::value($correlation, 'string', false);
        $current = (isset($this->seed['@rid']) ? $this->seed['@rid'] : (isset($this->seed['rid']) ? $this->seed['rid'] : ''));
        $path = ['nodes' => [$this->node($current)], 'edges' => [], 'correlation' => $correlation];
        foreach ($acceptedEdges as $accepted) {
            if ($accepted === null) {
                continue;
            }
            $raw = $this->raw((isset($accepted['@rid']) ? $accepted['@rid'] : (isset($accepted['edge_rid']) ? $accepted['edge_rid'] : '')));
            if ((isset($raw['@cat']) ? $raw['@cat'] : null) !== 'e') {
                throw new RuntimeException('Evidence record is not an edge');
            }
            $before = EvidenceCorrelator::extractIdentifiers($accepted);
            $after = EvidenceCorrelator::extractIdentifiers($raw);
            foreach (['pubmed_id', 'doi', 'ayush_id', 'unclassified_reference'] as $key) {
                if ((isset($before[$key]) ? $before[$key] : '') !== (isset($after[$key]) ? $after[$key] : '')) {
                    throw new RuntimeException('Evidence changed during retrieval');
                }
            }
            if ($raw['@out'] === $current) {
                $next = $raw['@in'];
            } elseif ($raw['@in'] === $current) {
                $next = $raw['@out'];
            } else {
                throw new RuntimeException('Disconnected legacy evidence');
            }
            $props = array_filter($raw, static function ($key) {
                return $key[0] !== '@';
            }, ARRAY_FILTER_USE_KEY);
            $path['edges'][] = ['id' => $raw['@rid'], 'type' => $raw['@type'], 'source' => $raw['@out'], 'target' => $raw['@in'], 'properties' => $props, 'evidence' => EvidenceCorrelator::extractIdentifiers($raw)];
            $path['nodes'][] = $this->node($next);
            $current = $next;
        }
        $this->recordPath($row, $path);
    }
    /** Also preserve paths already supplied by the validated canonical retriever. */
    public function recordPath(array $row, array $path)
    {
        $path = ['nodes' => $path['nodes'], 'edges' => $path['edges'], 'correlation' => (isset($path['correlation']) ? $path['correlation'] : 'shared_study')];
        self::validate($path);
        self::validateRow($row, $path);
        $signature = PhpCompatibility::jsonEncode(array_column($path['edges'], 'id'), 0);
        $this->paths[self::key($row)][$signature] = $path;
    }
    public function attach(array $response)
    {
        $response['evidence_paths'] = [];
        foreach ($response['results'] as $i => $row) {
            $paths = call_user_func(function () use (&$row) {
                $__kgCoalesce1 = $this->paths;
                return (isset($__kgCoalesce1[self::key($row)]) ? $__kgCoalesce1[self::key($row)] : []);
            });
            if (!$paths) {
                throw new RuntimeException('Missing legacy result provenance');
            }
            $response['results'][$i]['edge_properties'] = [];
            foreach ($paths as $path) {
                foreach ($path['edges'] as $edge) {
                    $response['results'][$i]['edge_properties'][$edge['id']] = ['edge_id' => $edge['id'], 'type' => $edge['type'], 'source' => $edge['source'], 'target' => $edge['target'], 'properties' => $edge['properties']] + EdgePresentation::metadata($edge);
                }
            }
            $response['evidence_paths'][] = ['result_index' => $i, 'paths' => array_values($paths)];
        }
        return PhpCompatibility::value($response, 'array', false);
    }
    public static function validate(array $path)
    {
        $nodes = (isset($path['nodes']) ? $path['nodes'] : []);
        $edges = (isset($path['edges']) ? $path['edges'] : []);
        if (!$edges || count($nodes) !== count($edges) + 1) {
            throw new RuntimeException('Invalid legacy evidence path');
        }
        foreach ($nodes as $node) {
            foreach (['id', 'type', 'label'] as $key) {
                if (!is_string((isset($node[$key]) ? $node[$key] : null)) || $node[$key] === '') {
                    throw new RuntimeException('Invalid evidence node');
                }
            }
        }
        $grouped = (isset($path['evidence_policy']) ? $path['evidence_policy'] : '') === 'separate_treatment';
        if ($grouped && ((isset($path['evidence_groups']) ? $path['evidence_groups'] : null) === null || $path['evidence_groups'] !== PathEvidence::groups($path, true))) {
            throw new RuntimeException('Invalid study groups');
        }
        $correlation = (isset($path['correlation']) ? $path['correlation'] : ($grouped ? 'shared_study' : ''));
        if (!in_array($correlation, ['shared_study', 'cross_study_association'], true)) {
            throw new RuntimeException('Invalid evidence correlation');
        }
        if ($correlation === 'cross_study_association' && array_column($edges, 'type') !== ['UNDERGOES', 'PRODUCES', 'TREATS']) {
            throw new RuntimeException('Invalid cross-study association');
        }
        static $schema;
        $schema = (isset($schema) ? $schema : json_decode(file_get_contents(__DIR__ . '/relationship_metadata.json'), true)['edges']);
        $byId = array_column($nodes, null, 'id');
        foreach ($edges as $i => $edge) {
            if (!preg_match('/^#\d+:\d+$/D', (isset($edge['id']) ? $edge['id'] : '')) || !is_string((isset($edge['type']) ? $edge['type'] : null)) || !is_array((isset($edge['properties']) ? $edge['properties'] : null)) || !is_array((isset($edge['evidence']) ? $edge['evidence'] : null))) {
                throw new RuntimeException('Invalid evidence edge');
            }
            $a = $nodes[$i]['id'];
            $b = $nodes[$i + 1]['id'];
            if (!($edge['source'] === $a && $edge['target'] === $b || $edge['source'] === $b && $edge['target'] === $a)) {
                throw new RuntimeException('Disconnected evidence edge');
            }
            $pair = ['source' => $byId[$edge['source']]['type'], 'target' => $byId[$edge['target']]['type']];
            if (!in_array($pair, (isset($schema[$edge['type']]) ? $schema[$edge['type']] : []), true)) {
                throw new RuntimeException('Invalid evidence type/direction');
            }
            if ($edge['evidence'] !== EvidenceCorrelator::extractIdentifiers($edge['properties'], $edge['id'])) {
                throw new RuntimeException('Evidence properties disagree with identifiers');
            }
            // The therapeutic-use contract explicitly associates a separately cited final indication.
            $separate = $correlation === 'cross_study_association' && $i === count($edges) - 1 && $edge['type'] === 'TREATS';
            if ($i > 0 && !$separate && !$grouped && !EvidenceCorrelator::correlate($edges[$i - 1]['evidence'], $edge['evidence'])) {
                throw new RuntimeException('Uncorrelated legacy evidence');
            }
        }
    }
    public static function validateResponse(array $retrieval)
    {
        if (!array_key_exists('evidence_paths', $retrieval)) {
            return;
        }
        // historical responses
        $seen = [];
        $edges = [];
        foreach ($retrieval['evidence_paths'] as $entry) {
            $i = $entry['result_index'];
            if (!is_int($i) || !isset($retrieval['results'][$i]) || isset($seen[$i]) || !$entry['paths']) {
                throw new RuntimeException('Invalid evidence result mapping');
            }
            $seen[$i] = true;
            foreach ($entry['paths'] as $path) {
                self::validate($path);
                self::validateRow($retrieval['results'][$i], $path);
                if ($path['nodes'][0]['label'] !== $retrieval['entity']['name'] || $path['nodes'][0]['type'] !== $retrieval['entity']['type']) {
                    throw new RuntimeException('Wrong evidence seed');
                }
                foreach ($path['edges'] as $edge) {
                    if (isset($edges[$edge['id']]) && $edges[$edge['id']] !== $edge) {
                        throw new RuntimeException('Conflicting evidence identity');
                    }
                    $edges[$edge['id']] = $edge;
                }
            }
        }
        if (count($seen) !== count($retrieval['results'])) {
            throw new RuntimeException('Incomplete evidence result mapping');
        }
    }
    private static function validateRow(array $row, array $path)
    {
        $labels = array_column($path['nodes'], 'label');
        foreach (['disease', 'symptom', 'dosha', 'ayurvedic_diagnosis', 'assessment_parameter', 'therapy', 'formulation', 'related_entity', 'compound', 'purified_compound', 'method', 'shodhana_method', 'process', 'media', 'study', 'toxic_substance', 'indication'] as $field) {
            if (isset($row[$field]) && $row[$field] !== '' && !in_array($row[$field], $labels, true)) {
                throw new RuntimeException('Evidence does not support result entity');
            }
        }
        foreach (['properties', 'validation_properties'] as $scope) {
            foreach (['pubmed_id', 'doi', 'ayush_id', 'unclassified_reference', 'procedure', 'result'] as $field) {
                $value = (isset($row[$scope][$field]) ? $row[$scope][$field] : '');
                if ($value === '') {
                    continue;
                }
                $values = array_map(static function ($edge) use ($field) {
                    return (isset($edge['evidence'][$field]) ? $edge['evidence'][$field] : '');
                }, $path['edges']);
                if (!in_array($value, $values, true)) {
                    throw new RuntimeException('Evidence does not support result property');
                }
            }
        }
    }
}
