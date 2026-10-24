<?php

require_once __DIR__ . '/PhpCompatibility.php';
/** Validate transport types before invoking routing or graph retrieval. */
final class ChatRequest
{
    public static function decode($body)
    {
        $body = PhpCompatibility::value($body, 'string', false);
        $object = json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($object)) {
            throw new InvalidArgumentException('Request must be a JSON object');
        }
        $input = json_decode($body, true);
        if (!is_string((isset($input['question']) ? $input['question'] : null)) || trim($input['question']) === '') {
            throw new InvalidArgumentException('Question must be a non-empty string');
        }
        if (mb_strlen($input['question']) > 4000) {
            throw new InvalidArgumentException('Question must not exceed 4000 characters');
        }
        foreach (['conversation_context', 'selected_entity'] as $key) {
            if (isset($object->{$key}) && !is_object($object->{$key})) {
                throw new InvalidArgumentException($key . ' must be an object');
            }
        }
        if (isset($object->history) && !is_array($object->history)) {
            throw new InvalidArgumentException('History must be an array');
        }
        if (isset($input['page_offset']) && (!is_int($input['page_offset']) || $input['page_offset'] < 0)) {
            throw new InvalidArgumentException('Page offset must be a non-negative integer');
        }
        if (isset($input['stream']) && !is_bool($input['stream'])) {
            throw new InvalidArgumentException('Stream must be a boolean');
        }
        $input['question'] = trim($input['question']);
        if (isset($input['faq_id'])) {
            require_once __DIR__ . '/FAQMatcher.php';
            if (!is_string($input['faq_id']) || FAQMatcher::find('', $input['faq_id']) === null) throw new InvalidArgumentException('Unknown FAQ identifier');
        }
        return PhpCompatibility::value($input, 'array', false);
    }
}
