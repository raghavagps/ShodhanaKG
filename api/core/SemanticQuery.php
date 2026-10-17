<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/EvidenceTraversal.php';
/** Typed, bounded composition over supported schema relationships. No generated SQL. */
final class SemanticQuery
{
    const MAX_HOPS = 5;
    public static function target($question)
    {
        $question = PhpCompatibility::value($question, 'string', false);
        require_once __DIR__ . '/QueryUnderstanding.php';
        return PhpCompatibility::value(QueryUnderstanding::target($question), 'string', true);
    }
    public static function definitions($source, $target)
    {
        $source = PhpCompatibility::value($source, 'string', false);
        $target = PhpCompatibility::value($target, 'string', false);
        $schema = json_decode(file_get_contents(__DIR__ . '/relationship_metadata.json'), true)['edges'];
        $disease = ['TREATED_BY', 'PRE_TREATED_BY', 'POST_TREATED_BY', 'USES_FORMULATION'];
        $production = ['UNDERGOES', 'FOLLOWED_BY', 'PRODUCES'];
        $allowed = array_merge($disease, $production, ['USES_MEDIA', 'DETOXIFIES', 'USED_FOR_TREATMENT', 'TREATS', 'USED_IN_FORMULATION']);
        $adjacency = [];
        foreach ($allowed as $edge) {
            foreach ((isset($schema[$edge]) ? $schema[$edge] : []) as $pair) {
                $adjacency[$pair['source']][] = ['edges' => [$edge], 'direction' => 'out', 'types' => [$pair['target']]];
                $adjacency[$pair['target']][] = ['edges' => [$edge], 'direction' => 'in', 'types' => [$pair['source']]];
            }
        }
        $definitions = [];
        $walk = function ($type, $steps, $visited) use (&$walk, &$definitions, $adjacency, $source, $target) {
            if ($type === $target && $steps) {
                $definitions[] = ['input' => [$source], 'steps' => $steps, 'separate_treatment' => true];
                return;
            }
            if (count($steps) >= self::MAX_HOPS) {
                return;
            }
            foreach ((isset($adjacency[$type]) ? $adjacency[$type] : []) as $step) {
                $next = $step['types'][0];
                $count = (isset($visited[$next]) ? $visited[$next] : 0);
                if ($count >= ($next === 'Process' ? 2 : 1)) {
                    continue;
                }
                // Avoid reversing a relationship immediately and hopping between unrelated seeds.
                $previous = ($steps ? $steps[count($steps) - 1] : null);
                if ($previous && $previous['edges'] === $step['edges'] && $previous['direction'] !== $step['direction']) {
                    continue;
                }
                $copy = $visited;
                $copy[$next] = $count + 1;
                $walk($next, array_merge($steps, [$step]), $copy);
            }
        };
        $walk($source, [], [$source => 1]);
        return PhpCompatibility::value($definitions, 'array', false);
    }
    public static function run($source, $name, $target, array $constraints = [], $offset = 0, $limit = 1000)
    {
        $source = PhpCompatibility::value($source, 'string', false);
        $name = PhpCompatibility::value($name, 'string', false);
        $target = PhpCompatibility::value($target, 'string', false);
        $offset = PhpCompatibility::value($offset, 'int', false);
        $limit = PhpCompatibility::value($limit, 'int', false);
        $definitions = self::definitions($source, $target);
        if (!$definitions) {
            return PhpCompatibility::value(['status' => 'unsupported', 'reason' => 'no_supported_schema_path', 'results' => []], 'array', false);
        }
        $resolvedConstraints = [];
        foreach ($constraints as $constraint) {
            if (!is_array($constraint)) {
                return PhpCompatibility::value(['status' => 'needs_clarification', 'reason' => 'invalid_constraint', 'results' => []], 'array', false);
            }
            $type = (isset($constraint['type']) ? $constraint['type'] : '');
            $value = (isset($constraint['name']) ? $constraint['name'] : '');
            // Types must occur in the validated schema, not in a client-provided SQL fragment.
            $known = false;
            foreach ($definitions as $definition) {
                foreach ($definition['steps'] as $step) {
                    if (in_array($type, $step['types'], true)) {
                        $known = true;
                    }
                }
            }
            if (!$known || !is_string($value)) {
                return PhpCompatibility::value(['status' => 'needs_clarification', 'reason' => 'incompatible_constraint', 'results' => []], 'array', false);
            }
            $node = EntityResolver::resolve($type, (isset($constraint['id']) ? $constraint['id'] : $value));
            if (!$node) {
                return PhpCompatibility::value(['status' => 'entity_not_found', 'results' => []], 'array', false);
            }
            if (isset($resolvedConstraints[$type]) && $resolvedConstraints[$type] !== $node['rid']) {
                return PhpCompatibility::value(['status' => 'needs_clarification', 'reason' => 'conflicting_constraints', 'results' => []], 'array', false);
            }
            $resolvedConstraints[$type] = $node['rid'];
        }
        $results = [];
        $entity = ['name' => $name, 'type' => $source];
        $engine = new EvidenceTraversal();
        foreach ($definitions as $definition) {
            $types = array_merge(...array_column($definition['steps'], 'types'));
            if (array_diff(array_keys($resolvedConstraints), $types)) {
                continue;
            }
            $definition['constraints'] = $resolvedConstraints;
            $response = $engine->runDefinition($definition, $name, true);
            if (in_array($response['status'], ['error', 'entity_not_found'], true)) {
                return PhpCompatibility::value($response, 'array', false);
            }
            $entity = $response['entity'];
            foreach ($response['results'] as $path) {
                $results[implode('|', array_column($path['edges'], 'id'))] = $path;
            }
        }
        ksort($results, SORT_STRING);
        $total = count($results);
        $limit = max(1, min(1000, $limit));
        $offset = max(0, $offset);
        $response = KnowledgeNormalizer::formatCanonical($entity, [$source, 'validated path', $target], array_slice(array_values($results), $offset, $limit));
        $response['query_plan'] = ['version' => 1, 'subject' => $entity, 'result_type' => $target, 'constraints' => $constraints, 'max_hops' => self::MAX_HOPS, 'evidence_policy' => 'separate_treatment'];
        $response['pagination'] = ['offset' => $offset, 'limit' => $limit, 'total' => $total, 'has_more' => $offset + $limit < $total, 'next_offset' => ($offset + $limit < $total ? $offset + $limit : null)];
        return PhpCompatibility::value($response, 'array', false);
    }
    public static function routeMultiple(array $groups, $question)
    {
        $question = PhpCompatibility::value($question, 'string', false);
        $target = self::target($question);
        if (!$target || count($groups) > 3) {
            return PhpCompatibility::value(null, 'array', true);
        }
        foreach ($groups as $group) {
            if (count(array_unique(array_column($group, 'inferred_type'))) > 1) {
                $qualified = false;
                foreach ($group as $node) {
                    $word = ($node['inferred_type'] === 'Shodhana_Method' ? 'method' : ($node['inferred_type'] === 'Process' ? 'process' : null));
                    if ($word && preg_match('/\b' . $word . '\s+' . preg_quote($node['name'], '/') . '\b/i', $question)) {
                        $qualified = true;
                    }
                }
                if (!$qualified) {
                    return PhpCompatibility::value(null, 'array', true);
                }
            }
        }
        $entities = array_map(static function ($group) use ($question) {
            return ConversationEntities::select($group, $question);
        }, $groups);
        // Same-type entities are alternatives/comparisons, not positional constraints.
        if (count(array_unique(array_column($entities, 'inferred_type'))) !== count($entities)) {
            return PhpCompatibility::value(null, 'array', true);
        }
        usort($entities, static function ($a, $b) {
            return PhpCompatibility::compare(($a['inferred_type'] === 'Disease' ? -1 : ($a['inferred_type'] === 'Compound' ? 0 : 1)), ($b['inferred_type'] === 'Disease' ? -1 : ($b['inferred_type'] === 'Compound' ? 0 : 1)));
        });
        $seed = array_shift($entities);
        if (!self::definitions($seed['inferred_type'], $target)) {
            return PhpCompatibility::value(null, 'array', true);
        }
        return PhpCompatibility::value(['status' => 'success', 'routing' => ['domain' => strtolower($seed['inferred_type']), 'intent' => 'constrained_path', 'endpoint' => 'semantic_query', 'entity' => $seed['name'], 'entity_type' => $seed['inferred_type'], 'result_type' => $target, 'constraints' => array_map(static function ($e) {
            return ['name' => $e['name'], 'type' => $e['inferred_type']];
        }, $entities), 'confidence' => 0.9, 'needs_clarification' => false]], 'array', true);
    }
}
