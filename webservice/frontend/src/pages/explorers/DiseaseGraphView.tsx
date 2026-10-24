import { GraphStatus } from '@/components/explorer/GraphStatus';
import { useProgressiveGraph } from '@/hooks/useProgressiveGraph';
import { uiLabels } from '@/data/uiLabels';
import { useGraphFilters, useGraphEntities, useGraphVisibility } from '@/hooks/useGraphEntities';
import { Suspense, useCallback, useEffect, useMemo, useRef, useState, ComponentType } from "react";
import { NavLink, useSearchParams } from "react-router-dom";
import {
  Home,
  Network,
  RotateCcw,
  Eye,
  EyeOff,
  Filter,
  Maximize2,
  Minimize2,
  Search,
  BookOpen,
  Table as TableIcon,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { toast } from "sonner";
import { NODE_TYPE_LABEL, NODE_COLORS, EDGE_TYPE_LABEL, EDGE_COLORS, NodeType, EdgeType, GraphNode, GraphEdge } from "@/data/diseaseGraph";
import { MultiAutocomplete, Option } from "@/components/explorer/MultiAutocomplete";
import { DetailsPanel } from "@/components/explorer/DetailsPanel";
import { ProvenancePanel } from "@/components/explorer/ProvenancePanel";
import { useProvenanceHighlighting } from "@/components/explorer/GraphProvenance";
import { ExportMenu } from "@/components/explorer/ExportMenu";
import { useColorblind } from "@/hooks/useColorblind";
import { exportGraphCSV, exportGraphJSON, exportGraphPNG } from "@/lib/graphExport";
import { searchApi, parseGraphParams } from "@/lib/api";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";




const DISEASE_EDGE_PROPS: Record<string, { label: string; key: string }[]> = {
  USES_FORMULATION: [
    { label: "Adjuvant", key: "adjuvant" },
    { label: "Dose", key: "dose" },
    { label: "Duration", key: "duration" },
  ],
  ASSESS: [{ label: "Output Measure", key: "outputMeasure" }],
};

const subNav: { to: string; labelKey: string; icon: ComponentType<{ className?: string }>; end: boolean }[] = [
  { to: "/explorer/disease", labelKey: "home", icon: Home, end: true },
  { to: "/explorer/disease/graph", labelKey: "graphView", icon: Network, end: true },
  { to: "/explorer/disease/table", labelKey: "tableView", icon: TableIcon, end: true },
];

const ALL_NODE_TYPES: NodeType[] = ["Disease", "AyurvedicDiagnosis", "Therapy", "Formulation", "Symptom", "AssessmentParameter", "Dosha"];
const ALL_EDGE_TYPES: EdgeType[] = ["HAS_SYMPTOM", "HAS_AYURVEDIC_DIAGNOSIS", "HAS_DOSHA_IMBALANCE", "TREATED_BY", "PRETREATED_BY", "POSTTREATED_BY", "ASSESS", "USES_FORMULATION"];
const GRAPH_DEPTH = 1;
const GRAPH_NODE_LIMIT = 120;
const GRAPH_EDGE_LIMIT = 300;
import { GraphCanvas } from "@/components/explorer/GraphCanvas";

const FILTER_GROUPS: { key: NodeType; label: string; accent: string }[] = [
  { key: "Disease", label: "Disease", accent: "bg-[var(--brown-red)]" },
  { key: "Symptom", label: "Symptoms", accent: "bg-[var(--royal-gold)]" },
  { key: "AyurvedicDiagnosis", label: "Ayurvedic Diagnosis", accent: "bg-[var(--bark)]" },
  { key: "Dosha", label: "Dosha", accent: "bg-[var(--deep-walnut)]" },
  { key: "Therapy", label: "Therapy", accent: "bg-[var(--muted-teal)]" },
  { key: "Formulation", label: "Formulation", accent: "bg-[var(--pink-orchid)]" },
  { key: "AssessmentParameter", label: "Assessment Parameter", accent: "bg-[var(--yale-blue)]" },
];

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

const DiseaseGraphView = () => {
  
  const [searchParams] = useSearchParams();
  const { getPalette } = useColorblind();
  const reactiveNodeColors = useMemo(() => getPalette(NODE_COLORS), [getPalette]);
  const parsed = parseGraphParams(searchParams);

  const { filters, setFilters, errors: entityErrors, pending: entitiesPending } = useGraphFilters(ALL_NODE_TYPES);
  const [visibleTypes, setVisibleTypes] = useGraphVisibility('hideTypes', ALL_NODE_TYPES);
  const [visibleEdges, setVisibleEdges] = useGraphVisibility('hideEdges', ALL_EDGE_TYPES);
  const [highlightId, setHighlightId] = useState<string | undefined>(parsed.seedIds[0]);
  const [focused, setFocused] = useState(false);

  const [selectedNode, setSelectedNode] = useState<GraphNode | undefined>();
  const [selectedEdge, setSelectedEdge] = useState<{ edge: GraphEdge; source: GraphNode; target: GraphNode } | undefined>();

  // Provenance highlighting - fetch provenance data when a Disease node is selected
  const selectedDiseaseId = selectedNode?.type === "Disease" ? selectedNode.id : null;
  const { provenanceEdgeIds } = useProvenanceHighlighting(selectedDiseaseId);



  const graphContainerRef = useRef<HTMLDivElement>(null);

  const hasSelection = Object.values(filters).some((arr) => arr.length > 0);
  const seedIds = Object.values(filters).flat();
  const selectedEntities = useGraphEntities(seedIds);
  const firstSeed = seedIds[0];
  useEffect(() => { setHighlightId(firstSeed); setSelectedNode(undefined); setSelectedEdge(undefined); }, [firstSeed]);

  const { data: queryGraphData, isLoading, isFetching, isError, controls: graphControls } = useProgressiveGraph("disease", seedIds, GRAPH_DEPTH, GRAPH_NODE_LIMIT, GRAPH_EDGE_LIMIT, '1hop', visibleTypes, visibleEdges);
  const graphData = queryGraphData;

  const searchNodes = useCallback(async (q: string, type: NodeType): Promise<Option[]> => {
    if (!q.trim()) return [];
    const results = await searchApi("disease", q, 8, type);
    return results.map((n) => ({ id: n.id, label: n.label }));
  }, []);

  const { displayNodes, displayEdges } = useMemo(() => {
    if (!hasSelection) return { displayNodes: [] as GraphNode[], displayEdges: [] as GraphEdge[] };
    const apiNodes = graphData?.nodes ?? [];
    const apiEdges = graphData?.edges ?? [];
    const dn = apiNodes
      .filter((n) => visibleTypes.has(n.type as NodeType))
      .map((n) => ({
        ...n,
        type: n.type as NodeType,
        description: n.description ?? "",
      }))
      ;
    const visIds = new Set(dn.map((n) => n.id));
    const de = apiEdges
      .filter((e) => visIds.has(e.source) && visIds.has(e.target) && visibleEdges.has(e.type as EdgeType))
      .map((e) => ({
        ...e,
        source: e.source,
        target: e.target,
        type: e.type as EdgeType,
      }))
      ;
    return { displayNodes: dn, displayEdges: de };
  }, [hasSelection, graphData, visibleTypes, visibleEdges]);
  const graphIsPending = hasSelection && (isLoading || (isFetching && displayNodes.length === 0));
  const translatedNodes = displayNodes;
  const translatedEdges = displayEdges;

  const setFilterFor = (t: NodeType, ids: string[]) => {
    const prev = filters[t];
    const added = ids.filter((id) => !prev.includes(id));
    setFilters((f) => ({ ...f, [t]: ids }));
    if (added.length) setHighlightId(added[added.length - 1]);
  };

  const toggleType = (t: NodeType) => {
    setVisibleTypes((s) => {
      const n = new Set(s);
      if (n.has(t)) n.delete(t);
      else n.add(t);
      return n;
    });
  };

  const reset = () => {
    setFilters({ Disease: [], Symptom: [], AyurvedicDiagnosis: [], Dosha: [], Therapy: [], Formulation: [], AssessmentParameter: [] });
    setVisibleTypes(new Set(ALL_NODE_TYPES));
    setVisibleEdges(new Set(ALL_EDGE_TYPES));
    setSelectedNode(undefined);
    setSelectedEdge(undefined);
    setHighlightId(undefined);
    toast.success("Filters reset");
  };

  const exportPrefix = "shodhana-disease-graph";
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

  const handleSelectNode = (n: GraphNode) => {
    setSelectedNode(n);
    setSelectedEdge(undefined);
  };
  const handleSelectEdge = (e: GraphEdge) => {
    // Use original displayNodes for source/target lookup (IDs are unchanged)
    const s = displayNodes.find((x) => x.id === e.source);
    const t = displayNodes.find((x) => x.id === e.target);
    // Use translated versions for display
    const sT = translatedNodes.find((x) => x.id === e.source);
    const tT = translatedNodes.find((x) => x.id === e.target);
    if (s && t) {
      setSelectedEdge({ edge: e, source: (sT ?? s) as GraphNode, target: (tT ?? t) as GraphNode });
      setSelectedNode(undefined);
    }
  };

  return (
    <section className="container py-6 md:py-8">
      {entitiesPending && <p role="status">Loading selected entities…</p>}
      {entityErrors.length > 0 && <p role="alert">{entityErrors[0]}</p>}
      {/* Sub-nav */}
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
        {/* LEFT SIDEBAR */}
        {!focused && (
        <aside className="order-2 lg:order-1 rounded-xl border border-border bg-card shadow-card overflow-hidden flex flex-col h-full">
          <div className="px-4 py-3 border-b border-border/70 flex items-center justify-between gap-2 shrink-0">
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

          {/* Selected filters chip display */}
          <div className="px-4 py-3 border-b border-border/70 bg-card/40">
            <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-2">
              {"Selected filters"}
            </div>
            {hasSelection ? (
              <div className="flex flex-wrap gap-1.5">
                {(Object.keys(filters) as NodeType[]).flatMap((tKey) =>
                  filters[tKey].map((id) => {
                    const graphNode = graphData?.nodes.find((n) => n.id === id);
                    const label = selectedEntities[id]?.node?.label ?? graphNode?.label ?? (selectedEntities[id]?.error ? 'Entity unavailable' : 'Loading entity…');
                    // Reactively translate node labels if possible in glossary (e.g. classical terms)
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

          {/* Node visibility */}
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
          <div className="overflow-y-auto p-4 space-y-4 flex-1 min-h-0">
            {FILTER_GROUPS.map((g) => (
              <MultiAutocomplete
                key={g.key}
                label={(g.key)}
                options={[]}
                selected={filters[g.key] ?? []}
                onChange={(ids) => setFilterFor(g.key, ids)}
                placeholder={`Select ${g.label.toLowerCase()}…`}
                accentClass={g.accent}
                accentColor={reactiveNodeColors[g.key]}
                onSearch={(q) => searchNodes(q, g.key)}
              />
            ))}
          </div>
        </aside>
        )}

        {/* CENTER: Graph */}
        <div ref={graphContainerRef} className="order-1 lg:order-2 rounded-xl border border-border bg-card shadow-card flex flex-col h-full">
          {graphControls}
          {hasSelection && (
            <div className="shrink-0 flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 border-b border-border/60 text-[11px] text-muted-foreground">
              {FILTER_GROUPS.map((g) => (
                <span key={g.key} className="flex items-center gap-1.5">
                  <span className="h-2 w-2 rounded-full shrink-0" style={{ background: reactiveNodeColors[g.key] }} />
                  {(g.key)}
                </span>
              ))}
            </div>
          )}
          <div className="relative flex-1 min-h-0">
          {hasSelection ? (
            <>
              <div className="absolute top-3 left-3 z-10 px-2.5 py-1 rounded-md bg-background/90 backdrop-blur border border-border/60 text-[11px] text-muted-foreground flex items-center gap-2">
                <GraphStatus nodes={displayNodes.length} edges={displayEdges.length} meta={graphData?.meta} loading={isFetching} error={isError} />
              </div>
              {isError ? (
                <GraphLoadingState message={"Unable to load graph. Try another filter or reset."} />
              ) : graphIsPending ? (
                <GraphLoadingState />
              ) : (
                <Suspense fallback={<GraphLoadingState message={"Preparing graph renderer…"} />}>
                  <GraphCanvas dimEdges={false}
                    nodes={translatedNodes}
                    edges={translatedEdges}
                    nodeColors={reactiveNodeColors}
                    edgeColors={EDGE_COLORS}
                    onSelectNode={(n) => handleSelectNode(n as GraphNode)}
                    onSelectEdge={handleSelectEdge}
                    onBackgroundClick={() => {
                      setSelectedNode(undefined);
                      setSelectedEdge(undefined);
                                      }}
                    highlightedNodeId={highlightId}
                    provenancePaths={provenanceEdgeIds}
                  />
                </Suspense>
              )}
            </>
          ) : (
            <div className="h-full w-full flex items-center justify-center p-8">
              <div className="max-w-md text-center space-y-3">
                <div className="mx-auto h-12 w-12 rounded-full bg-secondary/70 flex items-center justify-center">
                  <Search className="h-5 w-5 text-muted-foreground" />
                </div>
                <h3 className="font-display text-base font-semibold text-foreground">
                  {"Start exploring the disease graph"}
                </h3>
                <p className="text-xs text-muted-foreground leading-relaxed mb-4">
                  {"Pick a disease, symptom, or any entity from the left panel to load its connected subgraph here."}
                </p>
                <div className="flex flex-wrap items-center justify-center gap-2 pt-2">
                  <span className="text-xs font-medium text-muted-foreground mr-1">
                    {"Quick Start:"}
                  </span>
                  <button 
                    onClick={() => setFilterFor("Disease", [...filters["Disease"], "785"])}
                    className="inline-flex items-center rounded-full border border-terracotta/20 bg-terracotta/5 px-2.5 py-0.5 text-xs font-semibold text-terracotta transition-colors hover:bg-terracotta/10"
                  >
                    Rheumatoid Arthritis
                  </button>
                  <button 
                    onClick={() => setFilterFor("Disease", [...filters["Disease"], "702"])}
                    className="inline-flex items-center rounded-full border border-terracotta/20 bg-terracotta/5 px-2.5 py-0.5 text-xs font-semibold text-terracotta transition-colors hover:bg-terracotta/10"
                  >
                    Acute Prolapsed Disc
                  </button>
                  <button 
                    onClick={() => setFilterFor("Formulation", [...filters["Formulation"], "2103"])}
                    className="inline-flex items-center rounded-full border border-stone-400/20 bg-stone-500/5 px-2.5 py-0.5 text-xs font-semibold text-stone-600 transition-colors hover:bg-stone-500/10"
                  >
                    Arogyavardini Vati
                  </button>
                </div>
              </div>
            </div>
          )}
          </div>
        </div>

        {/* RIGHT SIDEBAR */}
        {!focused && (
        <aside className="order-3 rounded-xl border border-border bg-card shadow-card flex flex-col h-full min-h-0 overflow-hidden">
          {selectedNode?.type === "Disease" ? (
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
                    selectedNode={selectedNode}
                    selectedEdge={selectedEdge}
                    nodeTypeLabels={NODE_TYPE_LABEL}
                    edgeTypeLabels={EDGE_TYPE_LABEL}
                    nodeColors={reactiveNodeColors}
                    edgeColors={EDGE_COLORS}
                    edgePropertyMap={DISEASE_EDGE_PROPS}
                  />
                </TabsContent>

                <TabsContent value="provenance" className="m-0 h-full overflow-y-auto overflow-x-hidden absolute inset-0">
                  <ProvenancePanel
                    diseaseId={selectedNode.id}
                    diseaseName={selectedNode.label}
                  />
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
                  selectedNode={selectedNode}
                  selectedEdge={selectedEdge}
                  nodeTypeLabels={NODE_TYPE_LABEL}
                  edgeTypeLabels={EDGE_TYPE_LABEL}
                  nodeColors={reactiveNodeColors}
                  edgeColors={EDGE_COLORS}
                  edgePropertyMap={DISEASE_EDGE_PROPS}
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

export default DiseaseGraphView;
