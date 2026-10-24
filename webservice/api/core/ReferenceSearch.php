<?php
require_once __DIR__ . '/ReferenceClassifier.php';
class ReferenceSearch {
    public static function run(array $snapshot, array $filters) {
        $namespace = isset($filters['namespace']) ? $filters['namespace'] : 'both';
        $domain = isset($filters['domain']) ? $filters['domain'] : 'integrated';
        if (!in_array($namespace, array('both', 'PMID', 'AYUSH'), true) || !in_array($domain, array('disease', 'compound', 'integrated'), true)) throw new InvalidArgumentException('Invalid namespace or domain');
        $identifier = trim(isset($filters['identifier']) ? $filters['identifier'] : '');
        $declared = preg_match('~^(?:PMID:|https?://pubmed\.ncbi\.nlm\.nih\.gov/)~i', $identifier) ? 'PMID' : (preg_match('/^AYUSH(?: ID)?:/i', $identifier) ? 'AYUSH' : null);
        if ($declared !== null) {
            if ($namespace !== 'both' && $namespace !== $declared) throw new InvalidArgumentException('Identifier prefix does not match selected namespace');
            $namespace = $declared;
        }
        $identifier = preg_replace('~^(?:PMID:\s*|AYUSH(?: ID)?:\s*|https?://pubmed\.ncbi\.nlm\.nih\.gov/)~i', '', $identifier);
        $identifier = rtrim($identifier, '/');
        $q = trim(isset($filters['q']) ? $filters['q'] : '');
        $type = isset($filters['type']) ? $filters['type'] : '';
        $relationship = isset($filters['relationship']) ? $filters['relationship'] : '';
        $allowedTypes = vertex_types_for_domain($domain);
        $allowedRelations = edge_types_for_domain($domain);
        $types = array(); $relations = array();
        foreach ($snapshot['nodes'] as $node) if (in_array($node['@type'], $allowedTypes, true)) $types[$node['@type']] = true;
        foreach ($snapshot['edges'] as $edge) if (in_array($edge['@type'], $allowedRelations, true)) $relations[$edge['@type']] = true;
        if ($type !== '' && !in_array($type, $allowedTypes, true)) throw new InvalidArgumentException('Entity type is not applicable to this domain');
        if ($relationship !== '' && !in_array($relationship, $allowedRelations, true)) throw new InvalidArgumentException('Relationship is not applicable to this domain');
        $typeNames = array_keys($types); $relationNames = array_keys($relations); sort($typeNames); sort($relationNames);
        if (isset($filters['facets']) && $filters['facets'] === '1') return array('types'=>$typeNames,'relationships'=>$relationNames);
        if (isset($filters['limit']) && (!ctype_digit((string)$filters['limit']) || (int)$filters['limit'] < 1 || (int)$filters['limit'] > 100)) throw new InvalidArgumentException('Limit must be an integer between 1 and 100');
        $groups = array();
        foreach ($snapshot['edges'] as $id => $edge) {
            if (!isset($snapshot['nodes'][$edge['@out']], $snapshot['nodes'][$edge['@in']])) continue;
            $source = $snapshot['nodes'][$edge['@out']]; $target = $snapshot['nodes'][$edge['@in']];
            if (!in_array($source['@type'], vertex_types_for_domain($domain), true) && !in_array($target['@type'], vertex_types_for_domain($domain), true)) continue;
            if ($type !== '' && $source['@type'] !== $type && $target['@type'] !== $type) continue;
            if ($relationship !== '' && $edge['@type'] !== $relationship) continue;
            $public = array();
            foreach (array($source, $target, $edge) as $record) foreach ($record as $key => $value) if ($key !== '' && $key[0] !== '@' && !preg_match('/^(?:in|out)(?:_|$)/', $key)) $public[] = $value;
            if ($q !== '' && mb_stripos(json_encode($public, JSON_UNESCAPED_UNICODE), $q) === false) continue;
            foreach (ReferenceClassifier::resolve($edge, $id) as $ref) {
                if (!in_array($ref['namespace'], array('PMID', 'AYUSH'), true) || !in_array($ref['status'], array('verified', 'declared'), true)) continue;
                if ($namespace !== 'both' && $ref['namespace'] !== $namespace) continue;
                if ($identifier !== '' && strcasecmp($identifier, $ref['identifier']) !== 0) continue;
                $key = $ref['namespace'] . ':' . $ref['identifier'];
                if (!isset($groups[$key])) $groups[$key] = array('id' => $key, 'reference' => $ref, 'records' => array());
                $map = array($source['@rid'] => build_node($source), $target['@rid'] => build_node($target));
                $groups[$key]['records'][$id] = array('source' => $map[$source['@rid']], 'target' => $map[$target['@rid']], 'edge' => build_edge($edge, $map));
            }
        }
        ksort($groups); $edgeIds = array();
        foreach ($groups as &$group) { foreach ($group['records'] as $id => $record) $edgeIds[$id] = true; $group['records'] = array_values($group['records']); } unset($group);
        $signature = hash('sha256', json_encode(array($namespace,$domain,$identifier,$q,$type,$relationship, $groups)));
        $offset = 0;
        if (!empty($filters['cursor'])) { $cursor = explode(':', $filters['cursor']); if (count($cursor) !== 2 || $cursor[0] !== $signature || !ctype_digit($cursor[1])) throw new InvalidArgumentException('Search changed; restart pagination.'); $offset = (int)$cursor[1]; }
        $limit = max(1, min(100, isset($filters['limit']) ? (int)$filters['limit'] : 25));
        $typeNames = array_keys($types); $relationNames = array_keys($relations); sort($typeNames); sort($relationNames);
        return array('groups' => array_slice(array_values($groups), $offset, $limit), 'totalGroups' => count($groups), 'totalEdges' => count($edgeIds), 'nextCursor' => $offset+$limit < count($groups) ? $signature . ':' . ($offset+$limit) : null, 'types' => $typeNames, 'relationships' => $relationNames, 'fetchedAt' => $snapshot['fetched_at']);
    }
}
