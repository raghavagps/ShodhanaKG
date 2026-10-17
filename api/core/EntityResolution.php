<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once dirname(__DIR__) . '/graph_service.php';
/** Candidate discovery only. Never invents graph nodes or changes their types. */
final class EntityResolution
{
    private static $live;
    private $nodes = [];
    private $rawNodes = [];
    private $labels = [];
    private $broader = [];
    public static function live()
    {
        if (!self::$live) {
            $nodes = [];
            foreach (get_schema_vertex_types() as $type) {
                $rows = call_user_func(function () use (&$type) {
                    $__kgCoalesce1 = arcade_command('SELECT FROM `' . $type . '`');
                    return (isset($__kgCoalesce1['result']) ? $__kgCoalesce1['result'] : []);
                });
                $nodes = array_merge($nodes, $rows);
            }
            self::$live = new self($nodes);
        }
        return PhpCompatibility::value(self::$live, __CLASS__, false);
    }
    public static function normalize($value)
    {
        $value = PhpCompatibility::value($value, 'string', false);
        return PhpCompatibility::value(trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value, 'UTF-8'))), 'string', false);
    }
    public function __construct(array $nodes, $catalog = null)
    {
        $catalog = PhpCompatibility::value($catalog, 'array', true);
        foreach ($nodes as $node) {
            $rid = (isset($node['@rid']) ? $node['@rid'] : (isset($node['rid']) ? $node['rid'] : null));
            if (!$rid || !in_array((isset($node['@type']) ? $node['@type'] : ''), get_schema_vertex_types(), true)) {
                continue;
            }
            $this->rawNodes[$rid] = $node;
            $node['rid'] = $rid;
            $node['name'] = vertex_label($node);
            $node['inferred_type'] = $node['@type'];
            $this->nodes[$rid] = $node;
            if (is_string($node['name']) && trim($node['name']) !== '') {
                $this->add($node['name'], $rid, 'exact', 'KG canonical label');
            }
        }
        $path = __DIR__ . '/terminology_catalog_v2.json';
        $catalog = (isset($catalog) ? $catalog : (is_file($path) ? json_decode(file_get_contents($path), true) : []));
        foreach ((isset($catalog['mappings']) ? $catalog['mappings'] : []) as $mapping) {
            $rid = (isset($mapping['rid']) ? $mapping['rid'] : '');
            $node = (isset($this->nodes[$rid]) ? $this->nodes[$rid] : null);
            if (!$node || (isset($mapping['review_status']) ? $mapping['review_status'] : '') !== 'source_checked' || $node['@type'] !== (isset($mapping['type']) ? $mapping['type'] : '') || $node['name'] !== (isset($mapping['label']) ? $mapping['label'] : '')) {
                continue;
            }
            // KG-listed alternate names remain bound to the exact stored source fields.
            foreach ((isset($mapping['property_bindings']) ? $mapping['property_bindings'] : []) as $field => $value) {
                if ((isset($this->rawNodes[$rid][$field]) ? $this->rawNodes[$rid][$field] : null) !== $value) {
                    continue 2;
                }
            }
            foreach ((isset($mapping['aliases']) ? $mapping['aliases'] : []) as $alias) {
                if (is_array($alias) && !in_array((isset($alias['policy']) ? $alias['policy'] : ''), ['resolve', 'confirm'], true)) {
                    continue;
                }
                $term = (is_array($alias) ? (isset($alias['term']) ? $alias['term'] : '') : $alias);
                $method = (is_array($alias) && (isset($alias['policy']) ? $alias['policy'] : '') === 'confirm' ? 'terminology_suggestion' : 'alias');
                if (is_string($term) && trim($term) !== '') {
                    $this->add($term, $rid, $method, $mapping['source']);
                }
            }
            foreach ((isset($mapping['broader_terms']) ? $mapping['broader_terms'] : []) as $term) {
                $text = (is_array($term) ? (isset($term['term']) ? $term['term'] : '') : $term);
                if (!is_string($text) || trim($text) === '') {
                    continue;
                }
                $candidate = $this->candidate($rid, $text, 'broader', $mapping['source']);
                if (is_array($term)) {
                    $candidate['terminology_path'] = (isset($term['path']) ? $term['path'] : []);
                }
                $this->broader[self::normalize($text)][$rid] = $candidate;
            }
        }
    }
    private function add($label, $rid, $method, $source)
    {
        $label = PhpCompatibility::value($label, 'string', false);
        $rid = PhpCompatibility::value($rid, 'string', false);
        $method = PhpCompatibility::value($method, 'string', false);
        $source = PhpCompatibility::value($source, 'string', false);
        $key = self::normalize($label);
        if ($key !== '' && !isset($this->labels[$key][$rid])) {
            $this->labels[$key][$rid] = [$method, $source];
        }
    }
    private function candidate($rid, $mention, $method, $source, $distance = 0)
    {
        $rid = PhpCompatibility::value($rid, 'string', false);
        $mention = PhpCompatibility::value($mention, 'string', false);
        $method = PhpCompatibility::value($method, 'string', false);
        $source = PhpCompatibility::value($source, 'string', false);
        $distance = PhpCompatibility::value($distance, 'int', false);
        $n = $this->nodes[$rid];
        return PhpCompatibility::value(['id' => $rid, 'label' => $n['name'], 'type' => $n['@type'], 'mention' => $mention, 'match_method' => $method, 'source' => $source, 'distance' => $distance], 'array', false);
    }
    public function rawNode($rid)
    {
        $rid = PhpCompatibility::value($rid, 'string', false);
        return PhpCompatibility::value((isset($this->rawNodes[$rid]) ? $this->rawNodes[$rid] : null), 'array', true);
    }
    public function node($rid)
    {
        $rid = PhpCompatibility::value($rid, 'string', false);
        return PhpCompatibility::value((isset($this->nodes[$rid]) ? $this->nodes[$rid] : null), 'array', true);
    }
    public function broader($mention)
    {
        $mention = PhpCompatibility::value($mention, 'string', false);
        return PhpCompatibility::value(array_values(call_user_func(function () use (&$mention) {
            $__kgCoalesce2 = $this->broader;
            return (isset($__kgCoalesce2[self::normalize($mention)]) ? $__kgCoalesce2[self::normalize($mention)] : []);
        })), 'array', false);
    }
    public function search($query, array $types, $limit)
    {
        $query = PhpCompatibility::value($query, 'string', false);
        $limit = PhpCompatibility::value($limit, 'int', false);
        $r = $this->resolve($query, $types);
        $found = [];
        foreach (array_merge($r['candidates'], $r['suggestions']) as $c) {
            if (in_array($c['type'], $types, true)) {
                $found[$c['id']] = $c;
            }
        }
        $key = self::normalize($query);
        foreach ($this->labels as $label => $nodes) {
            if ($key !== '' && strpos($label, $key) !== false) {
                foreach ($nodes as $rid => $meta) {
                    if (in_array($this->nodes[$rid]['@type'], $types, true) && !isset($found[$rid])) {
                        $found[$rid] = $this->candidate($rid, $query, 'suggestion', $meta[1]);
                    }
                }
            }
        }
        return PhpCompatibility::value(array_slice(array_values($found), 0, max(1, min(100, $limit))), 'array', false);
    }
    /** Unicode optimal-string-alignment distance, including adjacent transpositions. */
    public static function distance($a, $b)
    {
        $a = PhpCompatibility::value($a, 'string', false);
        $b = PhpCompatibility::value($b, 'string', false);
        $a = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);
        $b = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY);
        $d = [range(0, count($b))];
        for ($i = 1; $i <= count($a); $i++) {
            $d[$i] = [$i];
            for ($j = 1; $j <= count($b); $j++) {
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + ($a[$i - 1] === $b[$j - 1] ? 0 : 1));
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }
        return PhpCompatibility::value($d[count($a)][count($b)], 'int', false);
    }
    public function resolve($mention, array $types = [], $fuzzy = true)
    {
        $mention = PhpCompatibility::value($mention, 'string', false);
        $fuzzy = PhpCompatibility::value($fuzzy, 'bool', false);
        $key = self::normalize($mention);
        $found = [];
        $broader = array_values(array_filter($this->broader($mention), static function ($c) use ($types) {
            return !$types || in_array($c['type'], $types, true);
        }));
        $allowed = function ($rid) use ($types) {
            return !$types || in_array($this->nodes[$rid]['@type'], $types, true);
        };
        if (isset($this->nodes[$mention]) && $allowed($mention)) {
            $found[$mention] = $this->candidate($mention, $mention, 'rid', 'KG RID');
        }
        foreach ((isset($this->labels[$key]) ? $this->labels[$key] : []) as $rid => $meta) {
            if ($allowed($rid)) {
                $found[$rid] = $this->candidate($rid, $mention, $meta[0], $meta[1]);
            }
        }
        // Canonical KG labels win over external aliases, never the reverse.
        $exact = array_filter($found, static function ($c) {
            return in_array($c['match_method'], ['exact', 'rid'], true);
        });
        if ($exact) {
            $found = $exact;
        }
        if (!$found && !$broader && $fuzzy && mb_strlen($key) >= 4 && self::residual($key) !== '') {
            $max = (mb_strlen($key) < 8 ? 1 : 2);
            foreach ($this->labels as $label => $nodes) {
                if (abs(mb_strlen($label) - mb_strlen($key)) > $max) {
                    continue;
                }
                $distance = self::distance($key, $label);
                if ($distance > $max || $distance / max(mb_strlen($key), mb_strlen($label)) > 0.2) {
                    continue;
                }
                foreach ($nodes as $rid => $meta) {
                    if ($allowed($rid) && (!isset($found[$rid]) || $distance < $found[$rid]['distance'])) {
                        $found[$rid] = $this->candidate($rid, $mention, ($meta[0] === 'terminology_suggestion' ? 'terminology_suggestion' : 'spelling'), $meta[1], $distance);
                    }
                }
            }
        }
        $suggestOnly = false;
        if (!$found && !$broader && $fuzzy && mb_strlen($key) >= 4 && self::residual($key) !== '') {
            foreach ($this->labels as $label => $nodes) {
                if (abs(mb_strlen($key) - mb_strlen($label)) > 3) {
                    continue;
                }
                $distance = self::distance($key, $label);
                if ($distance > 3 || $distance / max(mb_strlen($key), mb_strlen($label)) > 0.3) {
                    continue;
                }
                foreach ($nodes as $rid => $meta) {
                    if ($allowed($rid) && (!isset($found[$rid]) || $distance < $found[$rid]['distance'])) {
                        $found[$rid] = $this->candidate($rid, $mention, 'suggestion', $meta[1], $distance);
                    }
                }
            }
            $suggestOnly = (bool) $found;
        }
        usort($found, static function ($a, $b) {
            return PhpCompatibility::compare([$a['distance'], $a['label'], $a['type'], $a['id']], [$b['distance'], $b['label'], $b['type'], $b['id']]);
        });
        $best = ($found ? array_values(array_filter($found, static function ($c) use ($found) {
            return $c['distance'] === $found[0]['distance'];
        })) : []);
        foreach ($best as $candidate) {
            if ($candidate['match_method'] === 'terminology_suggestion') {
                $suggestOnly = true;
            }
        }
        return PhpCompatibility::value(['mention' => $mention, 'normalized' => $key, 'outcome' => (count($best) === 1 && !$suggestOnly ? 'resolved' : ($best ? 'ambiguous' : ($broader ? 'broader' : 'unmatched'))), 'candidates' => $best, 'suggestions' => $broader], 'array', false);
    }
    public static function residual($text)
    {
        $text = PhpCompatibility::value($text, 'string', false);
        $stop = 'what which how is are was were do does did can could would should the a an and or of for about in on with to by from as at please tell show list me i we you it its this that they them these those now switch mean okay describe description definition information details properties property explain all have has during used use uses using associated linked related occur occurs appear appears happen happens seen observed recorded records graph available symptoms symptom signs sign therapies therapy treatments treatment treated treat formulations formulation media medium substances substance purification purify purified compounds compound disease diseases methods method process processes processed toxic toxicity validate validation studies study follow follows following former latter give exist contain contains ingredients undergo undergoes detoxified detoxification shodhana';
        foreach (get_schema_vertex_types() as $type) {
            $stop .= ' ' . self::normalize($type);
        }
        return PhpCompatibility::value(implode(' ', array_values(array_diff(explode(' ', self::normalize($text)), explode(' ', $stop)))), 'string', false);
    }
    private function qualifiedTypes($mention, $question)
    {
        $mention = PhpCompatibility::value($mention, 'string', false);
        $question = PhpCompatibility::value($question, 'string', false);
        $types = [];
        foreach (get_schema_vertex_types() as $type) {
            $word = preg_quote(self::normalize($type), '/');
            if ($type === 'Shodhana_Method') {
                $word = '(?:shodhana )?method';
            }
            if (preg_match('/\b' . $word . '\s+' . preg_quote($mention, '/') . '(?![\p{L}\p{N}])/u', $question)) {
                $types[] = $type;
            }
        }
        return PhpCompatibility::value($types, 'array', false);
    }
    public function mentions($question)
    {
        $question = PhpCompatibility::value($question, 'string', false);
        $q = self::normalize($question);
        $hits = [];
        foreach ($this->labels as $label => $nodes) {
            if (mb_strlen($label) < 3 || self::residual($label) === '') {
                continue;
            }
            if (preg_match_all('/(?<![\p{L}\p{N}])' . preg_quote($label, '/') . '(?![\p{L}\p{N}])/u', $q, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $m) {
                    $hits[] = ['start' => $m[1], 'length' => strlen($m[0]), 'resolution' => $this->resolve($m[0], $this->qualifiedTypes($m[0], $q), false)];
                }
            }
        }
        // Match known broader phrases as complete spans, before a shorter contained node name.
        foreach ($this->broader as $label => $_) {
            if (mb_strlen($label) < 4 || self::residual($label) === '') {
                continue;
            }
            if (preg_match_all('/(?<![\p{L}\p{N}])' . preg_quote($label, '/') . '(?![\p{L}\p{N}])/u', $q, $matches, PREG_OFFSET_CAPTURE)) {
                $resolution = $this->resolve($label, $this->qualifiedTypes($label, $q), false);
                if ($resolution['candidates']) {
                    continue;
                }
                foreach ($matches[0] as $m) {
                    $hits[] = ['start' => $m[1], 'length' => strlen($m[0]), 'resolution' => $resolution];
                }
            }
        }
        usort($hits, static function ($a, $b) {
            return PhpCompatibility::compare($b['length'], $a['length']);
        });
        $selected = [];
        $mask = $q;
        foreach ($hits as $hit) {
            foreach ($selected as $prior) {
                if ($hit['start'] < $prior['start'] + $prior['length'] && $prior['start'] < $hit['start'] + $hit['length']) {
                    continue 2;
                }
            }
            $selected[] = $hit;
            $mask = substr_replace($mask, str_repeat(' ', $hit['length']), $hit['start'], $hit['length']);
        }
        $residual = self::residual($mask);
        $unresolvedBroadModifier = $residual !== '' && (bool) array_filter($selected, static function ($hit) {
            return $hit['resolution']['outcome'] === 'broader';
        });
        if ($residual !== '' && !$unresolvedBroadModifier) {
            // Whole residual first; then individual spans for multi-entity typos.
            $r = $this->resolve($residual, $this->qualifiedTypes($residual, $q));
            if ($r['candidates']) {
                $selected[] = ['start' => strlen($q), 'length' => strlen($residual), 'resolution' => $r];
            } else {
                foreach (preg_split('/\b(?:and|in|for|with|of|about|to|by)\b/', $mask) as $part) {
                    $span = self::residual($part);
                    if ($span === '' || $span === $residual) {
                        continue;
                    }
                    $spanResult = $this->resolve($span, $this->qualifiedTypes($span, $q));
                    if ($spanResult['candidates']) {
                        $selected[] = ['start' => strlen($q), 'length' => strlen($span), 'resolution' => $spanResult];
                    }
                }
            }
            if (!$selected) {
                // Botanical author suffix omission is a suggestion, never arbitrary substring selection.
                foreach ($this->labels as $label => $nodes) {
                    if (preg_match('/^' . preg_quote($residual, '/') . ' (linn|l|roxb)( |$)/', $label)) {
                        $r = $this->resolve($label, $this->qualifiedTypes($label, $q), false);
                        $r['mention'] = $residual;
                        $selected[] = ['start' => 0, 'length' => strlen($residual), 'resolution' => $r];
                    }
                }
            }
        }
        usort($selected, static function ($a, $b) {
            return PhpCompatibility::compare($a['start'], $b['start']);
        });
        $groups = [];
        $resolutions = [];
        $broaderSuggestions = [];
        foreach ($selected as $hit) {
            $group = [];
            $r = $hit['resolution'];
            if ($r['outcome'] === 'broader') {
                foreach ($r['suggestions'] as $c) {
                    $broaderSuggestions[$c['id']] = $c;
                }
            }
            foreach ($r['candidates'] as $c) {
                $group[$c['id']] = $this->nodes[$c['id']] + ['_resolution_mention' => $c['mention']];
            }
            if ($group) {
                $groups[] = $group;
                $resolutions[] = $r;
            }
        }
        // Unknown modifiers are not permission to broaden an unrecognized entity name.
        if (!$groups && $residual !== '') {
            $broaderSuggestions = [];
        }
        return PhpCompatibility::value(['groups' => $groups, 'unresolved' => !$groups && $residual !== '', 'resolutions' => $resolutions, 'suggestions' => array_values(($broaderSuggestions ?  : $this->broader($residual)))], 'array', false);
    }
}
