<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/TraversalCatalog.php';
require_once __DIR__ . '/PathEvidence.php';
require_once __DIR__ . '/EdgePresentation.php';
require_once __DIR__ . '/EntityResolver.php';
require_once __DIR__ . '/GraphRetriever.php';
require_once __DIR__ . '/EvidenceCorrelator.php';
require_once __DIR__ . '/KnowledgeNormalizer.php';
/** Bounded, typed traversal. Dependencies are injectable for offline graph tests. */
class EvidenceTraversal
{
    private $query;
    private $resolve;
    private $nodes = [];
    private $edgeCache = [];
    public function __construct($query = null, $resolve = null)
    {
        $query = PhpCompatibility::value($query, 'callable', true);
        $resolve = PhpCompatibility::value($resolve, 'callable', true);
        $this->query = (isset($query) ? $query : static function ($sql, $params) {
            return GraphRetriever::execute($sql, $params);
        });
        $this->resolve = (isset($resolve) ? $resolve : static function ($type, $name) {
            return EntityResolver::resolve($type, $name);
        });
    }
    private function node($rid)
    {
        $rid = PhpCompatibility::value($rid, 'string', false);
        if (!preg_match('/^#\d+:\d+$/D', $rid)) {
            throw new RuntimeException('Invalid graph identifier');
        }
        if (!array_key_exists($rid, $this->nodes)) {
            $rows = call_user_func($this->query, 'SELECT FROM ' . $rid, []);
            $this->nodes[$rid] = (isset($rows[0]) ? $rows[0] : null);
        }
        return PhpCompatibility::value($this->nodes[$rid], 'array', true);
    }
    private static function dto(array $node)
    {
        return PhpCompatibility::value(['id' => (string) (isset($node['@rid']) ? $node['@rid'] : $node['rid']), 'type' => $node['@type'], 'label' => vertex_label($node)], 'array', false);
    }
    public function run($endpoint, $name, $debug = false)
    {
        $endpoint = PhpCompatibility::value($endpoint, 'string', false);
        $name = PhpCompatibility::value($name, 'string', false);
        $debug = PhpCompatibility::value($debug, 'bool', false);
        $spec = call_user_func(function () use (&$endpoint) {
            $__kgCoalesce1 = TraversalCatalog::all();
            return (isset($__kgCoalesce1[$endpoint]) ? $__kgCoalesce1[$endpoint] : null);
        });
        if (!$spec || trim($name) === '') {
            return PhpCompatibility::value(['status' => 'error', 'message' => 'Invalid traversal or missing entity'], 'array', false);
        }
        return PhpCompatibility::value($this->runDefinition($spec, $name, $debug), 'array', false);
    }
    /** Internal definitions only; never accept a definition from HTTP input. */
    public function runDefinition(array $spec, $name, $debug = false)
    {
        $name = PhpCompatibility::value($name, 'string', false);
        $debug = PhpCompatibility::value($debug, 'bool', false);
        if (trim($name) === '') {
            return PhpCompatibility::value(['status' => 'error', 'message' => 'Missing entity'], 'array', false);
        }
        $this->nodes = [];
        $this->edgeCache = [];
        try {
            $seed = null;
            foreach ($spec['input'] as $type) {
                $seed = call_user_func($this->resolve, $type, trim($name));
                if ($seed) {
                    break;
                }
            }
            if (!$seed) {
                return PhpCompatibility::value(['status' => 'entity_not_found', 'entity' => ['name' => $name, 'type' => $spec['input'][0]]], 'array', false);
            }
            $rid = (isset($seed['@rid']) ? $seed['@rid'] : $seed['rid']);
            $seed = $this->node($rid);
            if (!$seed || !in_array($seed['@type'], $spec['input'], true)) {
                throw new RuntimeException('Invalid seed type');
            }
            $entity = ['name' => vertex_label($seed), 'type' => $seed['@type']];
            $paths = [['nodes' => [self::dto($seed)], 'edges' => []]];
            $logical = [$seed['@type']];
            $uncorrelated = false;
            foreach ($spec['steps'] as $step) {
                $logical[] = ($step['direction'] === 'in' ? '<- ' : '') . implode('|', $step['edges']);
                $logical[] = implode('|', $step['types']);
                $next = [];
                foreach ($paths as $path) {
                    $current = $path['nodes'][count($path['nodes']) - 1]['id'];
                    foreach ($step['edges'] as $edgeType) {
                        $side = ($step['direction'] === 'out' ? '@out' : '@in');
                        $targetSide = ($step['direction'] === 'out' ? '@in' : '@out');
                        $cacheKey = $edgeType . '|' . $side . '|' . $current;
                        if (!isset($this->edgeCache[$cacheKey])) {
                            $this->edgeCache[$cacheKey] = call_user_func($this->query, 'SELECT FROM `' . $edgeType . '` WHERE ' . $side . ' = :rid', ['rid' => $current]);
                        }
                        $edges = $this->edgeCache[$cacheKey];
                        foreach ($edges as $edge) {
                            if ((isset($edge[$side]) ? $edge[$side] : null) !== $current || (isset($edge['@type']) ? $edge['@type'] : null) !== $edgeType) {
                                continue;
                            }
                            $target = $this->node((string) $edge[$targetSide]);
                            if (!$target || !in_array($target['@type'], $step['types'], true)) {
                                continue;
                            }
                            if (in_array((string) $edge[$targetSide], array_column($path['nodes'], 'id'), true)) {
                                continue;
                            }
                            $constraint = (isset($spec['constraints'][$target['@type']]) ? $spec['constraints'][$target['@type']] : null);
                            if ($constraint !== null && $constraint !== (string) $edge[$targetSide]) {
                                continue;
                            }
                            $copy = $path;
                            $copy['nodes'][] = self::dto($target);
                            $properties = array_filter($edge, static function ($key) {
                                return $key[0] !== '@';
                            }, ARRAY_FILTER_USE_KEY);
                            $copy['edges'][] = ['id' => $edge['@rid'], 'type' => $edgeType, 'source' => $edge['@out'], 'target' => $edge['@in'], 'properties' => $properties, 'evidence' => EvidenceCorrelator::extractIdentifiers($edge)];
                            $lastEdge = count($copy['edges']) - 1;
                            $copy['edges'][$lastEdge] += EdgePresentation::metadata($copy['edges'][$lastEdge]);
                            if (PathEvidence::groups($copy, !empty($spec['separate_treatment'])) === null) {
                                $uncorrelated = true;
                                continue;
                            }
                            $next[] = $copy;
                            if (count($next) > 50000) {
                                throw new RuntimeException('Traversal capacity exceeded; narrow the query');
                            }
                        }
                    }
                }
                $paths = $next;
            }
            $candidateCount = count($paths);
            $results = [];
            foreach ($paths as $path) {
                if (!empty($spec['separate_treatment'])) {
                    $path['evidence_policy'] = 'separate_treatment';
                    $path['evidence_groups'] = PathEvidence::groups($path, true);
                }
                if (count($path['edges']) > 1 && empty($spec['separate_treatment'])) {
                    $match = EvidenceCorrelator::matchingIdentifier($path['edges'][0]['evidence'], $path['edges'][1]['evidence']);
                    if ($match === null) {
                        continue;
                    }
                    $path['matched_evidence'] = $match;
                }
                foreach ($path['edges'] as $edge) {
                    if (in_array($edge['type'], ['TREATED_BY', 'PRE_TREATED_BY', 'POST_TREATED_BY'], true)) {
                        $path['phase'] = $edge['type'];
                    }
                }
                $last = $path['nodes'][count($path['nodes']) - 1];
                $path['related_entity'] = $last['label'];
                $path['related_type'] = $last['type'];
                // Edge IDs distinguish studies/phases even when labels are identical.
                $key = implode('|', array_column($path['edges'], 'id'));
                $results[$key] = $path;
            }
            $response = KnowledgeNormalizer::formatCanonical($entity, $logical, $results);
            if (!$results && ($candidateCount > 0 || $uncorrelated) && count($spec['steps']) > 1) {
                $response['reason'] = 'no_correlated_evidence';
            }
            if ($debug) {
                $response['debug'] = ['candidate_paths' => $candidateCount, 'final_count' => count($results)];
            }
            return PhpCompatibility::value($response, 'array', false);
        } catch (Exception $e) {
            $response = ['status' => 'error', 'message' => 'Graph traversal failed'];
            if (strpos($e->getMessage(), 'capacity exceeded') !== false) {
                $response['reason'] = 'query_limit';
                $response['message'] = $e->getMessage();
            }
            if ($debug) {
                $response['debug'] = ['error' => $e->getMessage()];
            }
            return PhpCompatibility::value(PhpCompatibility::value($response, 'array', false), 'array', false);
        } catch (Throwable $e) {
            $response = ['status' => 'error', 'message' => 'Graph traversal failed'];
            if (strpos($e->getMessage(), 'capacity exceeded') !== false) {
                $response['reason'] = 'query_limit';
                $response['message'] = $e->getMessage();
            }
            if ($debug) {
                $response['debug'] = ['error' => $e->getMessage()];
            }
            return PhpCompatibility::value(PhpCompatibility::value($response, 'array', false), 'array', false);
        }
    }
    public static function serve($endpoint)
    {
        $endpoint = PhpCompatibility::value($endpoint, 'string', false);
        require_once dirname(__DIR__) . '/cors.php';
        api_handle_cors_preflight();
        header('Content-Type: application/json');
        if ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') !== 'GET') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Use GET']);
            return;
        }
        $name = (isset($_GET['entity']) ? $_GET['entity'] : '');
        if (!is_string($name) || trim($name) === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing entity']);
            return;
        }
        echo json_encode((new self())->run($endpoint, $name), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
