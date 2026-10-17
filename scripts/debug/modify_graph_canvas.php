<?php
$file = 'src/components/explorer/GraphCanvas.tsx';
$content = file_get_contents($file);

$search = <<<'EOD'
    useEffect(() => {
      const cy = cyRef.current;
      if (!cy || !highlightedNodeId) return;
      const node = cy.getElementById(highlightedNodeId);
      if (node && node.length) {
        cy.elements().removeClass("highlighted faded");
        const nbhd = node.closedNeighborhood();
        cy.elements().not(nbhd).addClass("faded");
        nbhd.addClass("highlighted");
        cy.animate({ center: { eles: node }, duration: 250 });
      }
    }, [highlightedNodeId]);
EOD;

$replace = <<<'EOD'
    useEffect(() => {
      const cy = cyRef.current;
      if (!cy || !highlightedNodeId) return;
      const node = cy.getElementById(highlightedNodeId);
      if (node && node.length) {
        cy.elements().removeClass("highlighted faded");
        let nbhd = node.closedNeighborhood();
        
        if (provenancePaths && provenancePaths.length > 0) {
            const provenanceEdgeIds = new Set<string>();
            provenancePaths.forEach((path) => {
                provenanceEdgeIds.add(path.edge1Id);
                provenanceEdgeIds.add(path.edge2Id);
            });
            
            cy.edges().forEach((edge) => {
                if (provenanceEdgeIds.has(edge.id())) {
                    nbhd = nbhd.union(edge);
                    nbhd = nbhd.union(edge.connectedNodes());
                }
            });
        }
        
        cy.elements().not(nbhd).addClass("faded");
        nbhd.addClass("highlighted");
        cy.animate({ center: { eles: node }, duration: 250 });
      }
    }, [highlightedNodeId, provenancePaths]);
EOD;

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Updated GraphCanvas.tsx\n";
