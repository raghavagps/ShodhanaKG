<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/ReferenceClassifier.php';
final class KgStatistics
{
    public static function collect(callable $query, array $vertexTypes)
    {
        $schema = call_user_func(function () use (&$query) {
            $__kgCoalesce1 = $query('SELECT name, type FROM schema:types');
            return (isset($__kgCoalesce1['result']) ? $__kgCoalesce1['result'] : []);
        });
        $edgeTypes = [];
        $vertices = [];
        foreach ($schema as $type) {
            if ($type['type'] === 'edge') {
                $edgeTypes[] = $type['name'];
            }
            if ($type['type'] === 'vertex') {
                $vertices[] = $type['name'];
            }
        }
        $nodes = 0;
        $edges = 0;
        $references = [];
        foreach ($vertexTypes as $type) {
            if (!in_array($type, $vertices, true)) {
                throw new RuntimeException('Missing configured vertex type');
            }
            $safe = str_replace('`', '``', $type);
            $nodes += (int) $query("SELECT COUNT(*) AS cnt FROM `{$safe}`")['result'][0]['cnt'];
        }
        foreach ($edgeTypes as $type) {
            $safe = str_replace('`', '``', $type);
            // Page raw records so neither count nor evidence silently hits a fixed cap.
            $offset = 0;
            do {
                $rows = call_user_func(function () use (&$query, &$safe, &$offset) {
                    $__kgCoalesce2 = $query("SELECT FROM `{$safe}` ORDER BY @rid LIMIT 1000 SKIP {$offset}");
                    return (isset($__kgCoalesce2['result']) ? $__kgCoalesce2['result'] : []);
                });
                $edges += count($rows);
                foreach ($rows as $edge) {
                    foreach (ReferenceClassifier::resolve($edge) as $ref) {
                        $key = (isset($ref['namespace']) ? $ref['namespace'] : 'unclassified') . ':' . $ref['identifier'];
                        $references[$key] = true;
                    }
                }
                $offset += count($rows);
            } while (count($rows) === 1000);
        }
        return PhpCompatibility::value(['totalNodes' => $nodes, 'totalRelations' => $edges, 'totalStudies' => count($references), 'totalReferences' => count($references), 'referenceCountBasis' => 'Distinct namespace-qualified identifiers; not independently established studies'], 'array', false);
    }
}
