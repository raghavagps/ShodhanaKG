<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/ConversationalAnswer.php';
/** Deterministic view model over validated retrieval. Never changes retrieval. */
final class ResultsPresentation
{
    private static function typeLabel($type)
    {
        $type = PhpCompatibility::value($type, 'string', false);
        $label = trim(str_replace('_', ' ', $type));
        $singular = ['Symptoms' => 'Symptom', 'Doshas' => 'Dosha', 'Assessment Parameters' => 'Assessment Parameter'];
        return PhpCompatibility::value((isset($singular[$label]) ? $singular[$label] : $label), 'string', false);
    }
    private static function field($type)
    {
        $type = PhpCompatibility::value($type, 'string', false);
        return PhpCompatibility::value(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', self::typeLabel($type))), 'string', false);
    }
    private static function schema()
    {
        static $schema;
        return PhpCompatibility::value((isset($schema) ? $schema : $schema = json_decode(file_get_contents(__DIR__ . '/relationship_metadata.json'), true)['edges']), 'array', false);
    }
    private static function entityType($key, array $path)
    {
        $key = PhpCompatibility::value($key, 'string', false);
        if ($key === 'related_entity') {
            return PhpCompatibility::value(self::typeLabel((string) (end($path) ?  : 'Entity')), 'string', true);
        }
        $aliases = ['method' => 'Shodhana Method', 'study' => 'Confirmation Study'];
        if (isset($aliases[$key])) {
            return PhpCompatibility::value($aliases[$key], 'string', true);
        }
        foreach (self::schema() as $pairs) {
            foreach ($pairs as $pair) {
                foreach ($pair as $type) {
                    if (self::field($key) === self::field($type)) {
                        return PhpCompatibility::value(self::typeLabel($type), 'string', true);
                    }
                }
            }
        }
        return PhpCompatibility::value(null, 'string', true);
    }
    private static function entities(array $row, array $path)
    {
        if (isset($row['nodes'])) {
            return PhpCompatibility::value(array_map(static function ($node) {
                return ['type' => self::typeLabel($node['type']), 'label' => $node['label'], 'id' => $node['id']];
            }, $row['nodes']), 'array', false);
        }
        $entities = [];
        foreach ($row as $key => $value) {
            $type = self::entityType($key, $path);
            if (!$type || !is_string($value) || trim($value) === '') {
                continue;
            }
            $entities[] = ['type' => self::typeLabel($type), 'label' => $value];
        }
        return PhpCompatibility::value($entities, 'array', false);
    }
    private static function pathTypes($declared)
    {
        $declared = PhpCompatibility::value($declared, 'string', false);
        $types = [];
        foreach (explode('/', $declared) as $part) {
            $type = self::entityType(strtolower(str_replace(' ', '_', $part)), []);
            if ($type) {
                $types[] = self::field($type);
            }
        }
        return PhpCompatibility::value(array_values(array_unique($types)), 'array', false);
    }
    private static function relationships(array $path, array $row)
    {
        $schema = self::schema();
        $choices = [];
        $hops = [];
        for ($i = 0; $i < count($path); $i += 2) {
            $choices[$i] = self::pathTypes($path[$i]);
        }
        for ($i = 1; $i + 1 < count($path); $i += 2) {
            $edge = trim($path[$i], '<-> ');
            $edgeTypes = [$edge];
            // Legacy traversal notation declares an optional prefix family.
            // Expand only schema-known members; retain the raw path unchanged.
            if (preg_match('/^\[([A-Z]+(?:\/[A-Z]+)*)\]_(.+)$/', $edge, $match)) {
                $edgeTypes = [$match[2]];
                foreach (explode('/', $match[1]) as $prefix) {
                    $edgeTypes[] = $prefix . '_' . $match[2];
                }
            }
            $pairs = [];
            foreach ($edgeTypes as $type) {
                foreach ((isset($schema[$type]) ? $schema[$type] : []) as $pair) {
                    $pairs[json_encode($pair)] = $pair;
                }
            }
            $hops[$i] = array_values($pairs);
        }
        // Resolve union node types using the entire connected schema path. For
        // example, UNDERGOES constrains Method/Process to Shodhana Method before
        // resolving USES_MEDIA. Do not guess when multiple directed pairs remain.
        for ($pass = 0; $pass < count($choices); $pass++) {
            $before = $choices;
            foreach ($hops as $i => $pairs) {
                $valid = [];
                $left = [];
                $right = [];
                foreach ($pairs as $pair) {
                    $source = self::field($pair['source']);
                    $target = self::field($pair['target']);
                    foreach ([[$source, $target], [$target, $source]] as list($a, $b)) {
                        if (in_array($a, $choices[$i - 1], true) && in_array($b, $choices[$i + 1], true)) {
                            $valid[json_encode($pair)] = $pair;
                            $left[$a] = true;
                            $right[$b] = true;
                        }
                    }
                }
                $hops[$i] = array_values($valid);
                if ($valid) {
                    $choices[$i - 1] = array_keys($left);
                    $choices[$i + 1] = array_keys($right);
                }
            }
            if ($choices === $before) {
                break;
            }
        }
        $relationships = [];
        foreach ($hops as $pairs) {
            if (count($pairs) === 1) {
                // Always label stored schema direction, including inverse traversal.
                $relationships[] = self::typeLabel($pairs[0]['source']) . ' → ' . self::typeLabel($pairs[0]['target']);
            }
        }
        return PhpCompatibility::value($relationships, 'array', false);
    }
    public static function build(array $retrieval)
    {
        // Includes connectivity, direction, seed identity and raw-reference checks.
        $validated = EvidenceAnswerBuilder::build($retrieval);
        if ($validated['status'] !== 'success') {
            return PhpCompatibility::value($validated, 'array', false);
        }
        $citations = ConversationalAnswer::packet($retrieval);
        $results = [];
        $types = [];
        $groups = [];
        $path = (isset($retrieval['path']) ? $retrieval['path'] : []);
        $attributed = array_column((isset($retrieval['evidence_paths']) ? $retrieval['evidence_paths'] : []), 'paths', 'result_index');
        foreach ($retrieval['results'] as $i => $row) {
            $entities = self::entities($row, $path);
            $target = null;
            if (isset($row['nodes'])) {
                $target = end($entities);
            } else {
                $wanted = self::field((string) (end($path) ?  : ''));
                foreach ($entities as $entity) {
                    if (self::field($entity['type']) === $wanted) {
                        $target = $entity;
                    }
                }
                if (!$target && count($entities) === 1) {
                    $target = $entities[0];
                }
            }
            $requestedType = (isset($retrieval['query_plan']['result_type']) ? $retrieval['query_plan']['result_type'] : null);
            $requestedTypes = (isset($retrieval['query_plan']['requested_result_types']) ? $retrieval['query_plan']['requested_result_types'] : []);
            $paired = [];
            if ($requestedTypes) {
                foreach ($requestedTypes as $type) {
                    $matches = array_values(array_filter($entities, static function ($e) use ($type) {
                        return self::field($e['type']) === self::field($type);
                    }));
                    if (count($matches) !== 1) {
                        return PhpCompatibility::value(['status' => 'error', 'reason' => 'result_type_mismatch', 'answer' => 'The retrieved path does not contain the requested information.'], 'array', false);
                    }
                    $paired[] = $matches[0];
                }
                // Retain node identity from exact supporting paths for legacy rows.
                foreach ($paired as &$part) {
                    foreach ((isset($attributed[$i]) ? $attributed[$i] : []) as $support) {
                        foreach ($support['nodes'] as $node) {
                            if ($node['label'] === $part['label'] && self::field($node['type']) === self::field($part['type'])) {
                                $part['id'] = $node['id'];
                            }
                        }
                    }
                }
                unset($part);
                $target = null;
            }
            if ($requestedType && (!$target || self::field($target['type']) !== self::field($requestedType))) {
                return PhpCompatibility::value(['status' => 'error', 'reason' => 'result_type_mismatch', 'answer' => 'The retrieved result type does not match the requested information.'], 'array', false);
            }
            $label = ($paired ? implode(' → ', array_column($paired, 'label')) : (isset($target['label']) ? $target['label'] : implode(' · ', array_column($entities, 'label'))));
            if ($label === '') {
                return PhpCompatibility::value(['status' => 'error', 'answer' => 'The retrieved results could not be displayed consistently.'], 'array', false);
            }
            if ($target) {
                $types[$target['type']] = true;
            }
            // Group entity identity, never evidence. Every supporting fact remains
            // in its own citation, even when the visible entity is listed once.
            $key = json_encode([($paired ?  : (isset($target['id']) ? $target['id'] : null)), (isset($target['type']) ? $target['type'] : null), $label]);
            if (!isset($groups[$key])) {
                $groups[$key] = count($results);
                $results[] = ['id' => 'f' . ($i + 1), 'label' => $label, 'fact_ids' => [], 'citation_ids' => [], 'record_indices' => []];
            }
            $group = $groups[$key];
            $results[$group]['fact_ids'][] = 'f' . ($i + 1);
            $results[$group]['citation_ids'][] = (string) ($i + 1);
            $results[$group]['record_indices'][] = $i;
            $citations[$i]['entities'] = $entities;
            if (isset($row['evidence_groups'])) {
                $citations[$i]['evidence_groups'] = $row['evidence_groups'];
            }
            if (isset($row['edges'])) {
                $nodes = array_column($row['nodes'], null, 'id');
                foreach ($row['edges'] as $j => $edge) {
                    $source = $nodes[$edge['source']];
                    $targetNode = $nodes[$edge['target']];
                    $citations[$i]['scopes'][$j]['relationship'] = self::typeLabel($source['type']) . ' → ' . self::typeLabel($targetNode['type']);
                    $citations[$i]['scopes'][$j]['edge_id'] = (isset($edge['id']) ? $edge['id'] : null);
                    $citations[$i]['scopes'][$j]['edge_type'] = $edge['type'];
                    $citations[$i]['scopes'][$j] += EdgePresentation::metadata($edge);
                    $citations[$i]['scopes'][$j]['source_id'] = $edge['source'];
                    $citations[$i]['scopes'][$j]['target_id'] = $edge['target'];
                    $citations[$i]['scopes'][$j]['entities'] = [['type' => self::typeLabel($source['type']), 'label' => $source['label']], ['type' => self::typeLabel($targetNode['type']), 'label' => $targetNode['label']]];
                }
            } elseif (isset($attributed[$i])) {
                $scopes = [];
                $relations = [];
                foreach ($attributed[$i] as $support) {
                    $nodes = array_column($support['nodes'], null, 'id');
                    foreach ($support['edges'] as $edge) {
                        $source = $nodes[$edge['source']];
                        $targetNode = $nodes[$edge['target']];
                        $relation = self::typeLabel($source['type']) . ' → ' . self::typeLabel($targetNode['type']);
                        $relations[$relation] = true;
                        $scopes[$edge['id']] = ['description' => $source['label'] . ' → ' . $targetNode['label'], 'relationship' => $relation, 'edge_id' => $edge['id'], 'edge_type' => $edge['type'], 'source_id' => $edge['source'], 'target_id' => $edge['target'], 'identifiers' => $edge['evidence'], 'properties' => $edge['properties'], 'display_properties' => EdgePresentation::properties($edge['properties']), 'treatment_phase' => EdgePresentation::phase($edge['type']), 'entities' => [['type' => self::typeLabel($source['type']), 'label' => $source['label']], ['type' => self::typeLabel($targetNode['type']), 'label' => $targetNode['label']]]];
                    }
                }
                $citations[$i]['scopes'] = array_values($scopes);
                $citations[$i]['relationships'] = array_keys($relations);
                $citations[$i]['attribution'] = 'per_edge';
            } else {
                // Keep every supplied flat property, including future keys.
                // These are record scopes: no unavailable edge identity is invented.
                $scopes = [];
                $properties = [];
                foreach ($row as $key => $value) {
                    if (self::entityType($key, $path) || $key === 'relationship') {
                        continue;
                    }
                    if (is_array($value)) {
                        $scopes[] = ['description' => ucwords(str_replace('_', ' ', $key)), 'identifiers' => EvidenceCorrelator::extractIdentifiers($value), 'properties' => $value];
                    } else {
                        $properties[$key] = $value;
                    }
                }
                if ($properties || !$scopes) {
                    $scopes[] = ['description' => 'Recorded association', 'identifiers' => EvidenceCorrelator::extractIdentifiers($properties), 'properties' => $properties];
                }
                $citations[$i]['scopes'] = $scopes;
                $relations = self::relationships($path, $row);
                $citations[$i]['relationships'] = $relations;
                foreach ($citations[$i]['scopes'] as &$scope) {
                    // A flat row cannot attribute a property to an individual hop.
                    // Keep it scoped to the original record, not a fabricated edge.
                    $scope['entities'] = $entities;
                    if (count($path) === 3 && count($relations) === 1) {
                        $scope['relationship'] = $relations[0];
                    }
                }
                unset($scope);
            }
        }
        $seed = $retrieval['entity']['name'];
        $kind = (count($types) === 1 ? array_keys($types)[0] : 'Result');
        $plurals = ['Symptom' => 'Symptoms', 'Therapy' => 'Therapies', 'Disease' => 'Diseases', 'Process' => 'Processes', 'Media' => 'Media'];
        $plural = (isset($plurals[$kind]) ? $plurals[$kind] : $kind . 's');
        $heading = $plural . ' associated with ' . $seed;
        $intro = 'The available records associate ' . $seed . ' with the following ' . strtolower($plural) . ':';
        $constraints = (isset($retrieval['query_plan']['constraints']) ? $retrieval['query_plan']['constraints'] : []);
        if ($constraints) {
            $intro .= ' Filtered to ' . implode(', ', array_map(static function ($c) {
                return $c['name'] . ' (' . str_replace('_', ' ', $c['type']) . ')';
            }, $constraints)) . '.';
        }
        if (array_filter($citations, static function ($c) {
            return count((isset($c['evidence_groups']) ? $c['evidence_groups'] : [])) > 1;
        })) {
            $intro .= ' Different studies may support different steps; each citation identifies its own edge.';
        }
        $lines = [];
        foreach ($results as $i => $result) {
            $lines[] = $i + 1 . '. ' . $result['label'] . ' [' . implode(', ', $result['citation_ids']) . ']';
        }
        return PhpCompatibility::value(['status' => 'success', 'answer' => $heading . "\n\n" . $intro . "\n\n" . implode("\n", $lines), 'pagination' => (isset($retrieval['pagination']) ? $retrieval['pagination'] : null), 'query_plan' => (isset($retrieval['query_plan']) ? $retrieval['query_plan'] : null), 'answer_heading' => $heading, 'answer_intro' => $intro, 'answer_results' => $results, 'answer_blocks' => [], 'citations' => $citations, 'generation' => ['mode' => 'deterministic', 'fallback_reason' => null, 'latency_ms' => 0]], 'array', false);
    }
}
