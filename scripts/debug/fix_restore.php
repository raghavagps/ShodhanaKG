<?php
$files = ['api/provenance-subgraph.php', 'api/compound-provenance.php'];
foreach ($files as $f) {
    $c = file_get_contents($f);
    
    // Restore correlation check
    $c = preg_replace('/\/\/ correlation check removed for all-path inclusion/', 'if (!EvidenceCorrelator::correlate($props1, $props2)) { continue; }', $c);
    
    // Restore pubmed requirement on edges (provenance-subgraph)
    $c = preg_replace('/if \(\$therapyRid\)/', 'if ($therapyRid && ($props[\'pubmed_id\'] !== \'\' || $props[\'doi\'] !== \'\' || $props[\'ayush_id\'] !== \'\'))', $c);
    $c = preg_replace('/if \(\$therapyRid && \$formulationRid\)/', 'if ($therapyRid && $formulationRid && ($props[\'pubmed_id\'] !== \'\' || $props[\'doi\'] !== \'\' || $props[\'ayush_id\'] !== \'\'))', $c);
    
    // Restore pubmed requirement on edges (compound-provenance)
    $c = preg_replace('/if \(\$methodRid\)/', 'if ($methodRid && ($props[\'pubmed_id\'] !== \'\' || $props[\'doi\'] !== \'\' || $props[\'ayush_id\'] !== \'\'))', $c);
    $c = preg_replace('/if \(\$methodRid && \$mediaRid\)/', 'if ($methodRid && $mediaRid && ($props[\'pubmed_id\'] !== \'\' || $props[\'doi\'] !== \'\' || $props[\'ayush_id\'] !== \'\'))', $c);

    file_put_contents($f, $c);
}
echo "Restored strict filtering rules.\n";
