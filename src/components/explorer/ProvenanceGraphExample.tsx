import { useProvenanceHighlighting } from "./GraphProvenance";
import { ProvenancePanel } from "./ProvenancePanel";

export const ProvenanceGraphExample = () => {
  // This would come from your graph selection
  const selectedDiseaseId = "36"; // Eczema
  const { provenanceEdgeIds, paths, meta } = useProvenanceHighlighting(selectedDiseaseId);

  return (
    <div className="space-y-4">
      <div className="text-sm">
        <p>Found {meta?.totalPaths} provenance paths</p>
        <p>Highlighting {provenanceEdgeIds.length * 2} edges in graph</p>
      </div>

      {/* Graph with provenance highlighting */}
      {/*
      <GraphCanvas
        nodes={nodes}
        edges={edges}
        nodeColors={NODE_COLORS}
        onSelectNode={handleSelectNode}
        onSelectEdge={handleSelectEdge}
        onBackgroundClick={handleBackgroundClick}
        provenancePaths={provenanceEdgeIds}
      />
      */}

      {/* Provenance panel */}
      <ProvenancePanel diseaseId={selectedDiseaseId} />
    </div>
  );
};

