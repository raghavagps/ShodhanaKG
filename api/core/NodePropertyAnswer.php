<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/EntityResolution.php';
require_once __DIR__ . '/QueryUnderstanding.php';
/** Node fields only: never traverses an edge or invokes a language model. */
final class NodePropertyAnswer
{
    public static function subjectQuestion($question)
    {
        $question = PhpCompatibility::value($question, 'string', false);
        // Keep field names out of entity matching (e.g. Weight is itself a KG node).
        if (preg_match('/^(?:(?:please|and)\s+)?(?:what (?:is|are)|show(?: me)?|give(?: me)?)\s+(?:its|their)\s+.+[?.!]*$/i', trim($question))) {
            return PhpCompatibility::value('it', 'string', false);
        }
        if (QueryUnderstanding::target($question) && !preg_match('/\b(?:property|attribute|field|description|definition|information|details)\b/i', $question)) {
            return PhpCompatibility::value($question, 'string', false);
        }
        if (preg_match('/^(?:(?:please|and)\s+)?(?:what (?:is|are)|show(?: me)?|give(?: me)?|tell me)\s+(?:the\s+)?(.+?)\s+(?:of|for|about|on)\s+(.+?)[?.!]*$/i', trim($question), $m)) {
            return PhpCompatibility::value($m[2], 'string', false);
        }
        if (preg_match('/^(?:(?:please|and)\s+)?(?:describe|define|explain|tell me (?:more )?about|what do you know about)\s+(.+?)[?.!]*$/i', trim($question), $m)) {
            return PhpCompatibility::value($m[1], 'string', false);
        }
        return PhpCompatibility::value($question, 'string', false);
    }
    public static function requested($question, array $node)
    {
        $question = PhpCompatibility::value($question, 'string', false);
        $q = EntityResolution::normalize($question);
        $mention = EntityResolution::normalize((isset($node['_resolution_mention']) ? $node['_resolution_mention'] : $node['name']));
        $q = preg_replace('/(?<![\p{L}\p{N}])' . preg_quote($mention, '/') . '(?![\p{L}\p{N}])/u', 'NODE', $q);
        $type = EntityResolution::normalize($node['inferred_type']);
        $q = str_replace($type . ' NODE', 'NODE', $q);
        if ($node['inferred_type'] === 'Shodhana_Method') {
            $q = str_replace('method NODE', 'NODE', $q);
        }
        $q = preg_replace('/\b(it|its|this|that)\b/', 'NODE', $q);
        $q = preg_replace('/^(and |please |can you |could you )+/', '', $q);
        if (preg_match('/^(?:what is|define|describe|explain|tell me (?:more )?about|what do you know about|(?:show|give)(?: me)? (?:information|details) (?:about|on)) NODE$/', $q)) {
            return PhpCompatibility::value('*', 'string', true);
        }
        if (preg_match('/^(?:what (?:is|are)|show(?: me)?|give(?: me)?|tell me) (?:the )?(.+?) (?:of|for|about|on) NODE$/', $q, $m) || preg_match('/^(?:what (?:is|are)|show(?: me)?|give(?: me)?|tell me) NODE s? ?(.+)$/', $q, $m)) {
            $property = trim($m[1]);
            $explicit = preg_match('/^(?:stored |node )?(?:property|attribute|field) /', $property);
            $property = preg_replace('/^(?:stored |node )?(?:property|attribute|field) /', '', $property);
            if ($explicit) {
                return PhpCompatibility::value($property, 'string', true);
            }
            if (QueryUnderstanding::procedureRequest($question)) {
                return PhpCompatibility::value(null, 'string', true);
            }
            foreach (array_keys($node) as $field) {
                if ($field !== '' && $field[0] !== '@' && $field[0] !== '_' && EntityResolution::normalize($field) === $property) {
                    return PhpCompatibility::value($property, 'string', true);
                }
            }
            if (in_array($property, ['description', 'definition', 'information', 'details'], true)) {
                return PhpCompatibility::value(in_array($property, ['information', 'details'], true) ? '*' : 'description', 'string', true);
            }
            if (in_array($property, ['properties', 'stored properties', 'node properties', 'attributes'], true)) {
                return PhpCompatibility::value('*', 'string', true);
            }
            // Ordinary relationship questions retain their existing retrieval path.
            if (!$explicit && (QueryUnderstanding::target($question) || preg_match('/\b(?:symptoms?|therapies|therapy|treatments?|formulations?|methods?|media|medium|diseases?|indications?|compounds?|process(?:es)?|therapeutic uses?|purification|detoxification|validation|toxicity)\b/i', $property))) {
                return PhpCompatibility::value(null, 'string', true);
            }
            return PhpCompatibility::value($property, 'string', true);
        }
        if (preg_match('/^(?:description|definition|information|details) (?:of|for|about|on) NODE$/', $q)) {
            return PhpCompatibility::value(preg_match('/^(?:information|details) /', $q) ? '*' : 'description', 'string', true);
        }
        return PhpCompatibility::value(null, 'string', true);
    }
    public static function build(array $raw, $property)
    {
        $property = PhpCompatibility::value($property, 'string', false);
        $fields = [];
        foreach ($raw as $key => $value) {
            if ($key === '' || $key[0] === '@' || $key[0] === '_' || preg_match('/^(?:in|out)(?:_|$)/i', $key)) {
                continue;
            }
            if ($value === null || is_string($value) && trim($value) === '' || $value === []) {
                continue;
            }
            $normalized = EntityResolution::normalize($key);
            $description = preg_match('/(?:^|[_. ])description(?:[. ]\d+)?$/i', $key);
            if ($property === '*' || $property === 'description' && $description || $normalized === EntityResolution::normalize($property)) {
                $fields[$key] = $value;
            }
        }
        $label = vertex_label($raw);
        $answer = ($fields ? $label . " - stored node " . ($property === '*' ? 'properties' : $property) . ":\n\n" : 'No information is available for ' . $property . ' on the ' . $label . ' node. You can ask about its associated relationships.');
        foreach ($fields as $key => $value) {
            $answer .= $key . ': ' . (is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "\n";
        }
        return PhpCompatibility::value(['status' => ($fields ? 'success' : 'no_data'), 'answer' => trim($answer), 'node_evidence' => ['source' => 'stored_node_properties', 'node_id' => $raw['@rid'], 'node_type' => $raw['@type'], 'requested_property' => $property, 'properties' => $fields], 'citations' => [], 'evidence_records' => []], 'array', false);
    }
}
