<?php
require_once dirname(__DIR__) . '/db.php';

/**
 * Handles database queries and telemetry collection for deterministic retrieval.
 */
class GraphRetriever {
    
    /**
     * Executes an ArcadeDB SQL query and logs telemetry if a debug collector is provided.
     * 
     * @param string $sql The ArcadeDB SQL query
     * @param array $params Query parameters
     * @param array &$debugCollector Telemetry array
     * @return array Result array from ArcadeDB
     * @throws Exception if query fails
     */
    public static function execute($sql, $params = [], &$debugCollector = null) {
        if (is_array($debugCollector)) {
            if (!isset($debugCollector['sql_queries'])) $debugCollector['sql_queries'] = [];
            $debugCollector['sql_queries'][] = ['sql' => $sql, 'params' => $params];
        }
        
        $res = arcade_command($sql, 'sql', $params);
        
        if (isset($res['error'])) {
            throw new Exception("GraphRetriever Error: " . $res['error'] . " | SQL: " . $sql . " | Params: " . json_encode($params));
        }
        
        return isset($res['result']) ? $res['result'] : [];
    }
    
    /**
     * Resolves the names for an array of node RIDs dynamically.
     * 
     * @param array $rids Array of RIDs (e.g. ['#12:0', '#12:1'])
     * @param array &$debugCollector Telemetry array
     * @param string $explicitType If known, query the specific type to optimize, otherwise uses generic `{rid}` approach
     * @return array Map of RID to Node Name
     */
    public static function fetchNodeNames($rids, &$debugCollector = null, $explicitType = null) {
        $map = [];
        if (empty($rids)) return $map;
        
        $rids = array_unique($rids);
        
        if ($explicitType) {
            $ridsCsv = implode(',', array_map(function($r) { return "'$r'"; }, $rids));
            $sql = "SELECT @rid as rid, name FROM `{$explicitType}` WHERE @rid IN [$ridsCsv]";
            $nodes = self::execute($sql, [], $debugCollector);
            foreach ($nodes as $node) {
                $map[$node['rid']] = $node['name'];
            }
        } else {
            // ArcadeDB allows selecting directly from RIDs
            foreach ($rids as $rid) {
                $sql = "SELECT name FROM {$rid}";
                $nodes = self::execute($sql, [], $debugCollector);
                if (!empty($nodes)) {
                    $map[$rid] = isset($nodes[0]['name']) ? $nodes[0]['name'] : 'Unknown';
                } else {
                    $map[$rid] = 'Unknown';
                }
            }
        }
        
        return $map;
    }
}
