<?php
/** Read-only, schema-driven snapshot. Optional cache must be outside document root. */
class KgReadSnapshot {
    public static function load() {
        $schema = arcade_command('SELECT name, type FROM schema:types', 'sql');
        $dir = getenv('KG_READ_CACHE_DIR');
        $file = null;
        if ($dir && is_dir($dir) && is_writable($dir)) {
            $real = realpath($dir);
            $public = realpath(!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(dirname(__DIR__)));
            $publicRoot = strtolower($public ? $public : dirname(dirname(__DIR__)));
            if ($real && strtolower($real) !== $publicRoot && strpos(strtolower($real), $publicRoot . DIRECTORY_SEPARATOR) !== 0) {
                $file = $real . DIRECTORY_SEPARATOR . 'kg-read-' . hash('sha256', __DIR__ . getenv('ARCADE_URL') . getenv('ARCADE_DB') . json_encode($schema['result']) . filemtime(__DIR__ . '/reference_catalog_v1.json')) . '.json';
            }
        }
        if ($file && is_file($file) && time() - filemtime($file) < 60) {
            $cached = json_decode(file_get_contents($file), true);
            if (is_array($cached) && isset($cached['nodes'], $cached['edges'], $cached['fetched_at'])) return $cached;
        }
        $result = array('nodes' => array(), 'edges' => array(), 'fetched_at' => gmdate('c'));
        foreach ($schema['result'] as $type) {
            if (!in_array($type['type'], array('vertex', 'edge'), true)) continue;
            if ($type['type'] === 'vertex' && !in_array($type['name'], get_schema_vertex_types(), true)) continue;
            $safe = str_replace('`', '``', $type['name']);
            for ($offset = 0; ; $offset += 1000) {
                $page = arcade_command("SELECT FROM `{$safe}` ORDER BY @rid SKIP {$offset} LIMIT 1000", 'sql');
                foreach ($page['result'] as $row) $result[$type['type'] === 'vertex' ? 'nodes' : 'edges'][$row['@rid']] = $row;
                if (count($page['result']) < 1000) break;
                if ($offset >= 100000) throw new RuntimeException('Snapshot exceeds read budget; narrow the query or configure indexed retrieval.');
            }
        }
        if ($file) {
            $tmp = tempnam($dir, 'kg-read-');
            if ($tmp !== false) { file_put_contents($tmp, json_encode($result)); @rename($tmp, $file); if (is_file($tmp)) unlink($tmp); }
        }
        return $result;
    }
}
