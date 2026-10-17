<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/EvidenceCorrelator.php';
require_once __DIR__ . '/LegacyEvidence.php';
/** Formats retrieved records, never question-specific answers or model prose. */
final class EvidenceAnswerBuilder
{
    const DETAIL_LIMIT = 12;
    private static function text($value)
    {
        if (is_array($value)) {
            return PhpCompatibility::value(PhpCompatibility::jsonEncode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | 0), 'string', false);
        }
        if (is_bool($value)) {
            return PhpCompatibility::value(($value ? 'true' : 'false'), 'string', false);
        }
        return PhpCompatibility::value(preg_replace('/[\r\n\t]+/u', ' ', (string) $value), 'string', false);
    }
    private static function fields(array $fields)
    {
        $parts = [];
        foreach ($fields as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $parts[] = str_replace('_', ' ', (string) $key) . ': ' . self::text($value);
        }
        return PhpCompatibility::value(implode('; ', $parts), 'string', false);
    }
    private static function path(array $row)
    {
        $nodes = (isset($row['nodes']) ? $row['nodes'] : []);
        $edges = (isset($row['edges']) ? $row['edges'] : []);
        if (!is_array($nodes) || !is_array($edges) || count($nodes) < 2 || count($edges) !== count($nodes) - 1) {
            throw new RuntimeException('Invalid evidence path');
        }
        $byId = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                throw new RuntimeException('Invalid evidence node');
            }
            foreach (['id', 'label', 'type'] as $field) {
                if (!is_string((isset($node[$field]) ? $node[$field] : null)) || trim($node[$field]) === '') {
                    throw new RuntimeException('Invalid evidence node');
                }
            }
            if (isset($byId[$node['id']]) && ($byId[$node['id']]['label'] !== $node['label'] || $byId[$node['id']]['type'] !== $node['type'])) {
                throw new RuntimeException('Conflicting node identity');
            }
            $byId[$node['id']] = $node;
        }
        $parts = [];
        $references = [];
        foreach ($edges as $i => $edge) {
            if (!is_array($edge) || !isset($edge['source'], $edge['target'], $edge['type']) || !is_array((isset($edge['evidence']) ? $edge['evidence'] : null)) || !is_array((isset($edge['properties']) ? $edge['properties'] : null))) {
                throw new RuntimeException('Invalid evidence edge');
            }
            $a = $nodes[$i]['id'];
            $b = $nodes[$i + 1]['id'];
            if (!($edge['source'] === $a && $edge['target'] === $b || $edge['source'] === $b && $edge['target'] === $a)) {
                throw new RuntimeException('Disconnected evidence');
            }
            $label = strtolower(str_replace('_', ' ', $edge['type']));
            $relation = self::text($byId[$edge['source']]['label']) . ' — ' . $label . ' → ' . self::text($byId[$edge['target']]['label']);
            $ids = array_intersect_key($edge['evidence'], array_flip(['pubmed_id', 'doi', 'ayush_id', 'unclassified_reference']));
            $rawIds = EvidenceCorrelator::extractIdentifiers($edge['properties'], $edge['id']);
            foreach (['pubmed_id', 'doi', 'ayush_id', 'unclassified_reference'] as $key) {
                if ((isset($ids[$key]) ? $ids[$key] : '') !== (isset($rawIds[$key]) ? $rawIds[$key] : '')) {
                    throw new RuntimeException('Reference disagrees with stored properties');
                }
            }
            $reference = self::fields($ids);
            $parts[] = $relation . "\n    Stored reference: " . ($reference ?  : 'not supplied') . (($props = self::fields($edge['properties'])) !== '' ? "\n    Recorded properties: " . $props : '');
            $references[] = $edge['evidence'];
        }
        // Validate the entire attributed path before any text can be streamed.
        if (isset($row['evidence_policy'])) {
            LegacyEvidence::validate($row);
        }
        if (count($edges) > 1 && !isset($row['evidence_policy'])) {
            $shared = EvidenceCorrelator::matchingIdentifier($references[0], $references[1]);
            if ($shared === null || (isset($row['matched_evidence']) ? $row['matched_evidence'] : null) !== $shared) {
                throw new RuntimeException('Uncorrelated evidence path');
            }
            foreach ($references as $reference) {
                if ((isset($reference[$shared['type']]) ? $reference[$shared['type']] : '') !== $shared['value']) {
                    throw new RuntimeException('Uncorrelated evidence path');
                }
            }
        }
        $last = $nodes[count($nodes) - 1];
        if (isset($row['related_entity']) && $row['related_entity'] !== $last['label']) {
            throw new RuntimeException('Conflicting related entity');
        }
        if (isset($row['related_type']) && $row['related_type'] !== $last['type']) {
            throw new RuntimeException('Conflicting related type');
        }
        return PhpCompatibility::value([self::text($last['label']) . ' (' . self::text($last['type']) . ')', implode("\n  ", $parts)], 'array', false);
    }
    public static function build(array $retrieval)
    {
        try {
            if ((isset($retrieval['status']) ? $retrieval['status'] : null) !== 'success' || !is_string((isset($retrieval['entity']['name']) ? $retrieval['entity']['name'] : null)) || !is_array((isset($retrieval['results']) ? $retrieval['results'] : null)) || !$retrieval['results']) {
                throw new RuntimeException('Invalid retrieval');
            }
            LegacyEvidence::validateResponse($retrieval);
            $summaries = [];
            $details = [];
            foreach ($retrieval['results'] as $row) {
                if (!is_array($row) || !$row) {
                    throw new RuntimeException('Invalid result');
                }
                if (array_key_exists('nodes', $row) || array_key_exists('edges', $row)) {
                    if ((isset($row['nodes'][0]['label']) ? $row['nodes'][0]['label'] : null) !== $retrieval['entity']['name']) {
                        throw new RuntimeException('Conflicting seed entity');
                    }
                    if (isset($retrieval['entity']['type']) && (isset($row['nodes'][0]['type']) ? $row['nodes'][0]['type'] : null) !== $retrieval['entity']['type']) {
                        throw new RuntimeException('Conflicting seed type');
                    }
                    list($summary, $detail) = self::path($row);
                } else {
                    // Legacy records have varying schemas. Keep their field names
                    // and nested property scopes; do not infer missing edge provenance.
                    $identity = array_filter($row, static function ($value, $key) {
                        return is_scalar($value) && !in_array($key, ['properties', 'validation_properties', 'safety_evidence'], true);
                    }, ARRAY_FILTER_USE_BOTH);
                    $summary = self::fields($identity);
                    if ($summary === '') {
                        throw new RuntimeException('No result identity');
                    }
                    $detail = self::fields($row);
                }
                $summaries[$summary][] = count($details) + 1;
                $details[] = $detail;
            }
            $total = count($details);
            $shown = min($total, self::DETAIL_LIMIT);
            $answer = 'Graph results for ' . self::text($retrieval['entity']['name']) . ': ' . count($summaries) . " distinct results across {$total} evidence records.\n\n";
            foreach ($summaries as $summary => $records) {
                $answer .= '- ' . $summary . ' — evidence records: ' . implode(', ', $records) . "\n";
            }
            $answer .= "\nEvidence details — showing {$shown} of {$total} records. Each reference applies only to its own record.\n";
            foreach (array_slice($details, 0, $shown) as $i => $detail) {
                $answer .= "\n" . ($i + 1) . '. ' . $detail . "\n";
            }
            if ($shown < $total) {
                $answer .= "\nAll {$total} records are available under Evidence / Retrieved Knowledge.\n";
            }
            return PhpCompatibility::value(['status' => 'success', 'answer' => trim($answer)], 'array', false);
        } catch (Exception $e) {
            return PhpCompatibility::value(PhpCompatibility::value(['status' => 'error', 'answer' => 'The retrieved evidence could not be formatted consistently. Please try again.'], 'array', false), 'array', false);
        } catch (Throwable $e) {
            return PhpCompatibility::value(PhpCompatibility::value(['status' => 'error', 'answer' => 'The retrieved evidence could not be formatted consistently. Please try again.'], 'array', false), 'array', false);
        }
    }
}
