<?php
$files = ['api/provenance-subgraph.php', 'api/compound-provenance.php'];
foreach ($files as $f) {
    $c = file_get_contents($f);
    
    // Remove strict correlator check
    $c = preg_replace('/if \(\!EvidenceCorrelator::correlate\(\$props1, \$props2\)\)\s*\{\s*continue;\s*\}/', '// correlation check removed for all-path inclusion', $c);
    
    // Remove pubmed requirement on edges
    $c = preg_replace('/if \(\$therapyRid && \(\$props\[\'pubmed_id\'\] !== \'\' \|\| \$props\[\'doi\'\] !== \'\' \|\| \$props\[\'ayush_id\'\] !== \'\'\)\)/', 'if ($therapyRid)', $c);
    $c = preg_replace('/if \(\$therapyRid && \$formulationRid && \(\$props\[\'pubmed_id\'\] !== \'\' \|\| \$props\[\'doi\'\] !== \'\' \|\| \$props\[\'ayush_id\'\] !== \'\'\)\)/', 'if ($therapyRid && $formulationRid)', $c);
    
    // Same for compound-provenance methods/media
    $c = preg_replace('/if \(\$methodRid && \(\$props\[\'pubmed_id\'\] !== \'\' \|\| \$props\[\'doi\'\] !== \'\' \|\| \$props\[\'ayush_id\'\] !== \'\'\)\)/', 'if ($methodRid)', $c);
    $c = preg_replace('/if \(\$methodRid && \$mediaRid && \(\$props\[\'pubmed_id\'\] !== \'\' \|\| \$props\[\'doi\'\] !== \'\' \|\| \$props\[\'ayush_id\'\] !== \'\'\)\)/', 'if ($methodRid && $mediaRid)', $c);

    file_put_contents($f, $c);
}
echo "Replaced.";
