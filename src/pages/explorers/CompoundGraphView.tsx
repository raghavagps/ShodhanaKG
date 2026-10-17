import { GraphStatus } from '@/components/explorer/GraphStatus';
import { useProgressiveGraph } from '@/hooks/useProgressiveGraph';
import { uiLabels } from '@/data/uiLabels';
import { useGraphFilters, useGraphEntities, useGraphVisibility } from '@/hooks/useGraphEntities';
import { connectedGraph } from '@/lib/connectedGraph';
import { Suspense, useCallback, useEffect, useMemo, useRef, useState, ComponentType } from "react";
import { NavLink, useSearchParams } from "react-router-dom";
import {
  Home,
  Network,
  RotateCcw,
  Eye,
  EyeOff,
  Filter,
  Search,
  Maximize2,
  Minimize2,
  AlertTriangle,
  Table as TableIcon,
  BookOpen,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { toast } from "sonner";
import {
  C_NODE_TYPE_LABEL,
  C_NODE_COLORS,
  C_EDGE_TYPE_LABEL,
  C_EDGE_COLORS,
  CompoundNodeType,
  CompoundEdgeType,
  CompoundGraphNode,
  CompoundGraphEdge,
} from "@/data/compoundGraph";
import { MultiAutocomplete, Option } from "@/components/explorer/MultiAutocomplete";
import { DetailsPanel } from "@/components/explorer/DetailsPanel";
import { ExportMenu } from "@/components/explorer/ExportMenu";
import { useColorblind } from "@/hooks/useColorblind";
import { CompoundProvenancePanel } from "@/components/explorer/CompoundProvenancePanel";
import { useCompoundProvenanceHighlighting } from "@/components/explorer/GraphProvenance";
import { exportGraphCSV, exportGraphJSON, exportGraphPNG } from "@/lib/graphExport";
import { useGraph, searchApi, parseGraphParams } from "@/lib/api";

import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";



const subNav: { to: string; labelKey: string; icon: ComponentType<{ className?: string }>; end: boolean }[] = [
  { to: "/explorer/compound", labelKey: "home", icon: Home, end: true },
  { to: "/explorer/compound/graph", labelKey: "graphView", icon: Network, end: true },
  { to: "/explorer/compound/table", labelKey: "tableView", icon: TableIcon, end: true },
];

const ALL_NODE_TYPES: CompoundNodeType[] = [
  "Compound", "ShodhanaMethod", "ToxicSubstance", "PurifiedCompound", "ShodhanaType",
  "Media", "ConfirmationStudy", "Indication", "AyurvedicFormulation", "Process",
];
const ALL_EDGE_TYPES: CompoundEdgeType[] = [
  "UNDERGOES", "CONTAINS", "USES_MEDIA", "PRODUCES", "DETOXIFIES",
  "VALIDATED_BY", "CLASSIFIED_AS", "FOLLOWED_BY", "INVOLVES_PROCESS",
  "DERIVED_FROM", "USED_FOR_TREATMENT", "USED_IN_FORMULATION", "TREATS", "CONFIRMS_SAFETY_OF",
];

const FILTER_GROUPS: { key: CompoundNodeType; label: string; accent: string }[] = [
  { key: "Compound", label: "Compound", accent: "bg-[var(--c-herbal)]" },
  { key: "ShodhanaMethod", label: "Shodhana Method", accent: "bg-[var(--c-terracotta)]" },
  { key: "ToxicSubstance", label: "Toxic Substance", accent: "bg-[var(--c-toxic)]" },
  { key: "PurifiedCompound", label: "Purified Compound", accent: "bg-[var(--c-purified)]" },
  { key: "ShodhanaType", label: "Shodhana Type", accent: "bg-[var(--c-type)]" },
  { key: "Media", label: "Media", accent: "bg-[var(--c-media)]" },
  { key: "ConfirmationStudy", label: "Confirmation Study", accent: "bg-[var(--c-evidence)]" },
  { key: "Indication", label: "Indication", accent: "bg-[var(--brown-red)]" },
  { key: "AyurvedicFormulation", label: "Ayurvedic Formulation", accent: "bg-[var(--pink-orchid)]" },
  { key: "Process", label: "Process", accent: "bg-[var(--c-process)]" },
];

const COMPOUND_EDGE_PROPS: Record<string, { label: string; key: string }[]> = {
  UNDERGOES: [{ label: "Procedure", key: "procedure" }],
  USES_MEDIA: [{ label: "Procedure", key: "procedure" }],
  CLASSIFIED_AS: [{ label: "Media used", key: "mediaUsed" }],
  CONFIRMS_SAFETY_OF: [{ label: "Result", key: "result" }],
};

const GRAPH_NODE_LIMIT = 120;
const GRAPH_EDGE_LIMIT = 300;

const NODE_TYPE_TO_VERTEX: Record<CompoundNodeType, string> = {
  Compound: "Compound",
  ShodhanaMethod: "Shodhana_Method",
  ToxicSubstance: "Toxic_Substance",
  PurifiedCompound: "Purified_Compound",
  ShodhanaType: "Shodhana_Type",
  Media: "Media",
  ConfirmationStudy: "Confirmation_Study",
  Indication: "Indication",
  AyurvedicFormulation: "Ayurvedic_Formulation",
  Process: "Process",
};

import { GraphCanvas } from "@/components/explorer/GraphCanvas";

const GraphLoadingState = ({ message }: { message?: string }) => {
  
  const defaultMsg = "Loading graph…";
  return (
    <div className="absolute inset-0 z-20 flex items-center justify-center bg-card/75 backdrop-blur-sm">
      <div className="rounded-lg border border-border bg-background px-4 py-3 text-xs text-muted-foreground shadow-card">
        {message || defaultMsg}
      </div>
    </div>
  );
};

const initEmpty = (): Record<CompoundNodeType, string[]> =>
  ALL_NODE_TYPES.reduce((acc, t) => { acc[t] = []; return acc; }, {} as Record<CompoundNodeType, string[]>);

const CompoundGraphView = () => {
  
  const [searchParams, setSearchParams] = useSearchParams();
  const { getPalette } = useColorblind();
  const reactiveNodeColors = useMemo(() => getPalette(C_NODE_COLORS), [getPalette]);
  const parsed = parseGraphParams(searchParams);

  const { filters, setFilters, errors: entityErrors, pending: entitiesPending } = useGraphFilters(ALL_NODE_TYPES);
  const [visibleTypes, setVisibleTypes] = useGraphVisibility('hideTypes', ALL_NODE_TYPES);
  const [visibleEdges, setVisibleEdges] = useGraphVisibility('hideEdges', ALL_EDGE_TYPES);
  const [highlightId, setHighlightId] = useState<string | undefined>(parsed.seedIds[0]);
  const [focused, setFocused] = useState(false);

  const [selectedNode, setSelectedNode] = useState<CompoundGraphNode | undefined>();
  const [selectedEdge, setSelectedEdge] = useState<{ edge: CompoundGraphEdge; source: CompoundGraphNode; target: CompoundGraphNode } | undefined>();
  

  // Reset tab when selection changes




  const graphContainerRef = useRef<HTMLDivElement>(null);

  const hasSelection = Object.values(filters).some((arr) => arr.length > 0);
  // Filters are reconstructed each render. Stable values prevent translation
  // state updates from rebuilding the graph and interrupting a node drag.
  const seedKey = JSON.stringify(Object.values(filters).flat());
  const seedIds = useMemo<string[]>(() => JSON.parse(seedKey), [seedKey]);
  const selectedEntities = useGraphEntities(seedIds);
  const firstSeed = seedIds[0];
  useEffect(() => { setHighlightId(firstSeed); setSelectedNode(undefined); setSelectedEdge(undefined); }, [firstSeed]);

  const { data: graphData, isLoading, isFetching, isError, isPlaceholderData, controls: graphControls } = useProgressiveGraph("compound", seedIds, 1, GRAPH_NODE_LIMIT, GRAPH_EDGE_LIMIT, '1hop', visibleTypes, visibleEdges);

  const searchNodes = useCallback(async (q: string, type: CompoundNodeType): Promise<Option[]> => {
    if (!q.trim()) return [];
    const vertexType = NODE_TYPE_TO_VERTEX[type];
    const results = await searchApi("compound", q, 8, vertexType);
    return results.map((n) => ({ id: n.id, label: n.label }));
  }, []);

  const { displayNodes, displayEdges } = useMemo(() => {
    if (!hasSelection) return { displayNodes: [] as CompoundGraphNode[], displayEdges: [] as CompoundGraphEdge[] };
    const apiNodes = graphData?.nodes ?? [];
    const apiEdges = graphData?.edges ?? [];
    const dn = apiNodes
      .filter((n) => visibleTypes.has(n.type as CompoundNodeType))
      .map((n) => ({
        ...n,
        type: n.type as CompoundNodeType,
        description: n.description ?? "",
      }))
      ;
    const visIds = new Set(dn.map((n) => n.id));
    const de = apiEdges
      .filter((e) => visIds.has(e.source) && visIds.has(e.target) && visibleEdges.has(e.type as CompoundEdgeType))
      .map((e) => ({
        ...e,
        source: e.source,
        target: e.target,
        type: e.type as CompoundEdgeType,
      }))
      ;
    const connected = connectedGraph(dn, de, seedIds);
    return { displayNodes: connected.nodes, displayEdges: connected.edges };
  }, [hasSelection, graphData, visibleTypes, visibleEdges, seedIds, isPlaceholderData]);
  const graphIsPending = hasSelection && (isLoading || (isFetching && displayNodes.length === 0));
  const translatedNodes = displayNodes;
  const translatedEdges = displayEdges;

  const setFilterFor = (t: CompoundNodeType, ids: string[]) => {
    const prev = filters[t];
    const added = ids.filter((id) => !prev.includes(id));
    setFilters((f) => ({ ...f, [t]: ids }));
    if (added.length) setHighlightId(added[added.length - 1]);
  };

  const toggleType = (t: CompoundNodeType) => {
    setVisibleTypes((s) => {
      const n = new Set(s);
      if (n.has(t)) n.delete(t); else n.add(t);
      return n;
    });
  };

  const reset = () => {
    setFilters(initEmpty());
    setVisibleTypes(new Set(ALL_NODE_TYPES));
    setVisibleEdges(new Set(ALL_EDGE_TYPES));
    setSelectedNode(undefined);
    setSelectedEdge(undefined);
    setHighlightId(undefined);
    toast.success("Filters reset");
  };

  const exportPrefix = "shodhana-compound-graph";
  const handleExportJSON = () => {
    exportGraphJSON(displayNodes, displayEdges, exportPrefix);
    toast.success(`Exported ${displayNodes.length} nodes / ${displayEdges.length} edges as JSON`);
  };
  const handleExportCSV = () => {
    exportGraphCSV(displayNodes, displayEdges, exportPrefix);
    toast.success(`Exported ${displayNodes.length} nodes / ${displayEdges.length} edges as CSV`);
  };
  const handleExportPNG = () => {
    const ok = exportGraphPNG(graphContainerRef.current, exportPrefix);
    if (ok) toast.success("Graph exported as PNG");
    else toast.error("Nothing to export — load a graph first");
  };

  const handleSelectNode = (n: CompoundGraphNode) => {
    setSelectedNode(n);
    setSelectedEdge(undefined);
  };
  const handleSelectEdge = (e: CompoundGraphEdge) => {
    const s = displayNodes.find((x) => x.id === e.source);
    const t = displayNodes.find((x) => x.id === e.target);
    const sT = translatedNodes.find((x) => x.id === e.source);
    const tT = translatedNodes.find((x) => x.id === e.target);
    if (s && t) {
      setSelectedEdge({ edge: e, source: (sT ?? s) as CompoundGraphNode, target: (tT ?? t) as CompoundGraphNode });
      setSelectedNode(undefined);
    }
  };

  return (
    <section className="container py-6 md:py-8">
      {entitiesPending && <p role="status">Loading selected entities…</p>}
      {entityErrors.length > 0 && <p role="alert">{entityErrors[0]}</p>}
      <div className="mb-5 flex items-center justify-between gap-3 flex-wrap">
        <nav className="inline-flex items-center gap-1 rounded-full border border-border bg-card/70 p-1 shadow-sm">
          {subNav.map(({ to, labelKey, icon: Icon, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              className={({ isActive }) =>
                cn(
                  "inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium rounded-full transition-smooth",
                  isActive ? "bg-primary text-primary-foreground shadow-sm" : "text-muted-foreground hover:text-foreground hover:bg-secondary/70"
                )
              }
            >
              <Icon className="h-3.5 w-3.5" />
              {(uiLabels[labelKey] ?? labelKey)}
            </NavLink>
          ))}
        </nav>

        <div className="flex items-center gap-2">
          <button
            onClick={() => setFocused((f) => !f)}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-border bg-card text-xs font-medium text-foreground hover:bg-secondary hover:-translate-y-0.5 transition-all"
            title={focused ? "Exit focus" : "Focus graph"}
          >
            {focused ? <Minimize2 className="h-3.5 w-3.5" /> : <Maximize2 className="h-3.5 w-3.5" />}
            {focused ? "Exit focus" : "Focus graph"}
          </button>
          <ExportMenu
            onCSV={handleExportCSV}
            onJSON={handleExportJSON}
            onPNG={handleExportPNG}
            disabled={!hasSelection}
          />
          <button
            onClick={reset}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-border bg-card text-xs font-medium text-foreground hover:bg-secondary hover:-translate-y-0.5 transition-all"
          >
            <RotateCcw className="h-3.5 w-3.5" />
            {"Reset"}
          </button>
        </div>
      </div>

      <div className={cn("grid gap-4 items-stretch h-[calc(100vh-180px)] min-h-[560px] transition-all", focused ? "lg:grid-cols-1" : "lg:grid-cols-[300px_1fr_320px]")}>
        {/* LEFT */}
        {!focused && (
        <aside className="order-2 lg:order-1 rounded-xl border border-border bg-card shadow-card overflow-hidden flex flex-col h-full">
          <div className="px-4 py-3 border-b border-border/70 flex items-center justify-between gap-2">
            <div className="flex items-center gap-2">
              <Filter className="h-4 w-4 text-herbal" />
              <h2 className="font-display text-sm font-semibold">{"Filters"}</h2>
            </div>
            <button
              onClick={reset}
              className="inline-flex items-center gap-1 text-[10px] font-medium text-muted-foreground hover:text-foreground hover:-translate-y-0.5 transition-all"
              title="Reset all filters"
            >
              <RotateCcw className="h-3 w-3" />
              {"Reset"}
            </button>
          </div>

          {/* Pinned: selected filter chips */}
          <div className="px-4 py-3 border-b border-border/70 bg-card/40">
            <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-2">
              {"Selected filters"}
            </div>
            {hasSelection ? (
              <div className="flex flex-wrap gap-1.5 animate-fade-in">
                {(Object.keys(filters) as CompoundNodeType[]).flatMap((tKey) =>
                  filters[tKey].map((id) => {
                    const node = displayNodes.find((n) => n.id === id) ?? graphData?.nodes.find((n) => n.id === id);
                    const label = selectedEntities[id]?.node?.label ?? node?.label ?? (selectedEntities[id]?.error ? 'Entity unavailable' : 'Loading entity…');
                    const translatedLabel = (label);
                    return (
                      <span
                        key={`${tKey}-${id}`}
                        className="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium transition-smooth"
                        style={{
                          color: reactiveNodeColors[tKey],
                          borderColor: reactiveNodeColors[tKey],
                          backgroundColor: reactiveNodeColors[tKey].replace("hsl(", "hsla(").replace(")", ", 0.10)"),
                        }}
                      >
                        {translatedLabel}
                        <button
                          onClick={() => setFilterFor(tKey, filters[tKey].filter((x) => x !== id))}
                          className="opacity-70 hover:opacity-100"
                          aria-label={`Remove ${label}`}
                        >
                          ×
                        </button>
                      </span>
                    );
                  })
                )}
              </div>
            ) : (
              <p className="text-[11px] text-muted-foreground italic">{"No filters selected yet."}</p>
            )}
          </div>

          {/* Pinned: node visibility checklist */}
          <div className="px-4 py-3 border-b border-border/70 bg-card/60">
            <div className="flex items-center justify-between mb-2">
              <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                {"Node visibility"}
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => setVisibleTypes(new Set(ALL_NODE_TYPES))}
                  className="text-[10px] text-muted-foreground hover:text-foreground transition-colors"
                >
                  {"All"}
                </button>
                <button
                  onClick={() => setVisibleTypes(new Set())}
                  className="text-[10px] text-muted-foreground hover:text-foreground transition-colors"
                >
                  {"None"}
                </button>
              </div>
            </div>
            <div className="space-y-1.5 max-h-56 overflow-y-auto pr-1">
              {ALL_NODE_TYPES.map((tKey) => {
                const on = visibleTypes.has(tKey);
                return (
                  <label
                    key={tKey}
                    className="flex items-center gap-2 text-[11px] cursor-pointer hover:bg-secondary/50 rounded px-1 py-0.5 transition-smooth"
                  >
                    <input
                      type="checkbox"
                      checked={on}
                      onChange={() => toggleType(tKey)}
                      className="accent-[hsl(var(--herbal))]"
                    />
                    <span
                      className="h-2 w-2 rounded-full shrink-0"
                      style={{ background: reactiveNodeColors[tKey] }}
                    />
                    <span className={cn("flex-1", on ? "text-foreground" : "text-muted-foreground")}>
                      {(tKey)}
                    </span>
                    {on ? (
                      <Eye className="h-3 w-3 text-muted-foreground" />
                    ) : (
                      <EyeOff className="h-3 w-3 text-muted-foreground" />
                    )}
                  </label>
                );
              })}
            </div>
          </div>

          {/* Entity pickers */}
          <div className="overflow-y-auto p-4 space-y-4 flex-1">
            {FILTER_GROUPS.map((g) => (
              <MultiAutocomplete
                key={g.key}
                label={(g.key)}
                options={[]}
                selected={filters[g.key] ?? []}
                onChange={(ids) => setFilterFor(g.key, ids)}
                placeholder={`Select ${g.label.toLowerCase()}…`}
                accentClass={g.accent}
                onSearch={(q) => searchNodes(q, g.key)}
              />
            ))}
          </div>
        </aside>
        )}

        {/* CENTER */}
        <div ref={graphContainerRef} className="order-1 lg:order-2 rounded-xl border border-border bg-card shadow-card overflow-hidden relative h-full flex flex-col">
          {graphControls}
          {hasSelection ? (
            <>
              <div className="shrink-0 flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 border-b border-border/60 text-[11px] text-muted-foreground">
                {FILTER_GROUPS.map((g) => (
                  <span key={g.key} className="flex items-center gap-1.5">
                    <span className="h-2 w-2 rounded-full shrink-0" style={{ background: reactiveNodeColors[g.key] }} />
                    {(g.key)}
                  </span>
                ))}
              </div>
              <div className="relative flex-1 min-h-0">
                <div className="absolute top-3 left-3 z-10 px-2.5 py-1 rounded-md bg-background/90 backdrop-blur border border-border/60 text-[11px] text-muted-foreground flex items-center gap-2">
                  <GraphStatus nodes={displayNodes.length} edges={displayEdges.length} meta={graphData?.meta} loading={isFetching} error={isError} />
                </div>
                {isError ? (
                  <GraphLoadingState message={"Unable to load graph. Try another filter or reset."} />
                ) : graphIsPending ? (
                  <GraphLoadingState />
                ) : (
                  <Suspense fallback={<GraphLoadingState message={"Preparing graph renderer…"} />}>
                    <GraphCanvas
                      nodes={translatedNodes}
                      edges={translatedEdges}
                      nodeColors={reactiveNodeColors as unknown as Record<string, string>}
                      edgeColors={C_EDGE_COLORS as unknown as Record<string, string>}
                      onSelectNode={(n) => handleSelectNode(n as CompoundGraphNode)}
                      onSelectEdge={(e) => handleSelectEdge(e as CompoundGraphEdge)}
                      onBackgroundClick={() => { setSelectedNode(undefined); setSelectedEdge(undefined); }}
                      highlightedNodeId={highlightId}
                    />
                  </Suspense>
                )}

              </div>
            </>
          ) : (
            <div className="h-full w-full flex items-center justify-center p-8">
              <div className="max-w-md text-center space-y-3 animate-fade-in">
                <div className="mx-auto h-12 w-12 rounded-full bg-secondary/70 flex items-center justify-center">
                  <Search className="h-5 w-5 text-muted-foreground" />
                </div>
                <h3 className="font-display text-base font-semibold text-foreground">
                  {"Start exploring the compound graph"}
                </h3>
                  <p className="text-xs text-muted-foreground leading-relaxed mb-4">
                    {"Pick a compound, shodhana method, purified compound, or any entity from the left panel to load its connected subgraph here."}
                  </p>
                  <div className="flex flex-wrap items-center justify-center gap-2 pt-2">
                    <span className="text-xs font-medium text-muted-foreground mr-1">
                      {"Quick Start:"}
                    </span>
                    <button 
                      onClick={() => setFilterFor("Compound", [...filters["Compound"], "2"])}
                      className="inline-flex items-center rounded-full border border-herbal/20 bg-herbal/5 px-2.5 py-0.5 text-xs font-semibold text-herbal transition-colors hover:bg-herbal/10"
                    >
                      Aconitum ferox
                    </button>
                    <button 
                      onClick={() => setFilterFor("Compound", [...filters["Compound"], "4"])}
                      className="inline-flex items-center rounded-full border border-herbal/20 bg-herbal/5 px-2.5 py-0.5 text-xs font-semibold text-herbal transition-colors hover:bg-herbal/10"
                    >
                      Strychnos nux-vomica
                    </button>
                    <button 
                      onClick={() => setFilterFor("Compound", [...filters["Compound"], "5"])}
                      className="inline-flex items-center rounded-full border border-herbal/20 bg-herbal/5 px-2.5 py-0.5 text-xs font-semibold text-herbal transition-colors hover:bg-herbal/10"
                    >
                      Semecarpus anacardium
                    </button>
                  </div>
                </div>
            </div>
          )}
        </div>

        {/* RIGHT */}
        {!focused && (
        <aside className="order-3 rounded-xl border border-border bg-card shadow-card flex flex-col h-full min-h-0 overflow-hidden">
            {selectedNode?.type === "Compound" ? (
              <Tabs defaultValue="properties" className="flex flex-col h-full min-h-0">
                <div className="px-4 py-3 border-b border-border/70 shrink-0">
                  <TabsList className="grid w-full grid-cols-2">
                    <TabsTrigger value="properties" className="text-xs">{"Properties"}</TabsTrigger>
                    <TabsTrigger value="provenance" className="text-xs gap-1.5">
                      <BookOpen className="h-3.5 w-3.5" />
                      {"Provenance"}
                    </TabsTrigger>
                  </TabsList>
                </div>

                <div className="flex-1 min-h-0 relative">
                  <TabsContent value="properties" className="m-0 h-full overflow-y-auto overflow-x-hidden absolute inset-0">
                    <DetailsPanel
                      hideDuplicateDescription
                      selectedNode={selectedNode}
                      selectedEdge={selectedEdge}
                      nodeTypeLabels={C_NODE_TYPE_LABEL as unknown as Record<string, string>}
                      edgeTypeLabels={C_EDGE_TYPE_LABEL as unknown as Record<string, string>}
                      nodeColors={reactiveNodeColors as unknown as Record<string, string>}
                      edgeColors={C_EDGE_COLORS as unknown as Record<string, string>}
                      edgePropertyMap={COMPOUND_EDGE_PROPS}
                    />
                  </TabsContent>

                  <TabsContent value="provenance" className="m-0 h-full overflow-y-auto overflow-x-hidden absolute inset-0">
                    <CompoundProvenancePanel compoundId={selectedNode.id} compoundName={selectedNode.label} />
                  </TabsContent>
                </div>
              </Tabs>
            ) : (
              <>
                <div className="px-4 py-3 border-b border-border/70 shrink-0">
                  <h2 className="font-display text-sm font-semibold">{"Properties"}</h2>
                </div>
                <div className="flex-1 min-h-0 relative">
                  <div className="h-full overflow-y-auto absolute inset-0">
                    <DetailsPanel
                      hideDuplicateDescription
                      selectedNode={selectedNode}
                      selectedEdge={selectedEdge}
                      nodeTypeLabels={C_NODE_TYPE_LABEL as unknown as Record<string, string>}
                      edgeTypeLabels={C_EDGE_TYPE_LABEL as unknown as Record<string, string>}
                      nodeColors={reactiveNodeColors as unknown as Record<string, string>}
                      edgeColors={C_EDGE_COLORS as unknown as Record<string, string>}
                      edgePropertyMap={COMPOUND_EDGE_PROPS}
                    />
                  </div>
                </div>
              </>
            )}
          </aside>
        )}
      </div>
    </section>
  );
};

export default CompoundGraphView;

