<?php

require_once __DIR__ . '/PhpCompatibility.php';
/** Read-only graph queries: absent schema types are excluded, query failures are not. */
final class GraphRead
{
    public static function edges(array $rids, array $types, $limit, callable $query)
    {
        $limit = PhpCompatibility::value($limit, 'int', false);
        if (!$rids || !$types) {
            return PhpCompatibility::value([], 'array', false);
        }
        $schema = call_user_func(function () use (&$query) {
            $__kgCoalesce1 = $query('SELECT name, type FROM schema:types');
            return (isset($__kgCoalesce1['result']) ? $__kgCoalesce1['result'] : []);
        });
        $available = [];
        foreach ($schema as $type) {
            if ($type['type'] === 'edge') {
                $available[] = $type['name'];
            }
        }
        $result = [];
        $ids = implode(',', $rids);
        foreach (array_intersect($types, $available) as $type) {
            $safe = str_replace('`', '``', $type);
            $rows = call_user_func(function () use (&$query, &$safe, &$ids, &$limit) {
                $__kgCoalesce2 = $query("SELECT FROM `{$safe}` WHERE @out IN [{$ids}] OR @in IN [{$ids}] LIMIT {$limit}");
                return (isset($__kgCoalesce2['result']) ? $__kgCoalesce2['result'] : []);
            });
            $result = array_merge($result, $rows);
            if ($limit > 2000 && count($rows) >= $limit) throw new RuntimeException('Graph edge read budget reached');
        }
        return PhpCompatibility::value($result, 'array', false);
    }
}
