<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/EntityResolution.php';
require_once __DIR__ . '/EntityResolver.php';
/** Compatibility facade: chatbot and Explorer share the same candidate index. */
final class ConversationEntities
{
    const TYPES = ['Compound', 'Disease', 'Indication', 'Shodhana_Method', 'Purified_Compound', 'Media', 'Symptom', 'Therapy', 'Formulation', 'Toxic_Substance', 'Dosha', 'Ayurvedic_Diagnosis', 'Assessment_Parameter', 'Process', 'Confirmation_Study', 'Shodhana_Type', 'Ayurvedic_Formulation'];
    private $index;
    private $selectedRid;
    public function __construct($nodes = null, $selectedRid = null)
    {
        $nodes = PhpCompatibility::value($nodes, 'array', true);
        $selectedRid = PhpCompatibility::value($selectedRid, 'string', true);
        $this->index = ($nodes === null ? EntityResolution::live() : new EntityResolution($nodes));
        $this->selectedRid = $selectedRid;
    }
    public static function normalize($text)
    {
        $text = PhpCompatibility::value($text, 'string', false);
        return PhpCompatibility::value(EntityResolution::normalize($text), 'string', false);
    }
    public static function residual($text)
    {
        $text = PhpCompatibility::value($text, 'string', false);
        return PhpCompatibility::value(EntityResolution::residual($text), 'string', false);
    }
    public function find($question)
    {
        $question = PhpCompatibility::value($question, 'string', false);
        $found = $this->index->mentions(preg_replace('/\bdosha imbalance\b/i', 'dosha relation', $question));
        if ($this->selectedRid) {
            foreach ($found['groups'] as $i => $group) {
                if (isset($group[$this->selectedRid])) {
                    $found['groups'][$i] = [$this->selectedRid => $group[$this->selectedRid]];
                    $found['resolutions'][$i]['outcome'] = 'resolved';
                    $found['resolutions'][$i]['candidates'] = array_values(array_filter($found['resolutions'][$i]['candidates'], function ($c) {
                        return $c['id'] === $this->selectedRid;
                    }));
                    foreach ($found['resolutions'][$i]['candidates'] as &$candidate) {
                        $candidate['match_method'] = 'selected';
                    }
                    unset($candidate);
                }
            }
        }
        return PhpCompatibility::value($found, 'array', false);
    }
    public static function select(array $group, $question)
    {
        $question = PhpCompatibility::value($question, 'string', false);
        $declared = [];
        foreach ($group as $node) {
            $mention = (isset($node['_resolution_mention']) ? $node['_resolution_mention'] : $node['name']);
            foreach (self::TYPES as $type) {
                $word = strtolower(str_replace('_', ' ', $type));
                if ($type === 'Shodhana_Method') {
                    $word = '(?:shodhana )?method';
                }
                if (preg_match('/\b' . $word . '\s+(?:' . preg_quote($node['name'], '/') . '|' . preg_quote($mention, '/') . ')\b/i', $question)) {
                    $declared[$type] = true;
                }
            }
        }
        if ($declared) {
            $typed = array_values(array_filter($group, static function ($node) use ($declared) {
                return isset($declared[$node['inferred_type']]);
            }));
            return PhpCompatibility::value((count($typed) === 1 ? $typed[0] : []), 'array', false);
        }
        if (count($group) === 1) {
            return PhpCompatibility::value(reset($group), 'array', false);
        }
        $qualified = [];
        foreach ($group as $node) {
            $qualifier = strtolower(str_replace('_', ' ', $node['inferred_type']));
            if ($qualifier === 'shodhana method') {
                $qualifier = '(?:shodhana )?method';
            }
            $mention = (isset($node['_resolution_mention']) ? $node['_resolution_mention'] : $node['name']);
            if (preg_match('/\b' . $qualifier . '\s+(?:' . preg_quote($node['name'], '/') . '|' . preg_quote($mention, '/') . ')\b/i', $question)) {
                $qualified[] = $node;
            }
        }
        return PhpCompatibility::value((count($qualified) === 1 ? $qualified[0] : []), 'array', false);
    }
}
