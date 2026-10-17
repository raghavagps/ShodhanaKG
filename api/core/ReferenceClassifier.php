<?php

require_once __DIR__ . '/PhpCompatibility.php';
/** Namespace classification only: never a scientific claim or a network lookup. */
final class ReferenceClassifier
{
    private static $catalog;
    private static $bindings;
    public static function catalog()
    {
        if (self::$catalog === null) {
            self::$catalog = PhpCompatibility::jsonDecode(file_get_contents(__DIR__ . '/reference_catalog_v1.json'), true, 512, 0);
            self::$bindings = [];
            foreach (self::$catalog['entries'] as $entry) {
                foreach ($entry['edge_ids'] as $id) {
                    self::$bindings[$id] = $entry;
                }
            }
        }
        return PhpCompatibility::value(self::$catalog, 'array', false);
    }
    private static function normalize($value, $namespace)
    {
        $value = PhpCompatibility::value($value, 'string', false);
        $namespace = PhpCompatibility::value($namespace, 'string', false);
        $value = trim($value);
        if ($namespace === 'PMID') {
            $value = preg_replace('~^(?:PMID:\s*|https?://pubmed\.ncbi\.nlm\.nih\.gov/)~i', '', $value);
            $value = rtrim($value, '/');
            return PhpCompatibility::value((preg_match('/^[0-9]+$/D', $value) ? $value : null), 'string', true);
        }
        if ($namespace === 'DOI') {
            $value = preg_replace('~^(?:doi:\s*|https?://(?:dx\.)?doi\.org/)~i', '', $value);
            return PhpCompatibility::value((preg_match('~^10\.[0-9]{4,9}/[^\s]+$~D', $value) ? strtolower($value) : null), 'string', true);
        }
        $value = preg_replace('/^(?:AYUSH(?:\s*ID)?):\s*/i', '', $value);
        if (preg_match('/^([0-9]+)(?:\(Ay\))?$/iD', $value, $m)) {
            return PhpCompatibility::value($m[1], 'string', true);
        }
        return PhpCompatibility::value((preg_match('/^ARP_AYU[0-9]+$/iD', $value) ? strtoupper($value) : null), 'string', true);
    }
    public static function resolve(array $edge, $edgeId = null)
    {
        $edgeId = PhpCompatibility::value($edgeId, 'string', true);
        $catalog = self::catalog();
        $id = (isset($edgeId) ? $edgeId : (isset($edge['@rid']) ? $edge['@rid'] : (isset($edge['edge_rid']) ? $edge['edge_rid'] : (isset($edge['id']) ? $edge['id'] : ''))));
        $binding = (isset(self::$bindings[$id]) ? self::$bindings[$id] : null);
        $refs = [];
        $fields = ['ref' => null, 'pubmedId' => 'PMID', 'pubmed_id' => 'PMID', 'Pubmed ID' => 'PMID', 'doi' => 'DOI', 'DOI' => 'DOI', 'ayush_id' => 'AYUSH', 'Ayush_ID' => 'AYUSH', 'ayushId' => 'AYUSH'];
        foreach ($fields as $field => $namespace) {
            if (!isset($edge[$field]) || !is_scalar($edge[$field]) || trim((string) $edge[$field]) === '') {
                continue;
            }
            $raw = (string) $edge[$field];
            $ref = ['raw' => $raw, 'identifier' => trim($raw), 'namespace' => null, 'status' => 'unclassified', 'resolution_status' => 'not_checked', 'catalog_version' => $catalog['version']];
            if ($field === 'ref' && $binding && $raw === $binding['raw']) {
                $ref = array_merge($ref, array_intersect_key($binding, array_flip(['identifier', 'namespace', 'resolution_status', 'source', 'verified_at', 'aliases'])));
                $ref['status'] = 'verified';
            } else {
                if ($namespace === null) {
                    if (preg_match('~^(?:PMID:|https?://pubmed\.ncbi\.nlm\.nih\.gov/)~i', trim($raw))) {
                        $namespace = 'PMID';
                    } elseif (preg_match('~^(?:doi:|https?://(?:dx\.)?doi\.org/)~i', trim($raw))) {
                        $namespace = 'DOI';
                    } elseif (preg_match('/^(?:AYUSH(?:\s*ID)?:|ARP_AYU[0-9]+$|[0-9]+\(Ay\)$)/i', trim($raw))) {
                        $namespace = 'AYUSH';
                    }
                }
                $normalized = ($namespace ? self::normalize($raw, $namespace) : null);
                if ($normalized !== null) {
                    $ref['namespace'] = $namespace;
                    $ref['identifier'] = $normalized;
                    $ref['status'] = 'declared';
                }
                if ($field === 'ref' && $binding && $raw !== $binding['raw']) {
                    $ref['status'] = 'conflict';
                }
            }
            $refs[] = $ref;
        }
        // Different namespaces may coexist as explicit identifiers of one paper.
        // A repeated token in different namespaces, or two values in one namespace,
        // is contradictory and must not be used to connect evidence paths.
        $conflict = false;
        foreach ($refs as $a) {
            foreach ($refs as $b) {
                if (!$a['namespace'] || !$b['namespace']) {
                    continue;
                }
                if (($a['namespace'] === $b['namespace']) !== ($a['identifier'] === $b['identifier'])) {
                    $conflict = true;
                }
            }
        }
        foreach ($refs as &$ref) {
            if ($conflict) {
                $ref['status'] = 'conflict';
            }
            if ($ref['status'] === 'conflict') {
                $ref['namespace'] = null;
            }
        }
        unset($ref);
        $unique = [];
        foreach ($refs as $ref) {
            $key = (isset($ref['namespace']) ? $ref['namespace'] : '') . ':' . $ref['identifier'];
            if (!isset($unique[$key]) || $ref['status'] === 'verified') {
                $unique[$key] = $ref;
            }
        }
        return PhpCompatibility::value(array_values($unique), 'array', false);
    }
}
