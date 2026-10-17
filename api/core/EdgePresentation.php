<?php

require_once __DIR__ . '/PhpCompatibility.php';
/** Display metadata derived from a stored edge, never inferred from its nodes. */
final class EdgePresentation
{
    public static function phase($type)
    {
        $type = PhpCompatibility::value($type, 'string', false);
        $phases = ['PRE_TREATED_BY' => 'pre-treated', 'TREATED_BY' => 'treated', 'POST_TREATED_BY' => 'post-treated'];
        return PhpCompatibility::value((isset($phases[$type]) ? $phases[$type] : null), 'string', true);
    }
    public static function meaningful($value)
    {
        if ($value === null) {
            return PhpCompatibility::value(false, 'bool', false);
        }
        if (is_string($value)) {
            return PhpCompatibility::value(!in_array(strtolower(trim($value)), ['', 'null', 'not supplied'], true), 'bool', false);
        }
        if (is_array($value)) {
            return PhpCompatibility::value(count(array_filter($value, [self::class, 'meaningful'])) > 0, 'bool', false);
        }
        return PhpCompatibility::value(true, 'bool', false);
    }
    public static function properties(array $raw)
    {
        $result = [];
        foreach ($raw as $key => $value) {
            if (strpos($key, '@') === 0 || preg_match('/^(ref|references|reference|pubmed[_ ]?id|doi|ayush[_ ]?id|unclassified_reference|phase)$/i', $key)) {
                continue;
            }
            if (self::meaningful($value)) {
                $result[] = ['key' => $key, 'label' => ucfirst(str_replace('_', ' ', $key)), 'value' => $value];
            }
        }
        return PhpCompatibility::value($result, 'array', false);
    }
    public static function metadata(array $edge)
    {
        return PhpCompatibility::value(['display_properties' => self::properties($edge['properties']), 'treatment_phase' => self::phase($edge['type'])], 'array', false);
    }
}
