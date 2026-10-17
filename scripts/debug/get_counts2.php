<?php
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/graph_service.php';

try {
    $types = get_schema_vertex_types();
    $total_nodes = 0;
    
    $edges = edge_types_for_domain('integrated');
    $total_edges = 0;
    
    echo "Total Node Types: " . count($types) . "\n";
    echo "Total Edge Types: " . count($edges) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
