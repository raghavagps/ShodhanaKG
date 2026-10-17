<?php
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/graph_service.php';

try {
    $types = get_vertex_types();
    $total_nodes = 0;
    foreach ($types as $type) {
        $res = arcade_command("SELECT count(*) as cnt FROM `$type`", 'sql', []);
        if (isset($res[0]['cnt'])) $total_nodes += $res[0]['cnt'];
    }
    
    $edges = get_edge_types();
    $total_edges = 0;
    foreach ($edges as $edge) {
        $res = arcade_command("SELECT count(*) as cnt FROM `$edge`", 'sql', []);
        if (isset($res[0]['cnt'])) $total_edges += $res[0]['cnt'];
    }
    
    echo "Total Nodes: " . $total_nodes . "\n";
    echo "Total Edges: " . $total_edges . "\n";
    echo "Total Node Types: " . count($types) . "\n";
    echo "Total Edge Types: " . count($edges) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
