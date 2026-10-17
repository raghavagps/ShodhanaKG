<?php

require_once __DIR__ . '/EntityResolution.php';
/** Legacy exact lookup stays exact; fuzzy callers share conservative candidate selection. */
class EntityResolver
{
    public static function resolve($type, $name, &$debugCollector = null)
    {
        if (!in_array($type, get_schema_vertex_types(), true)) {
            return null;
        }
        if (preg_match('/^#\d+:\d+$/D', $name)) {
            $node = EntityResolution::live()->node($name);
            if (!$node || (isset($node['@type']) ? $node['@type'] : '') !== $type) {
                return null;
            }
            $node['rid'] = $node['@rid'];
            return $node;
        }
        $fields = label_fields_for_type($type);
        $conditions = array_map(static function ($field) {
            return '`' . trim($field, '`') . '`.toLowerCase() = :name';
        }, $fields);
        $sql = 'SELECT FROM `' . $type . '` WHERE ' . implode(' OR ', $conditions) . ' LIMIT 2';
        $params = ['name' => mb_strtolower(trim($name))];
        if (is_array($debugCollector)) {
            $debugCollector['sql_queries'][] = ['sql' => $sql, 'params' => $params];
        }
        $rows = call_user_func(function () use (&$sql, &$params) {
            $__kgCoalesce1 = arcade_command($sql, 'sql', $params);
            return (isset($__kgCoalesce1['result']) ? $__kgCoalesce1['result'] : []);
        });
        if (count($rows) !== 1) {
            return null;
        }
        $node = $rows[0];
        $node['rid'] = $node['@rid'];
        return $node;
    }
    public static function fuzzyResolve($type, $name, &$debugCollector = null)
    {
        if (!in_array($type, get_schema_vertex_types(), true) || EntityResolution::residual($name) === '') {
            return null;
        }
        $index = EntityResolution::live();
        $r = $index->resolve($name, [$type]);
        if (is_array($debugCollector)) {
            $debugCollector['entity_resolution'] = $r;
        }
        return ($r['outcome'] === 'resolved' ? $index->node($r['candidates'][0]['id']) : null);
    }
}
