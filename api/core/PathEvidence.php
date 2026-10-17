<?php

require_once __DIR__ . '/PhpCompatibility.php';
require_once __DIR__ . '/EvidenceCorrelator.php';
/** Study groups split only at an actual purified-product/treatment boundary. */
final class PathEvidence
{
    public static function boundary(array $previous, array $edge, array $node)
    {
        $types = [$previous['type'], $edge['type']];
        return PhpCompatibility::value($node['type'] === 'Purified_Compound' && in_array('PRODUCES', $types, true) && (in_array('TREATS', $types, true) || in_array('USED_FOR_TREATMENT', $types, true)), 'bool', false);
    }
    public static function groups(array $path, $separate)
    {
        $separate = PhpCompatibility::value($separate, 'bool', false);
        $groups = [];
        $anchor = null;
        $group = null;
        foreach ($path['edges'] as $i => $edge) {
            $split = $i > 0 && $separate && self::boundary($path['edges'][$i - 1], $edge, $path['nodes'][$i]) && EvidenceCorrelator::matchingIdentifier($anchor, $edge['evidence']) === null;
            if ($i === 0 || $split) {
                if ($group !== null) {
                    $groups[] = $group;
                }
                $anchor = $edge['evidence'];
                $group = ['edge_ids' => [$edge['id']], 'reference' => EvidenceCorrelator::matchingIdentifier($anchor, $anchor)];
            } else {
                $match = EvidenceCorrelator::matchingIdentifier($anchor, $edge['evidence']);
                if ($match === null || $match !== $group['reference']) {
                    return PhpCompatibility::value(null, 'array', true);
                }
                $group['edge_ids'][] = $edge['id'];
            }
        }
        if ($group !== null) {
            $groups[] = $group;
        }
        // A cross-study connection requires an identifiable citation for each group.
        if (count($groups) > 1) {
            foreach ($groups as $g) {
                if ($g['reference'] === null) {
                    return PhpCompatibility::value(null, 'array', true);
                }
            }
        }
        return PhpCompatibility::value($groups, 'array', true);
    }
}
