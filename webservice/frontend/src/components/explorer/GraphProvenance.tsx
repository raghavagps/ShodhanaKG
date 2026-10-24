import { useMemo } from "react";
import { useProvenanceSubgraph } from "@/lib/api";
import type { GenericNode, GenericEdge } from "@/components/explorer/GraphCanvas";
import type { GraphNode, GraphEdge } from "@/data/diseaseGraph";

/**
 * Helper hook to extract provenance edge IDs for graph highlighting
 * 
 * Usage in DiseaseGraphView:
 * 
 * const { provenanceEdgeIds } = useProvenanceHighlighting(selectedDiseaseId);
 * 
 * <GraphCanvas
 *   ...existing props
 *   provenancePaths={provenanceEdgeIds}
 * />
 */
export const useProvenanceHighlighting = (diseaseId: string | null) => {
  const { data } = useProvenanceSubgraph(diseaseId);

  const provenanceEdgeIds = useMemo(() => {
    if (!data?.paths) return [];
    
    return data.paths.map((path) => ({
      edge1Id: path.edge1.id,
      edge2Id: path.edge2.id,
      pubmedId: path.provenance.pubmedId,
    }));
  }, [data]);

  return {
    provenanceEdgeIds,
    paths: data?.paths || [],
    meta: data?.meta,
  };
};

/**
 * Helper to filter graph to show only provenance-validated connections
 */
import { useCompoundProvenance } from "@/lib/api";

export const useCompoundProvenanceHighlighting = (compoundId: string | null) => {
  const { data } = useCompoundProvenance(compoundId);

  const provenanceEdgeIds = useMemo(() => {
    if (!data?.paths) return [];
    
    return data.paths.map((path) => ({
      edge1Id: path.edge1.id,
      edge2Id: path.edge2.id,
      pubmedId: path.provenance.pubmedId || '',
    }));
  }, [data]);

  return {
    provenanceEdgeIds,
    paths: data?.paths || [],
    meta: data?.meta,
  };
};
export const filterGraphByProvenance = (
  nodes: GenericNode[],
  edges: GenericEdge[],
  provenancePaths: { edge1Id: string; edge2Id: string }[]
): { nodes: GenericNode[]; edges: GenericEdge[] } => {
  const provenanceEdgeIds = new Set<string>();
  provenancePaths.forEach((p) => {
    provenanceEdgeIds.add(p.edge1Id);
    provenanceEdgeIds.add(p.edge2Id);
  });

  const filteredEdges = edges.filter((e) => provenanceEdgeIds.has(e.id));
  const connectedNodeIds = new Set<string>();
  filteredEdges.forEach((e) => {
    connectedNodeIds.add(e.source);
    connectedNodeIds.add(e.target);
  });
  const filteredNodes = nodes.filter((n) => connectedNodeIds.has(n.id));

  return { nodes: filteredNodes, edges: filteredEdges };
};

/**
 * Example: Provenance integration in DiseaseGraphView
 */
export default useProvenanceHighlighting;
