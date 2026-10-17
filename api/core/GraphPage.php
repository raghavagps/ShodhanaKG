<?php
/** Deterministic fair traversal ordering and bounded, query-bound continuation. */
class GraphPage {
    public static function render(array $nodes, array $edges, array $seeds, array $options) {
        $seeds = array_values(array_unique($seeds));
        if (isset($options['budget']) && (!ctype_digit((string)$options['budget']) || (int)$options['budget'] < 1 || (int)$options['budget'] > 100000)) throw new InvalidArgumentException('Node limit must be an integer between 1 and 100000');
        $visibleTypes = isset($options['visibleTypes']) ? explode(',', $options['visibleTypes']) : null;
        $visibleEdges = isset($options['visibleEdges']) ? explode(',', $options['visibleEdges']) : null;
        $map = array();
        $hiddenTypes = explode(',',isset($options['hideTypes'])?$options['hideTypes']:'');
        $hiddenEdges = explode(',',isset($options['hideEdges'])?$options['hideEdges']:'');
        foreach ($nodes as $node) if (!in_array($node['type'],$hiddenTypes,true) && ($visibleTypes === null || in_array($node['type'], $visibleTypes, true))) $map[$node['id']] = $node;
        $links = array(); $adj = array();
        foreach ($edges as $edge) if (!in_array($edge['type'],$hiddenEdges,true) && isset($map[$edge['source']], $map[$edge['target']]) && ($visibleEdges === null || in_array($edge['type'], $visibleEdges, true))) {
            $links[$edge['id']] = $edge; $adj[$edge['source']][$edge['target']] = true; $adj[$edge['target']][$edge['source']] = true;
        }
        ksort($map); ksort($links); $ordered = array(); $selected = array(); $counts = array();
        if (isset($options['domain']) && $options['domain'] === 'compound') {
            $reached = array(); $queue = array();
            foreach ($seeds as $id) if (isset($map[$id])) { $reached[$id] = true; $queue[] = $id; }
            for ($i=0; $i<count($queue); $i++) foreach (isset($adj[$queue[$i]]) ? $adj[$queue[$i]] : array() as $id => $unused) {
                if (!isset($reached[$id])) { $reached[$id] = true; $queue[] = $id; }
            }
            $map = array_intersect_key($map, $reached);
            foreach ($links as $id => $edge) if (!isset($map[$edge['source']],$map[$edge['target']])) unset($links[$id]);
        }
        if (isset($options['integratedMode']) && $options['integratedMode'] === 'bridge') {
            foreach (array_keys($map) as $id) if (!isset($adj[$id])) unset($map[$id]);
        }
        foreach ($seeds as $seed) if (isset($map[$seed])) { $selected[$seed] = true; $ordered[] = $map[$seed]; $type = $map[$seed]['type']; $counts[$type] = isset($counts[$type]) ? $counts[$type]+1 : 1; }
        while (count($selected) < count($map)) {
            $candidates = array();
            foreach ($map as $id => $node) {
                if (isset($selected[$id])) continue;
                // Prefer connected candidates; retain genuine isolated records only when no frontier remains.
                if (isset($adj[$id]) && array_intersect_key($adj[$id], $selected)) $candidates[$id] = $node;
            }
            if (!$candidates) foreach ($map as $id => $node) if (!isset($selected[$id])) $candidates[$id] = $node;
            $chosen = null; $minimum = PHP_INT_MAX;
            foreach ($candidates as $id => $node) { $count = isset($counts[$node['type']]) ? $counts[$node['type']] : 0; if ($count < $minimum) { $chosen = $id; $minimum = $count; } }
            $ordered[] = $map[$chosen]; $selected[$chosen] = true; $counts[$map[$chosen]['type']] = $minimum+1;
        }
        $rank = array(); foreach ($ordered as $i => $node) $rank[$node['id']] = $i;
        $links = array_values($links);
        usort($links, function($a,$b) use ($rank) { $x = max($rank[$a['source']],$rank[$a['target']]); $y = max($rank[$b['source']],$rank[$b['target']]); return $x === $y ? strcmp($a['id'],$b['id']) : ($x < $y ? -1 : 1); });
        $signature = hash('sha256', json_encode(array($ordered,$links,$seeds,isset($options['queryKey'])?$options['queryKey']:'')));
        $nodeOffset=0; $edgeOffset=0;
        if (!empty($options['cursor'])) {
            $cursor = explode(':',$options['cursor']);
            if (count($cursor)!==3 || $cursor[0]!==$signature || !ctype_digit($cursor[1]) || !ctype_digit($cursor[2])) throw new InvalidArgumentException('Graph changed; restart loading.');
            $nodeOffset=(int)$cursor[1]; $edgeOffset=(int)$cursor[2];
            if ($nodeOffset>count($ordered) || $edgeOffset>count($links)) throw new InvalidArgumentException('Invalid graph continuation');
        }
        $budget=max(count(array_intersect($seeds,array_keys($map))), max(1,(int)(isset($options['budget'])?$options['budget']:300)));
        $nodeEnd=max($nodeOffset,min(count($ordered),$budget,$nodeOffset+300));
        // Finish pending edges before admitting another node page.
        if (isset($links[$edgeOffset]) && max($rank[$links[$edgeOffset]['source']],$rank[$links[$edgeOffset]['target']]) < $nodeOffset) $nodeEnd=$nodeOffset;
        $pageEdges=array(); $edgeEnd=$edgeOffset;
        while (isset($links[$edgeEnd]) && count($pageEdges)<1000 && max($rank[$links[$edgeEnd]['source']],$rank[$links[$edgeEnd]['target']]) < $nodeEnd) $pageEdges[]=$links[$edgeEnd++];
        $hasMore=$nodeEnd<count($ordered)||$edgeEnd<count($links);
        $withinBudget=$nodeEnd<min($budget,count($ordered)) || (isset($links[$edgeEnd]) && max($rank[$links[$edgeEnd]['source']],$rank[$links[$edgeEnd]['target']]) < $nodeEnd);
        $pathResults=array(); $loaded=array_flip(array_column(array_slice($links,0,$edgeEnd),'id')); $newEdges=array_flip(array_column($pageEdges,'id'));
        foreach (isset($options['evidencePaths']) ? $options['evidencePaths'] : array() as $path) {
            $pathIds=array_column($path['edges'],'id');
            if ($pathIds && !array_diff($pathIds,array_keys($loaded)) && array_intersect($pathIds,array_keys($newEdges))) $pathResults[]=$path;
        }
        return array('nodes'=>array_slice($ordered,$nodeOffset,$nodeEnd-$nodeOffset),'edges'=>$pageEdges,'meta'=>array('depth'=>1,'truncated'=>$hasMore,'totalNodes'=>count($ordered),'totalEdges'=>count($links),'loadedNodes'=>$nodeEnd,'loadedEdges'=>$edgeEnd,'categoryCounts'=>$counts,'cursor'=>$hasMore?$signature.':'.$nodeEnd.':'.$edgeEnd:null,'hasMore'=>$hasMore,'withinBudget'=>$withinBudget,'effectiveBudget'=>$budget,'evidencePaths'=>$pathResults,'continuations'=>array()));
    }
}
