<?php

require_once __DIR__ . '/PhpCompatibility.php';
/** Read-only graph queries: absent schema types are excluded, query failures are not. */
final class GraphRead
{
    private static $availableEdges = null;

    public static function resetCache()
    {
        self::$availableEdges = null;
    }

    public static function edges(array $rids, array $types, $limit, callable $query)
    {
        $limit = PhpCompatibility::value($limit, 'int', false);
        if (!$rids || !$types) {
            return PhpCompatibility::value([], 'array', false);
        }
        if (self::$availableEdges === null) {
            $__kgCoalesce1 = $query('SELECT name, type FROM schema:types');
            $schema = (isset($__kgCoalesce1['result']) ? $__kgCoalesce1['result'] : []);
            $available = [];
            foreach ($schema as $type) {
                if (isset($type['type']) && $type['type'] === 'edge') {
                    $available[] = $type['name'];
                }
            }
            self::$availableEdges = $available;
        }
        $available = self::$availableEdges;
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
