<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/ConversationEntities.php';
require_once __DIR__ . '/NodePropertyAnswer.php';
/** Conversational hints only. Every selected subject is resolved against the KG again. */
final class ConversationContext
{
    public static function validate($state)
    {
        if (!is_array($state) || !in_array((isset($state['version']) ? $state['version'] : null), [1, 2], true)) {
            return PhpCompatibility::value(['version' => 2, '_provided' => true, '_invalid' => true], 'array', false);
        }
        $clean = ['version' => 2, '_provided' => true];
        $subject = (isset($state['subject']) ? $state['subject'] : []);
        if (is_array($subject) && in_array((isset($subject['type']) ? $subject['type'] : ''), ConversationEntities::TYPES, true) && is_string((isset($subject['name']) ? $subject['name'] : null)) && is_string((isset($subject['id']) ? $subject['id'] : null))) {
            $node = EntityResolver::resolve($subject['type'], (isset($subject['id']) ? $subject['id'] : ''));
            if ($node && $node['@type'] === $subject['type'] && vertex_label($node) === $subject['name']) {
                $clean['subject'] = ['id' => $node['rid'], 'name' => vertex_label($node), 'type' => $node['@type']];
            }
        }
        if ($subject && empty($clean['subject'])) {
            $clean['_invalid'] = true;
        }
        foreach (['last_question', 'pending_question'] as $key) {
            if (isset($state[$key]) && is_string($state[$key])) {
                $clean[$key] = mb_substr($state[$key], 0, 500);
            }
        }
        $clean['constraints'] = [];
        foreach (array_slice((is_array((isset($state['constraints']) ? $state['constraints'] : null)) ? $state['constraints'] : []), 0, 3) as $constraint) {
            if (!is_array($constraint)) {
                continue;
            }
            if (in_array((isset($constraint['type']) ? $constraint['type'] : ''), ConversationEntities::TYPES, true) && is_string((isset($constraint['name']) ? $constraint['name'] : null))) {
                $node = EntityResolver::resolve($constraint['type'], (isset($constraint['id']) ? $constraint['id'] : $constraint['name']));
                if ($node && $node['@type'] === $constraint['type'] && vertex_label($node) === $constraint['name']) {
                    $clean['constraints'][] = ['id' => $node['rid'], 'name' => vertex_label($node), 'type' => $node['@type']];
                } else {
                    $clean['_invalid'] = true;
                }
            }
        }
        if (!is_array((isset($state['constraints']) ? $state['constraints'] : [])) || count($clean['constraints']) !== count((isset($state['constraints']) ? $state['constraints'] : []))) {
            $clean['_invalid'] = true;
        }
        if (in_array((isset($state['result_type']) ? $state['result_type'] : ''), ConversationEntities::TYPES, true)) {
            $clean['result_type'] = $state['result_type'];
        }
        if (isset($state['requested_result_types'])) {
            if ($state['requested_result_types'] === ['Shodhana_Method', 'Media']) {
                $clean['requested_result_types'] = $state['requested_result_types'];
            } else {
                $clean['_invalid'] = true;
            }
        }
        foreach (['intent', 'property'] as $key) {
            if (is_string((isset($state[$key]) ? $state[$key] : null))) {
                $clean[$key] = mb_substr($state[$key], 0, 100);
            }
        }
        if (is_array((isset($state['pending_clarification']) ? $state['pending_clarification'] : null))) {
            $pending = $state['pending_clarification'];
            if (in_array((isset($pending['result_type']) ? $pending['result_type'] : ''), ConversationEntities::TYPES, true)) {
                $clean['pending_clarification']['result_type'] = $pending['result_type'];
            }
            if (is_string((isset($pending['property']) ? $pending['property'] : null))) {
                $clean['pending_clarification']['property'] = mb_substr($pending['property'], 0, 100);
            }
        }
        return PhpCompatibility::value($clean, 'array', false);
    }
    public static function prepare($question, array $history, $state)
    {
        $question = PhpCompatibility::value($question, 'string', false);
        $state = self::validate($state);
        $matcher = new ConversationEntities();
        $found = $matcher->find(NodePropertyAnswer::subjectQuestion($question));
        $followup = preg_match('/^(and\b|what about\b)|\b(it|its|this|that|they|them|these|those)\b/i', $question);
        // Context is passed as typed routing input, never as synthetic chat messages.
        $history = [];
        $clearPattern = '/\b(?:without|ignore|clear|remove)\s+(?:the\s+)?(?:previous\s+)?(?:constraints?|filters?)\b/i';
        if (preg_match($clearPattern, $question)) {
            $state['constraints'] = [];
            $question = trim(preg_replace($clearPattern, '', $question));
            if (trim($question, " \t\n\r?.!") === '') {
                $target = (isset($state['pending_clarification']['result_type']) ? $state['pending_clarification']['result_type'] : (isset($state['result_type']) ? $state['result_type'] : null));
                $question = ($target ? 'What about its ' . str_replace('_', ' ', $target) . '?' : 'What about it?');
            }
            unset($state['pending_question'], $state['pending_clarification']);
        }
        if (isset($state['pending_question']) && $found['groups'] && !SemanticQuery::target($question)) {
            // Resolve an entity-only clarification against the previous requested intent.
            $pendingTarget = SemanticQuery::target($state['pending_question']);
            if ($pendingTarget && count($found['groups']) === 1) {
                $selected = ConversationEntities::select($found['groups'][0], $question);
                if ($selected) {
                    $question = 'Which ' . str_replace('_', ' ', $pendingTarget) . ' is associated with ' . str_replace('_', ' ', $selected['inferred_type']) . ' ' . $selected['name'] . '?';
                }
            }
        }
        return PhpCompatibility::value([$question, array_slice($history, -20), $state, $found], 'array', false);
    }
    public static function finish(array $response, $question, array $previous)
    {
        $question = PhpCompatibility::value($question, 'string', false);
        $state = ['version' => 2];
        $r = (is_array((isset($response['routing']) ? $response['routing'] : null)) ? $response['routing'] : []);
        if (is_array($r) && isset($r['entity_type'], $r['entity']) && in_array($r['entity_type'], ConversationEntities::TYPES, true)) {
            $node = EntityResolver::resolve($r['entity_type'], (isset($r['entity_id']) ? $r['entity_id'] : $r['entity']));
            if ($node) {
                $state['subject'] = ['id' => $node['rid'], 'name' => vertex_label($node), 'type' => $node['@type']];
                // A self-contained routing question is stored, not answer prose.
                $label = vertex_label($node);
                $state['last_question'] = $question . (stripos($question, $label) === false ? ' for ' . $label : '');
                $state['constraints'] = (isset($r['constraints']) ? $r['constraints'] : []);
                $state['result_type'] = (isset($r['result_type']) ? $r['result_type'] : (isset($response['retrieval']['query_plan']['result_type']) ? $response['retrieval']['query_plan']['result_type'] : SemanticQuery::target($question)));
                $path = (isset($response['retrieval']['path']) ? $response['retrieval']['path'] : []);
                if (!$state['result_type'] && $path && in_array(end($path), ConversationEntities::TYPES, true)) {
                    $state['result_type'] = end($path);
                }
                $state['intent'] = (isset($r['intent']) ? $r['intent'] : null);
                if (!empty($r['requested_result_types'])) {
                    $state['requested_result_types'] = $r['requested_result_types'];
                }
                if (isset($r['property'])) {
                    $state['property'] = $r['property'];
                }
                $state['constraints'] = call_user_func(function () use (&$state) {
                    $__kgCoalesce1 = self::validate($state);
                    return (isset($__kgCoalesce1['constraints']) ? $__kgCoalesce1['constraints'] : []);
                });
                $state['resolved_entities'] = array_merge([$state['subject']], $state['constraints']);
            }
        } elseif ((isset($response['status']) ? $response['status'] : '') === 'needs_clarification') {
            $state = $previous;
            $state['pending_question'] = $question;
            $state['pending_clarification'] = ['result_type' => call_user_func(function () use (&$question, &$previous) {
                $__kgCoalesce2 = SemanticQuery::target($question);
                return (isset($__kgCoalesce2) ? $__kgCoalesce2 : (isset($previous['result_type']) ? $previous['result_type'] : null));
            }), 'property' => (isset($r['property']) ? $r['property'] : null)];
        } elseif (in_array((isset($response['status']) ? $response['status'] : ''), ['unsupported', 'no_data'], true)) {
            $state = $previous;
        }
        unset($state['_provided'], $state['_invalid']);
        $state['version'] = 2;
        return PhpCompatibility::value($state, 'array', false);
    }
}
