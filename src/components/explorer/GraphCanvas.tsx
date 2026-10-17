import { applyGraphFocus } from '@/lib/graphFocus';
import { useEffect, useMemo, useRef, useState } from "react";
import cytoscape, { Core, ElementDefinition } from "cytoscape";
import { ZoomIn, ZoomOut, Maximize2 } from "lucide-react";
import { cn } from "@/lib/utils";


export interface GenericNode {
  id: string;
  label: string;
  type: string;
  description?: string;
  [key: string]: unknown;
}

export interface GenericEdge {
  id: string;
  source: string;
  target: string;
  type: string;
  pubmedId?: string;
  [k: string]: unknown;
}

export type GraphLayoutKey = "cose" | "circle" | "concentric" | "breadthfirst" | "grid";

const LAYOUT_OPTIONS: { value: GraphLayoutKey; label: string }[] = [
  { value: "cose", label: "Force-directed" },
  { value: "breadthfirst", label: "Hierarchical" },
  { value: "circle", label: "Circular" },
  { value: "concentric", label: "Concentric" },
  { value: "grid", label: "Grid" },
];

// Single, uniform edge color used across all graph views.
const UNIFORM_EDGE_COLOR = "hsl(25, 18%, 45%)";

interface Props {
  nodes: GenericNode[];
  edges: GenericEdge[];
  nodeColors: Record<string, string>;
  /** Kept for API compatibility — edges now render in a single uniform color. */
  edgeColors?: Record<string, string>;
  onSelectNode: (n: GenericNode) => void;
  onSelectEdge: (e: GenericEdge) => void;
  onBackgroundClick: () => void;
  highlightedNodeId?: string;
  dimEdges?: boolean;
  /** Highlight edges that belong to provenance paths (matching PMIDs) */
  provenancePaths?: { edge1Id: string; edge2Id: string; pubmedId: string }[];
}

const buildLayoutOptions = (name: GraphLayoutKey): cytoscape.LayoutOptions => {
  const common = { animate: false, fit: true, padding: 40 } as const;
  switch (name) {
    case "circle":
      return { name: "circle", ...common, spacingFactor: 1.4 } as cytoscape.LayoutOptions;
    case "concentric":
      return {
        name: "concentric",
        ...common,
        minNodeSpacing: 30,
        concentric: (n: cytoscape.NodeSingular) => n.degree(false),
        levelWidth: () => 2,
      } as cytoscape.LayoutOptions;
    case "breadthfirst":
      return {
        name: "breadthfirst",
        ...common,
        directed: true,
        spacingFactor: 1.2,
        grid: false,
      } as cytoscape.LayoutOptions;
    case "grid":
      return { name: "grid", ...common, avoidOverlap: true } as cytoscape.LayoutOptions;
    case "cose":
    default:
      return {
        name: "cose",
        ...common,
        randomize: false,
        nodeRepulsion: () => 4500,
        idealEdgeLength: () => 95,
        edgeElasticity: () => 70,
        gravity: 0.45,
        numIter: 500,
      } as cytoscape.LayoutOptions;
  }
};



export const GraphCanvas = ({
  nodes,
  edges,
  nodeColors,
  edgeColors,
  onSelectNode,
  onSelectEdge,
  onBackgroundClick,
  highlightedNodeId,
  provenancePaths,
  dimEdges = true,
}: Props) => {
  
  const containerRef = useRef<HTMLDivElement>(null);
  const cyRef = useRef<Core | null>(null);
  const edgesRef = useRef(edges);
  const nodesRef = useRef(nodes);
  const onSelectNodeRef = useRef(onSelectNode);
  const onSelectEdgeRef = useRef(onSelectEdge);
  const onBackgroundClickRef = useRef(onBackgroundClick);
  const activeNodeRef = useRef<string>();
  const notifiedNodeRef = useRef<string>();
  const provenanceRef = useRef(provenancePaths);
  provenanceRef.current = provenancePaths;
  const dimEdgesRef = useRef(dimEdges);
  dimEdgesRef.current = dimEdges;
  const expandedNodeIdRef = useRef<string | undefined>(undefined);
  edgesRef.current = edges;
  nodesRef.current = nodes;
  onSelectNodeRef.current = onSelectNode;
  onSelectEdgeRef.current = onSelectEdge;
  onBackgroundClickRef.current = onBackgroundClick;
  const [hover, setHover] = useState<{ x: number; y: number; label: string; sub: string } | null>(null);
  const [layoutName, setLayoutName] = useState<GraphLayoutKey>("cose");
  const [expandedNodeId, setExpandedNodeId] = useState<string | undefined>(undefined);
  expandedNodeIdRef.current = expandedNodeId;

  const elements = useMemo<ElementDefinition[]>(() => {
    const nodeIds = new Set(nodes.map((n) => n.id));
    return [
      ...nodes.map((n) => ({
        data: { ...n, id: n.id, label: (n.label), type: n.type, description: n.description },
      })),
      ...edges
        .filter((e) => nodeIds.has(e.source) && nodeIds.has(e.target))
        .map((e) => ({
          data: { id: e.id, source: e.source, target: e.target, label: (e.type), type: e.type, pubmedId: e.pubmedId },
        })),
    ];
  }, [nodes, edges]);

  useEffect(() => {
    if (!containerRef.current) return;

    const cy = cytoscape({
      container: containerRef.current,
      elements,
      style: [
        {
          selector: "node",
          style: {
            "background-color": (ele: cytoscape.NodeSingular) =>
              nodeColors[ele.data("type") as string] ?? "hsl(25, 25%, 35%)",
            label: "data(label)",
            color: "hsl(25, 25%, 15%)",
            "font-size": 11,
            "font-family": "Inter, sans-serif",
            "font-weight": 500,
            "text-valign": "bottom",
            "text-margin-y": 6,
            "text-background-color": "hsl(40, 38%, 95%)",
            "text-background-opacity": 0.85,
            "text-background-padding": "2px",
            "text-background-shape": "roundrectangle",
            "text-events": "no",
            "text-wrap": "ellipsis",
            "text-max-width": "120px",
            width: 34,
            height: 34,
            "border-width": 2,
            "border-color": "hsl(40, 38%, 95%)",
            "overlay-opacity": 0,
            "transition-property": "border-color, border-width, width, height",
            "transition-duration": 150,
          } as cytoscape.Css.Node,
        },
        {
          selector: "node:selected",
          style: { "border-color": "hsl(25, 25%, 15%)", "border-width": 3, width: 40, height: 40 } as cytoscape.Css.Node,
        },
        {
          selector: "node.highlighted",
          style: { "border-color": "hsl(16, 45%, 50%)", "border-width": 3 } as cytoscape.Css.Node,
        },
        { selector: "node.faded", style: { opacity: 0.25 } as cytoscape.Css.Node },
        {
          selector: "edge",
          style: {
            width: 1.5,
            "line-color": (ele: cytoscape.EdgeSingular) =>
              (edgeColors ?? {})[ele.data("type") as string] ?? UNIFORM_EDGE_COLOR,
            "target-arrow-color": (ele: cytoscape.EdgeSingular) =>
              (edgeColors ?? {})[ele.data("type") as string] ?? UNIFORM_EDGE_COLOR,
            "target-arrow-shape": "triangle",
            "arrow-scale": 1,
            "curve-style": "bezier",
            label: "data(label)",
            "font-size": 8,
            "font-family": "ui-monospace, monospace",
            color: "hsl(25, 15%, 38%)",
            "text-rotation": "autorotate" as unknown as number,
            "text-background-color": "hsl(40, 38%, 95%)",
            "text-background-opacity": 0.9,
            "text-background-padding": "1px",
            "text-margin-y": -4,
            "text-events": "no",
            "overlay-opacity": 0,
          } as cytoscape.Css.Edge,
        },
        {
          selector: "edge:selected",
          style: {
            width: 2.5,
            "line-color": (ele: cytoscape.EdgeSingular) =>
              (edgeColors ?? {})[ele.data("type") as string] ?? "hsl(25, 25%, 15%)",
            "target-arrow-color": (ele: cytoscape.EdgeSingular) =>
              (edgeColors ?? {})[ele.data("type") as string] ?? "hsl(25, 25%, 15%)",
          } as cytoscape.Css.Edge,
        },
        { selector: "edge.faded", style: { opacity: () => dimEdgesRef.current ? 0.15 : 1 } as cytoscape.Css.Edge },
        {
          selector: "edge.provenance-highlight",
          style: {
            width: 3,
            "line-color": "hsl(145, 63%, 42%)",
            "target-arrow-color": "hsl(145, 63%, 42%)",
            "z-index": 999,
          } as cytoscape.Css.Edge,
        },
        {
          selector: "edge.provenance-highlight-dim",
          style: { opacity: () => dimEdgesRef.current ? 0.2 : 1 } as cytoscape.Css.Edge,
        },
        { selector: 'edge.highlighted, edge:selected', style: { opacity: 1, width: 2.5, 'z-index': 1000 } as cytoscape.Css.Edge },
      ],
      layout: buildLayoutOptions(layoutName),
      autoungrabify: false,
      autounselectify: false,
      userZoomingEnabled: true,
      userPanningEnabled: true,
      boxSelectionEnabled: false,
      minZoom: 0.3,
      maxZoom: 2.5,
      wheelSensitivity: 0.2,
    });

    cyRef.current = cy;


    cy.on("tap", "node", (evt) => {
      const node = evt.target;
      const data = node.data();
      notifiedNodeRef.current = data.id;
      onSelectNodeRef.current(data as GenericNode);
      if (data.type === "Therapy") {
        setExpandedNodeId((prev) => prev === data.id ? undefined : data.id);
      } else {
        setExpandedNodeId(undefined);
      }
      activeNodeRef.current = data.id;
      applyGraphFocus(cy, data.id, provenanceRef.current, dimEdgesRef.current);
    });

    cy.on("tap", "edge", (evt) => {
      activeNodeRef.current = undefined;
      notifiedNodeRef.current = undefined;
      applyGraphFocus(cy, undefined, provenanceRef.current, dimEdgesRef.current);
      const e = evt.target.data();
      const currentEdges = edgesRef.current;
      const original = currentEdges.find((x) => x.id === e.id);
      if (original) onSelectEdgeRef.current(original);
    });

    cy.on("tap", (evt) => {
      if (evt.target === cy) {
        activeNodeRef.current = undefined;
        notifiedNodeRef.current = undefined;
        applyGraphFocus(cy, undefined, provenanceRef.current, dimEdgesRef.current);
        cy.$(":selected").unselect();
        onBackgroundClickRef.current();
        setExpandedNodeId(undefined);
      }
    });

    cy.on("mouseover", "node", (evt) => {
      const n = evt.target;
      const pos = n.renderedPosition();
      setHover({ x: pos.x, y: pos.y, label: n.data("label"), sub: (n.data("type")) });
    });
    cy.on("mouseout", "node", () => setHover(null));
    cy.on("drag pan zoom", () => setHover(null));

    return () => {
      cy.destroy();
      cyRef.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    const cy = cyRef.current;
    if (!cy) return;

    const existingPositions = new Map<string, { x: number; y: number }>();
    cy.nodes().forEach((n) => {
      existingPositions.set(n.id(), { ...n.position() });
    });

    const incoming = new Set(elements.map(element => String(element.data.id)));
    cy.batch(() => {
      cy.elements().filter(element => !incoming.has(element.id())).remove();
      for (const element of elements) {
        const current = cy.getElementById(String(element.data.id));
        if (current.length) current.data(element.data);
        else cy.add(element);
      }
    });
    if (existingPositions.size === 0) cy.layout(buildLayoutOptions(layoutName)).run();
    else {
      let index = 0;
      cy.nodes().forEach(node => {
        const previous = existingPositions.get(node.id());
        if (previous) { node.position(previous); return; }
        const anchor = node.neighborhood('node').filter(other => existingPositions.has(other.id())).first();
        const center = anchor.length ? existingPositions.get(anchor.id())! : {x: 0, y: 0};
        const angle = index++ * 2.399963;
        const radius = 60 + 18 * Math.sqrt(index);
        node.position({x:center.x+Math.cos(angle)*radius,y:center.y+Math.sin(angle)*radius});
      });
    }
  }, [elements, layoutName]);

  // Auto-fit when a node is expanded
  useEffect(() => {
    const cy = cyRef.current;
    if (!cy) return;


    if (expandedNodeId) {
      const visibleElements = cy.elements(":visible");
      if (visibleElements.length > 0) {
        cy.fit(visibleElements, 50);
      }
    }
  }, [expandedNodeId]);

  useEffect(() => {
    activeNodeRef.current = highlightedNodeId;
  }, [highlightedNodeId]);
  useEffect(() => {
    const cy = cyRef.current;
    if (!cy) return;
    applyGraphFocus(cy, activeNodeRef.current, provenanceRef.current, dimEdgesRef.current);
    const node = activeNodeRef.current ? cy.getElementById(activeNodeRef.current) : null;
    if (node?.length) {
      const original = nodesRef.current.find(n => n.id === node.id());
      if (original && notifiedNodeRef.current !== original.id) {
        notifiedNodeRef.current = original.id;
        onSelectNodeRef.current(original);
      }
      cy.animate({ center: { eles: node }, duration: 250 });
    }
  }, [highlightedNodeId, elements]);

  // Update styles when colors change
  useEffect(() => {
    const cy = cyRef.current;
    if (!cy) return;

    cy.style()
      .selector("node")
      .style({
        "background-color": (ele: cytoscape.NodeSingular) =>
          nodeColors[ele.data("type") as string] ?? "hsl(25, 25%, 35%)",
      })
      .selector("edge")
      .style({
        "line-color": (ele: cytoscape.EdgeSingular) =>
          (edgeColors ?? {})[ele.data("type") as string] ?? "hsl(25, 10%, 80%)",
        "target-arrow-color": (ele: cytoscape.EdgeSingular) =>
          (edgeColors ?? {})[ele.data("type") as string] ?? "hsl(25, 10%, 80%)",
      })
      .selector("edge:selected")
      .style({
        "line-color": (ele: cytoscape.EdgeSingular) =>
          (edgeColors ?? {})[ele.data("type") as string] ?? "hsl(25, 25%, 15%)",
        "target-arrow-color": (ele: cytoscape.EdgeSingular) =>
          (edgeColors ?? {})[ele.data("type") as string] ?? "hsl(25, 25%, 15%)",
      })
      .update();
  }, [nodeColors, edgeColors]);

  // Highlight edges that belong to provenance paths
  useEffect(() => {
    const cy = cyRef.current;
    if (!cy) return;

    applyGraphFocus(cy, activeNodeRef.current, provenancePaths, dimEdges);
  }, [provenancePaths, elements, dimEdges]);

  // Handle container resize to update Cytoscape canvas
  useEffect(() => {
    const cy = cyRef.current;
    if (!cy || !containerRef.current) return;

    const resizeObserver = new ResizeObserver(() => {
      cy.resize();
    });

    resizeObserver.observe(containerRef.current);
    return () => resizeObserver.disconnect();
  }, []);

  const handleZoomIn = () => {
    const cy = cyRef.current;
    if (!cy) return;
    cy.zoom({ level: Math.min(cy.zoom() * 1.25, 2.5), renderedPosition: { x: cy.width() / 2, y: cy.height() / 2 } });
  };
  const handleZoomOut = () => {
    const cy = cyRef.current;
    if (!cy) return;
    cy.zoom({ level: Math.max(cy.zoom() / 1.25, 0.3), renderedPosition: { x: cy.width() / 2, y: cy.height() / 2 } });
  };
  const handleFit = () => {
    const cy = cyRef.current;
    if (!cy) return;
    cy.fit(cy.elements(":visible"), 40);
  };

  const applyLayout = (next: GraphLayoutKey) => {
    setLayoutName(next);
    const cy = cyRef.current;
    if (!cy) return;
    cy.layout(buildLayoutOptions(next)).run();
  };

  return (
    <div className="relative h-full w-full">
      {/* Cytoscape injects an unlayered position: relative rule; preserve the renderer's fill geometry. */}
      <div ref={containerRef} className="absolute inset-0" style={{ position: "absolute" }} />

      {/* Controls — top right */}
      <div className="absolute top-3 right-3 z-10 flex items-center gap-2">
        <select
          value={layoutName}
          onChange={(e) => applyLayout(e.target.value as GraphLayoutKey)}
          aria-label="Graph layout"
          className="h-8 rounded-md border border-border/70 bg-background/90 backdrop-blur px-2 text-[11px] font-medium text-foreground outline-none focus:border-herbal/60 focus:ring-2 focus:ring-herbal/15 transition-smooth cursor-pointer"
        >
          {LAYOUT_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
        <div className="inline-flex items-center rounded-md border border-border/70 bg-background/90 backdrop-blur shadow-sm overflow-hidden">
          <CtrlBtn label="Zoom in" onClick={handleZoomIn}>
            <ZoomIn className="h-3.5 w-3.5" />
          </CtrlBtn>
          <CtrlBtn label="Zoom out" onClick={handleZoomOut} divider>
            <ZoomOut className="h-3.5 w-3.5" />
          </CtrlBtn>
          <CtrlBtn label="Fit to screen" onClick={handleFit} divider>
            <Maximize2 className="h-3.5 w-3.5" />
          </CtrlBtn>
        </div>
      </div>

      {hover && (
        <div
          className="pointer-events-none absolute z-10 px-2 py-1 rounded-md bg-popover border border-border shadow-card text-[11px]"
          style={{ left: hover.x + 14, top: hover.y - 8 }}
        >
          <div className="font-semibold text-foreground">{hover.label}</div>
          <div className="text-[10px] text-muted-foreground">{hover.sub}</div>
        </div>
      )}
    </div>
  );
};

const CtrlBtn = ({
  children,
  onClick,
  label,
  divider,
}: {
  children: React.ReactNode;
  onClick: () => void;
  label: string;
  divider?: boolean;
}) => (
  <button
    type="button"
    title={label}
    aria-label={label}
    onClick={onClick}
    className={cn(
      "h-8 w-8 inline-flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-secondary/70 transition-smooth",
      divider && "border-l border-border/70",
    )}
  >
    {children}
  </button>
);
