<?php
final class EvaluationAssertions {
    public static function validateCases($cases): void {
        if (!is_array($cases) || !$cases || array_keys($cases) !== range(0, count($cases) - 1)) throw new InvalidArgumentException('Expected a nonempty case array');
        $ids = [];
        foreach ($cases as $case) {
            if (!is_array($case)) throw new InvalidArgumentException('Invalid case');
            foreach (['id', 'question', 'expected_status'] as $key) {
                if (!isset($case[$key]) || !is_string($case[$key]) || trim($case[$key]) === '') throw new InvalidArgumentException('Missing or invalid ' . $key);
            }
            if (!in_array($case['expected_status'], ['success', 'no_data', 'unsupported', 'needs_clarification', 'entity_not_found', 'error'], true)) throw new InvalidArgumentException('Invalid expected_status');
            if (isset($ids[$case['id']])) throw new InvalidArgumentException('Duplicate case ID: ' . $case['id']);
            $ids[$case['id']] = true;
            foreach (['expected_endpoint', 'expected_entity_type', 'expected_entity', 'expected_reason', 'expected_final_status'] as $key) {
                if (isset($case[$key]) && !is_string($case[$key])) throw new InvalidArgumentException('Invalid ' . $key);
            }
            foreach (['history', 'expected_results', 'forbidden_results'] as $key) {
                if (array_key_exists($key, $case) && !is_array($case[$key])) throw new InvalidArgumentException('Invalid ' . $key);
            }
            foreach ($case['history'] ?? [] as $turn) {
                if (!is_array($turn) || !in_array($turn['role'] ?? null, ['user', 'bot'], true) || !is_string($turn['text'] ?? null)) throw new InvalidArgumentException('Invalid history turn');
            }
            foreach (['expected_results', 'forbidden_results'] as $key) foreach ($case[$key] ?? [] as $row) {
                if (!is_array($row) || !$row) throw new InvalidArgumentException('Invalid result assertion');
            }
            if (array_key_exists('expected_result_count', $case) && (!is_int($case['expected_result_count']) || $case['expected_result_count'] < 0)) throw new InvalidArgumentException('Invalid expected_result_count');
        }
    }

    public static function contains($actual, $expected): bool {
        if (!is_array($expected)) return $actual === $expected;
        if (!is_array($actual)) return false;
        // Object fields are subsets; nonempty ordered lists describe the complete path/value.
        if ($expected && array_keys($expected) === range(0, count($expected) - 1) && count($actual) !== count($expected)) return false;
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $actual) || !self::contains($actual[$key], $value)) return false;
        }
        return true;
    }

    public static function compare(array $case, array $actual, bool $routingOnly = false): array {
        $routing = (array)($actual['routing'] ?? []);
        $fields = [
            'expected_status' => $actual['status'] ?? null,
            'expected_endpoint' => $routing['endpoint'] ?? null,
            'expected_entity_type' => $routing['entity_type'] ?? null,
            'expected_entity' => $routing['entity'] ?? null,
            'expected_reason' => $actual['reason'] ?? $actual['retrieval']['reason'] ?? null,
        ];
        $failures = [];
        $nodeEnvelope = false;
        if (!$routingOnly && ($routing['endpoint'] ?? '') === 'node_property') {
            $answer = $actual['node_answer'] ?? []; $evidence = $answer['node_evidence'] ?? [];
            $nodeEnvelope = ($evidence['source'] ?? '') === 'stored_node_properties'
                && isset($routing['entity_id'], $routing['entity_type'], $routing['property'])
                && ($evidence['node_id'] ?? null) === $routing['entity_id']
                && ($evidence['node_type'] ?? null) === $routing['entity_type']
                && ($evidence['requested_property'] ?? null) === $routing['property']
                && is_array($evidence['properties'] ?? null)
                && ($answer['status'] ?? null) === ($actual['status'] ?? null)
                && (($actual['status'] === 'success') === (count($evidence['properties'] ?? []) > 0));
            if (!$nodeEnvelope) $failures[] = 'Invalid node-property evidence envelope';
        }
        if (isset($case['expected_faq_id'])) {
            if (($actual['faq']['id'] ?? null) !== $case['expected_faq_id'] || ($actual['type'] ?? null) !== 'faq_static_response') $failures[] = 'Wrong educational FAQ';
            if (!empty($actual['retrieval']['results'])) $failures[] = 'FAQ invented KG evidence';
        }
        if (!$routingOnly && !isset($case['expected_faq_id']) && !$nodeEnvelope && in_array($actual['status'] ?? null, ['success', 'no_data'], true) && !isset($actual['retrieval'])) $failures[] = 'Missing required retrieval';
        if (!$routingOnly && isset($actual['retrieval'])) {
            $retrieval = $actual['retrieval'];
            if (!is_array($retrieval) || !isset($retrieval['status'])) $failures[] = 'Invalid retrieval envelope';
            elseif (in_array($actual['status'] ?? null, ['success', 'no_data'], true) && $actual['status'] !== $retrieval['status']) $failures[] = 'Orchestration status disagrees with retrieval';
            elseif (in_array($retrieval['status'], ['success', 'no_data'], true)) {
                if (!isset($retrieval['results'], $retrieval['entity'], $retrieval['path']) || !is_array($retrieval['results']) || !is_array($retrieval['path'])) $failures[] = 'Invalid retrieval contract';
                elseif (($retrieval['status'] === 'success') !== (count($retrieval['results']) > 0)) $failures[] = 'Retrieval status disagrees with results';
            }
        }
        foreach ($fields as $key => $got) {
            if (!array_key_exists($key, $case)) continue;
            $want = $case[$key];
            if ($key === 'expected_endpoint') {
                $want = $want === null ? null : preg_replace('/\.php$/', '', $want);
                $got = $got === null ? null : preg_replace('/\.php$/', '', $got);
            }
            if ($want !== $got) $failures[] = "$key: expected " . json_encode($want) . ', got ' . json_encode($got);
        }
        if ($routingOnly) return $failures;
        if (array_key_exists('expected_result_count', $case) && count($actual['retrieval']['results'] ?? []) !== $case['expected_result_count']) $failures[] = 'Unexpected retrieval result count';
        foreach (['expected_results', 'forbidden_results'] as $key) {
            foreach ($case[$key] ?? [] as $expected) {
                $found = false;
                foreach ($actual['retrieval']['results'] ?? [] as $row) if (self::contains($row, $expected)) { $found = true; break; }
                if ($found !== ($key === 'expected_results')) $failures[] = $key . ': ' . json_encode($expected);
            }
        }
        return $failures;
    }
}
