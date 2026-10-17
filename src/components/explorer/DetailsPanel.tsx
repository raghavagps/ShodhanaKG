import { PropertyList } from "./PropertyList";
import { EdgeProperties } from "./EdgeProperties";
import type { DisplayProperty } from "@/lib/types";
import { ReferenceLinks } from "./ReferenceLinks";
import type { PublicationReference } from "@/lib/types";
import { ArrowRight, Info } from "lucide-react";
import { GenericNode, GenericEdge } from "./GraphCanvas";


interface Props {
  selectedNode?: GenericNode;
  selectedEdge?: { edge: GenericEdge; source: GenericNode; target: GenericNode };
  nodeTypeLabels: Record<string, string>;
  edgeTypeLabels: Record<string, string>;
  nodeColors: Record<string, string>;
  edgeColors: Record<string, string>;
  /** Edge property keys to show in details, with display labels */
  edgePropertyMap?: Record<string, { label: string; key: string }[]>;
  hideDuplicateDescription?: boolean;
}

export const DetailsPanel = ({
  selectedNode,
  selectedEdge,
  nodeTypeLabels,
  edgeTypeLabels,
  nodeColors,
  edgeColors,
  edgePropertyMap = {},
  hideDuplicateDescription = false,
}: Props) => {
  

  if (!selectedNode && !selectedEdge) {
    return (
      <div className="h-full flex flex-col items-center justify-center text-center px-6 text-muted-foreground">
        <Info className="h-6 w-6 mb-3 opacity-60" />
        <p className="text-sm font-medium text-foreground">{"Nothing selected"}</p>
        <p className="text-xs mt-1 max-w-[220px]">
          {"Click a node or edge in the graph to inspect its properties and source evidence."}
        </p>
      </div>
    );
  }

  if (selectedNode) {
    const color = nodeColors[selectedNode.type];
    
    // Check if there's a description
    const hasDescription = selectedNode.description && String(selectedNode.description).trim() !== "";
    
    // Check if there are any properties to display
    const propertyEntries = Object.entries(selectedNode).filter(([k, v]) => {
      if (["id", "label", "type", "description", "@cat"].includes(k)) return false;
      if (v === null || v === undefined || v === "") return false;
      if (hideDuplicateDescription && hasDescription && typeof v === "string" && v.trim() === String(selectedNode.description).trim()) return false;
      return true;
    });
    const hasProperties = propertyEntries.length > 0;
    
    return (
      <div className="p-5 space-y-4">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <span className="h-2.5 w-2.5 rounded-full" style={{ background: color }} />
            <span className="text-[10px] uppercase tracking-[0.16em] font-semibold text-muted-foreground">
              {"Node"} · {(nodeTypeLabels[selectedNode.type] ?? selectedNode.type)}
            </span>
          </div>
          <h3 className="font-display text-lg font-semibold text-foreground leading-tight">
            {(selectedNode.label)}
          </h3>
        </div>

        {hasDescription && (
          <div className="rounded-md bg-secondary/50 p-3 text-xs text-foreground/90 leading-relaxed">
            {selectedNode.description}
          </div>
        )}

        {hasProperties && (
          <div>
            <div className="text-[10px] uppercase tracking-wider text-muted-foreground mb-1.5">{"Properties"}</div>
            {/* Dynamic properties from DB */}
            <PropertyList properties={propertyEntries.map(([key, value]) => ({ key, label: (key.replace(/_/g, " ")), value }))} />
          </div>
        )}
      </div>
    );
  }

  // Edge
  const { edge, source, target } = selectedEdge!;
  const color = edgeColors[edge.type];
  const props = edgePropertyMap[edge.type] ?? [];
  const definedProps = props.filter((p) => edge[p.key] !== undefined && edge[p.key] !== null && edge[p.key] !== "");

  return (
    <div className="p-5 space-y-4">
      <div>
        <div className="flex items-center gap-2 mb-2 flex-wrap">
          <span className="h-2.5 w-2.5 rounded-full" style={{ background: color }} />
          <span className="text-[10px] uppercase tracking-[0.16em] font-semibold text-muted-foreground">
            {"Relationship"}
          </span>
        </div>
        <h3 className="font-display text-lg font-semibold text-foreground leading-tight">
          {(edgeTypeLabels[edge.type] ?? edge.type).replace(/_/g, " ").toLowerCase().replace(/(^|\s)(\p{L})/gu, (_, space, letter) => space + letter.toUpperCase())}
        </h3>
      </div>

      <div className="rounded-lg bg-secondary/50 border border-border/60 p-3 animate-fade-in">
        <div className="flex flex-col items-stretch gap-1.5">
          <div className="flex items-start gap-2">
            <span
              className="h-2 w-2 mt-1.5 rounded-full shrink-0"
              style={{ background: nodeColors[source.type] }}
            />
            <span
              className="flex-1 rounded-md bg-background border border-border/70 px-2.5 py-1.5 text-xs font-semibold text-foreground break-words whitespace-normal leading-snug shadow-sm"
              style={{ borderColor: nodeColors[source.type] }}
            >
              {(source.label)}
            </span>
          </div>

          <div className="flex items-center gap-2 pl-1">
            <ArrowRight
              className="h-4 w-4 rotate-90 shrink-0"
              style={{ color }}
              strokeWidth={2.5}
            />
            <span
              className="text-[10px] uppercase tracking-wider font-semibold px-1.5 py-0.5 rounded"
              style={{ color }}
            >
              {(edgeTypeLabels[edge.type] ?? edge.type)}
            </span>
          </div>

          <div className="flex items-start gap-2">
            <span
              className="h-2 w-2 mt-1.5 rounded-full shrink-0"
              style={{ background: nodeColors[target.type] }}
            />
            <span
              className="flex-1 rounded-md bg-background border border-border/70 px-2.5 py-1.5 text-xs font-semibold text-foreground break-words whitespace-normal leading-snug shadow-sm"
              style={{ borderColor: nodeColors[target.type] }}
            >
              {(target.label)}
            </span>
          </div>
        </div>
      </div>

      <div>
        <div className="text-[10px] uppercase tracking-wider text-muted-foreground mb-1.5">{"Properties"}</div>
        {edge.display_properties ? <EdgeProperties properties={edge.display_properties as DisplayProperty[]} phase={edge.treatment_phase as string} /> : <PropertyList properties={definedProps.map(p => ({ key: p.key, label: (p.label), value: edge[p.key] }))} />}
        {(edge.references || edge.reference || edge.pubmedId) && <div className="mt-3"><ReferenceLinks minimalist references={edge.references as PublicationReference[]} raw={String(edge.reference ?? edge.pubmedId ?? "")} /></div>}
        {definedProps.length === 0 && !edge.display_properties && !edge.pubmedId && (
          <div className="text-xs text-muted-foreground italic py-1">No additional properties.</div>
        )}
      </div>

      
    </div>
  );
};
