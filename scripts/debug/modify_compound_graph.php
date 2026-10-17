<?php
$file = 'src/pages/explorers/CompoundGraphView.tsx';
$content = file_get_contents($file);

if (strpos($content, 'useCompoundProvenanceHighlighting') === false) {
    $content = str_replace(
        'import { CompoundProvenancePanel } from "@/components/explorer/CompoundProvenancePanel";',
        "import { CompoundProvenancePanel } from \"@/components/explorer/CompoundProvenancePanel\";\nimport { useCompoundProvenanceHighlighting } from \"@/components/explorer/GraphProvenance\";",
        $content
    );
    
    $content = str_replace(
        'const selectedNodeObj = selectedNodeId ? nodes.find((n) => n.id === selectedNodeId) : null;',
        "const selectedNodeObj = selectedNodeId ? nodes.find((n) => n.id === selectedNodeId) : null;\n  const { provenanceEdgeIds } = useCompoundProvenanceHighlighting(selectedNodeObj?.type === 'Compound' ? selectedNodeId : null);",
        $content
    );
    
    $content = str_replace(
        'highlightedNodeId={highlightedNodeId}',
        "highlightedNodeId={highlightedNodeId}\n                provenancePaths={provenanceEdgeIds}",
        $content
    );
    
    file_put_contents($file, $content);
    echo "Updated CompoundGraphView.tsx to use provenance highlighting\n";
} else {
    echo "Already updated CompoundGraphView.tsx\n";
}
