import { GraphStatus } from '@/components/explorer/GraphStatus';
import { useProgressiveGraph } from '@/hooks/useProgressiveGraph';
import { uiLabels } from '@/data/uiLabels';
import { useGraphEntities, useGraphFilters, useGraphVisibility } from '@/hooks/useGraphEntities';
import { parseGraphParams } from '@/lib/api';
import { Suspense, useEffect, useMemo, useState, KeyboardEvent, useRef, ComponentType } from "react";
import { NavLink, useSearchParams } from "react-router-dom";
import {
  Home,
  Network,
  RotateCcw,
  Filter,
  Search,
  Maximize2,
  Minimize2,
  Eye,
  EyeOff,
  Table as TableIcon,
  AlertTriangle,
  BookOpen,
  Compass,
  GitBranch,
  Info,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { toast } from "sonner";
import {
  I_NODE_TYPE_LABEL,
  I_NODE_COLORS,
  I_EDGE_TYPE_LABEL,
  I_EDGE_COLORS,
  IntegratedNodeType,
  IntegratedEdgeType,
  IntegratedGraphNode,
  IntegratedGraphEdge,
  ALL_INTEGRATED_NODE_TYPES,
  ALL_INTEGRATED_EDGE_TYPES,
  BRIDGE_EDGE_TYPES,
} from "@/data/integratedGraph";
import { DetailsPanel } from "@/components/explorer/DetailsPanel";
import { IntegratedPaths } from "@/components/explorer/IntegratedPaths";
import { ExportMenu } from "@/components/explorer/ExportMenu";
import { useColorblind } from "@/hooks/useColorblind";
import { exportGraphCSV, exportGraphJSON, exportGraphPNG } from "@/lib/graphExport";
import {
  useGraph, searchApi, type IntegratedMode,
} from "@/lib/api";

import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ProvenancePanel } from "@/components/explorer/ProvenancePanel";
import { CompoundProvenancePanel } from "@/components/explorer/CompoundProvenancePanel";



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

const subNav: { to: string; labelKey: string; icon: ComponentType<{ className?: string }>; end: boolean }[] = [
  { to: "/explorer/integrated", labelKey: "home", icon: Home, end: true },
  { to: "/explorer/integrated/graph", labelKey: "graphView", icon: Network, end: true },
  { to: "/explorer/integrated/table", labelKey: "tableView", icon: TableIcon, end: true },
];

// Quick presets and "Start from" tab were moved to the Integrated home page.



const EDGE_PROP_MAP: Record<string, { label: string; key: string }[]> = {
  UNDERGOES: [{ label: "Procedure", key: "procedure" }],
  USES_MEDIA: [{ label: "Procedure", key: "procedure" }],
  CLASSIFIED_AS: [{ label: "Media used", key: "mediaUsed" }],
  CONFIRMS_SAFETY_OF: [{ label: "Result", key: "result" }],
  USES_FORMULATION: [
    { label: "Adjuvant", key: "adjuvant" },
    { label: "Dose", key: "dose" },
    { label: "Duration", key: "duration" },
  ],
  ASSESS: [{ label: "Output Measure", key: "outputMeasure" }],
  USED_FOR_TREATMENT: [{ label: "Bridge Note", key: "bridgeNote" }],
  USED_IN_FORMULATION: [{ label: "Bridge Note", key: "bridgeNote" }],
};

const RECENT_KEY = "shodhana-integrated-recent";

const DISEASE_SIDE_TYPES: IntegratedNodeType[] = [
  "Disease", "AyurvedicDiagnosis", "Symptom", "Dosha", "Therapy", "Formulation", "AssessmentParameter",
];

const toSeedId = (raw: string) => {
  const s = raw.trim();
  if (!s) return "";
  if (/^#\d+:\d+$/.test(s)) return s;
  if (/^[dc]:/.test(s)) return s.slice(2);
  return s;
};

const mapApiNode = (n: { id: string; label: string; type: string; description?: string; [key: string]: unknown }): IntegratedGraphNode | null => {
  if (!(ALL_INTEGRATED_NODE_TYPES as string[]).includes(n.type)) return null;
  const t = n.type as IntegratedNodeType;
  const side: IntegratedGraphNode["side"] = t === "PurifiedCompound"
    ? "bridge"
    : DISEASE_SIDE_TYPES.includes(t)
      ? "disease"
      : "compound";
  return { ...n, id: n.id, label: n.label, type: t, description: n.description ?? "", side } as IntegratedGraphNode;
};

const mapApiEdge = (e: Record<string, unknown>): IntegratedGraphEdge | null => {
  const type = String(e.type ?? "");
  if (!(ALL_INTEGRATED_EDGE_TYPES as string[]).includes(type)) return null;
  const t = type as IntegratedEdgeType;
  const side: IntegratedGraphEdge["side"] = BRIDGE_EDGE_TYPES.includes(t)
    ? "bridge"
    : ["UNDERGOES", "CONTAINS", "USES_MEDIA", "PRODUCES", "DETOXIFIES", "VALIDATED_BY", "CLASSIFIED_AS", "FOLLOWED_BY", "DERIVED_FROM", "CONFIRMS_SAFETY_OF"].includes(t)
      ? "compound"
      : "disease";
  return {
    ...e,
    id: String(e.id ?? ""),
    source: String(e.source ?? ""),
    target: String(e.target ?? ""),
    type: t,
    side,
    references: e.references as import("@/lib/types").PublicationReference[],
    pubmedId: e.pubmedId ? String(e.pubmedId) : undefined,
    procedure: e.procedure ? String(e.procedure) : undefined,
    result: e.result ? String(e.result) : undefined,
    mediaUsed: e.mediaUsed ? String(e.mediaUsed) : undefined,
    outputMeasure: e.outputMeasure ? String(e.outputMeasure) : undefined,
    adjuvant: e.adjuvant ? String(e.adjuvant) : undefined,
    dose: e.dose ? String(e.dose) : undefined,
    duration: e.duration ? String(e.duration) : undefined,
    reference: e.reference ? String(e.reference) : undefined,
  };
};

const IntegratedGraphView = () => {
  
  const [searchParams, setSearchParams] = useSearchParams();
  const { getPalette } = useColorblind();
  const reactiveNodeColors = useMemo(() => getPalette(I_NODE_COLORS), [getPalette]);
  const { filters: resolvedFilters, errors: entityErrors, pending: entitiesPending } = useGraphFilters(ALL_INTEGRATED_NODE_TYPES);
  const resolvedKey = Object.values(resolvedFilters).flat().join(',');
  const seedIds = useMemo(() => resolvedKey ? resolvedKey.split(',') : [], [resolvedKey]);
  const firstSeed = seedIds[0];
  const selectedEntities = useGraphEntities(seedIds);
  const setSeedIds = (next: string[] | ((previous: string[]) => string[])) => {
    const value = typeof next === 'function' ? next(seedIds) : next;
    setSearchParams(previous => {
      const params = new URLSearchParams(previous);
      ['node', 'type', 'filter', 'seeds'].forEach(key => params.delete(key));
      if (value.length) params.set('seeds', value.join(','));
      return params;
    });
  };
  // integratedMode controls the traversal strategy passed to graph.php
  const requestedMode = searchParams.get('integratedMode');
  const integratedMode: IntegratedMode = ['paths', 'bridge', '1hop', '2hop'].includes(requestedMode ?? '') ? requestedMode as IntegratedMode : 'paths';
  const setIntegratedMode = (mode: IntegratedMode) => setSearchParams(previous => { const params = new URLSearchParams(previous); params.set('integratedMode', mode); return params; }, { replace: true });
  // Refinement filters removed — keep panel minimal as requested.

  const [visibleTypes, setVisibleTypes] = useGraphVisibility('hideTypes', ALL_INTEGRATED_NODE_TYPES);
  const [visibleEdges, setVisibleEdges] = useGraphVisibility('hideEdges', ALL_INTEGRATED_EDGE_TYPES);
  const [advancedOpen, setAdvancedOpen] = useState(false);
  const [highlightId, setHighlightId] = useState<string | undefined>(seedIds[0]);
  const [searchQuery, setSearchQuery] = useState("");
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [focused, setFocused] = useState(false);

  const [selectedNode, setSelectedNode] = useState<IntegratedGraphNode | undefined>();
  const [selectedEdge, setSelectedEdge] = useState<
    { edge: IntegratedGraphEdge; source: IntegratedGraphNode; target: IntegratedGraphNode } | undefined
  >();
  const [suggestions, setSuggestions] = useState<IntegratedGraphNode[]>([]);
  const [searchingSuggestions, setSearchingSuggestions] = useState(false);



  useEffect(() => { setHighlightId(firstSeed); setSelectedNode(undefined); setSelectedEdge(undefined); }, [firstSeed]);

  const { data: queryGraphData, isLoading, isFetching, isError, controls: graphControls } = useProgressiveGraph("integrated", seedIds, 1, 300, 900, integratedMode, visibleTypes, visibleEdges);
  const graphData = queryGraphData;

  const { displayNodes, displayEdges } = useMemo(() => {
    if (seedIds.length === 0) return { displayNodes: [] as IntegratedGraphNode[], displayEdges: [] as IntegratedGraphEdge[] };
    const visible = (graphData?.nodes ?? [])
      .map(mapApiNode)
      .filter((n): n is IntegratedGraphNode => Boolean(n))
      .filter((n) => visibleTypes.has(n.type));
    const ids = new Set(visible.map((n) => n.id));
    const visEdges = (graphData?.edges ?? [])
      .map((e) => mapApiEdge(e as Record<string, unknown>))
      .filter((e): e is IntegratedGraphEdge => Boolean(e))
      .filter((e) => ids.has(e.source) && ids.has(e.target) && visibleEdges.has(e.type));
    // Bridge shows relationships, so hidden/missing links must not leave floating nodes.
    const connectedIds = new Set(visEdges.flatMap((edge) => [edge.source, edge.target]));
    return {
      displayNodes: integratedMode === 'bridge' ? visible.filter((node) => connectedIds.has(node.id)) : visible,
      displayEdges: visEdges,
    };
  }, [seedIds, graphData, visibleTypes, visibleEdges, integratedMode]);

  useEffect(() => {
    if (integratedMode !== 'bridge') return;
    if (selectedNode && !displayNodes.some((node) => node.id === selectedNode.id)) setSelectedNode(undefined);
    if (selectedEdge && !displayEdges.some((edge) => edge.id === selectedEdge.edge.id)) setSelectedEdge(undefined);
  }, [integratedMode, displayNodes, displayEdges, selectedNode, selectedEdge]);
  const translatedNodes = displayNodes;
  const translatedEdges = displayEdges;

  useEffect(() => {
    const q = searchQuery.trim();
    if (!q) {
      setSuggestions([]);
      setSearchingSuggestions(false);
      return;
    }
    let active = true;
    const t = setTimeout(async () => {
      setSearchingSuggestions(true);
      try {
        const res = await searchApi("integrated", q, 8);
        if (!active) return;
        setSuggestions(
          res
            .map((n) => mapApiNode({ id: n.id, label: n.label, type: n.type, description: n.description }))
            .filter((n): n is IntegratedGraphNode => Boolean(n)),
        );
      } catch {
        if (active) setSuggestions([]);
      }
      if (active) setSearchingSuggestions(false);
    }, 250);
    return () => {
      active = false;
      clearTimeout(t);
    };
  }, [searchQuery]);

  const addSeed = (id: string) => {
    const node = suggestions.find((n) => n.id === id) ?? displayNodes.find((n) => n.id === id);
    if (!node) return;
    setSeedIds([toSeedId(id)]);
    setHighlightId(id);
    setSearchQuery("");
    setShowSuggestions(false);
  };

  // setStarter removed — "Start from" tab dropped per simplified panel spec.

  const reset = () => {
    setSeedIds([]);
    setIntegratedMode('paths');
    setVisibleTypes(new Set(ALL_INTEGRATED_NODE_TYPES));
    setVisibleEdges(new Set(ALL_INTEGRATED_EDGE_TYPES));
    setSelectedNode(undefined);
    setSelectedEdge(undefined);
    setHighlightId(undefined);
    setSearchQuery("");
    toast.success("Exploration reset");
  };

  const graphContainerRef = useRef<HTMLDivElement>(null);
  const exportPrefix = "shodhana-integrated-graph";
  const handleExportJSON = () => {
    exportGraphJSON(displayNodes, displayEdges, exportPrefix, { seedIds, integratedMode });
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

  const onSearchKey = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key !== "Enter") return;
    const q = searchQuery.trim();
    if (!q) return;
    const hit = suggestions[0];
    if (hit) {
      addSeed(hit.id);
      toast.success(`Added “${hit.label}” as a starting node`);
      setSearchQuery("");
    } else {
      toast.error("No matching entity found");
    }
  };

  const handleSelectNode = (n: IntegratedGraphNode) => {
    setSelectedNode(n);
    setSelectedEdge(undefined);
  };
  const handleSelectEdge = (e: IntegratedGraphEdge) => {
    const s = displayNodes.find((x) => x.id === e.source);
    const t = displayNodes.find((x) => x.id === e.target);
    const sT = translatedNodes.find((x) => x.id === e.source);
    const tT = translatedNodes.find((x) => x.id === e.target);
    if (s && t) {
      setSelectedEdge({ edge: e, source: (sT ?? s) as IntegratedGraphNode, target: (tT ?? t) as IntegratedGraphNode });
      setSelectedNode(undefined);
    }
  };

  const toggleType = (t: IntegratedNodeType) => {
    setVisibleTypes((s) => {
      const n = new Set(s);
      if (n.has(t)) n.delete(t); else n.add(t);
      return n;
    });
  };
  const toggleEdge = (t: IntegratedEdgeType) => {
    setVisibleEdges((s) => {
      const n = new Set(s);
      if (n.has(t)) n.delete(t); else n.add(t);
      return n;
    });
  };

  const hasStart = seedIds.length > 0;

  const connectedEntities = useMemo(() => {
    if (!selectedNode) return [] as IntegratedGraphNode[];
    const ids = new Set<string>();
    displayEdges.forEach((e) => {
      if (e.source === selectedNode.id) ids.add(e.target);
      if (e.target === selectedNode.id) ids.add(e.source);
    });
    return displayNodes.filter((n) => ids.has(n.id));
  }, [selectedNode, displayEdges, displayNodes]);

  const isBridgeNode = (n?: IntegratedGraphNode) => n?.type === "PurifiedCompound";
  const isBridgeEdge = (e?: IntegratedGraphEdge) => e && BRIDGE_EDGE_TYPES.includes(e.type);

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
                  isActive
                    ? "bg-primary text-primary-foreground shadow-sm"
                    : "text-muted-foreground hover:text-foreground hover:bg-secondary/70",
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
            disabled={!hasStart}
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

      {/* Top search bar — autocomplete over the integrated graph */}
      <div className="mb-4 relative">
        <div className="relative rounded-xl border border-border bg-card shadow-card focus-within:border-herbal/50 focus-within:shadow-elevated transition-smooth">
          <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <input
            value={searchQuery}
            onChange={(e) => {
              setSearchQuery(e.target.value);
              setShowSuggestions(true);
            }}
            onFocus={() => setShowSuggestions(true)}
            onBlur={() => setTimeout(() => setShowSuggestions(false), 150)}
            onKeyDown={onSearchKey}
            placeholder={"Search any node — disease, compound, purified compound, formulation, therapy…"}
            className="w-full bg-transparent rounded-xl pl-11 pr-4 py-3 text-sm outline-none placeholder:text-muted-foreground/80"
          />
        </div>
        {showSuggestions && suggestions.length > 0 && (
          <div className="absolute z-30 mt-1 left-0 right-0 rounded-xl border border-border bg-popover shadow-elevated max-h-72 overflow-y-auto">
            {suggestions.map((n) => (
              <button
                key={n.id}
                onMouseDown={(e) => {
                  e.preventDefault();
                  addSeed(n.id);
                }}
                className="w-full flex items-center gap-3 px-4 py-2 text-left hover:bg-secondary/60 transition-smooth"
              >
                <span
                  className="h-2 w-2 rounded-full shrink-0"
                  style={{ background: reactiveNodeColors[n.type] }}
                />
                <div className="flex-1 min-w-0">
                  <div className="text-xs font-medium text-foreground truncate">{(n.label)}</div>
                  <div className="text-[10px] uppercase tracking-wider text-muted-foreground">
                    {(n.type)}
                  </div>
                </div>
              </button>
            ))}
          </div>
        )}
        {showSuggestions && searchingSuggestions && suggestions.length === 0 && (
          <div className="absolute z-30 mt-1 left-0 right-0 rounded-xl border border-border bg-popover shadow-elevated px-4 py-2 text-xs text-muted-foreground">
            {"Searching…"}
          </div>
        )}
      </div>

      <div className={cn("grid gap-4 items-stretch h-[calc(100vh-240px)] min-h-[560px] transition-all", focused ? "lg:grid-cols-1" : "lg:grid-cols-[280px_1fr_340px]")}>
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

          {/* Pinned: selected starting nodes (chips) */}
          <div className="px-4 py-3 border-b border-border/70 bg-card/40">
            <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-2">
              {"Selected filters"}
            </div>
            {seedIds.length > 0 ? (
              <div className="flex flex-wrap gap-1.5 animate-fade-in">
                {seedIds.map((id) => {
                  const node = selectedEntities[id]?.node ?? displayNodes.find((n) => n.id === id) ?? suggestions.find((n) => n.id === id);
                  if (!node) return <span key={id} role="status">{selectedEntities[id]?.error ? 'Entity unavailable' : 'Loading entity…'}</span>;
                  return (
                    <span
                      key={id}
                      className="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium transition-smooth"
                      style={{
                        color: reactiveNodeColors[node.type],
                        borderColor: reactiveNodeColors[node.type],
                        backgroundColor: reactiveNodeColors[node.type].replace("hsl(", "hsla(").replace(")", ", 0.10)"),
                      }}
                    >
                      {(node.label)}
                      <button
                        onClick={() => setSeedIds((s) => s.filter((x) => x !== id))}
                        className="opacity-70 hover:opacity-100"
                        aria-label={`Remove ${node.label}`}
                      >
                        ×
                      </button>
                    </span>
                  );
                })}
              </div>
            ) : (
              <p className="text-[11px] text-muted-foreground italic">{"No starting node selected yet."}</p>
            )}
          </div>

          {graphControls}
          {/* Pinned: node visibility checklist */}
          <div className="px-4 py-3 border-b border-border/70 bg-card/60">
            <div className="flex items-center justify-between mb-2">
              <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                {"Node visibility"}
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => setVisibleTypes(new Set(ALL_INTEGRATED_NODE_TYPES))}
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
              {ALL_INTEGRATED_NODE_TYPES.map((tKey) => {
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

          {/* Scrollable: depth + relationship visibility */}
          <div className="overflow-y-auto p-4 space-y-4 flex-1">
            <div>
              <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                {"Path depth"}
              </div>
              <div className="flex gap-1">
                {([
                  { mode: 'paths' as IntegratedMode, label: 'Evidence paths', title: 'Verified paths through purification processes and separately cited treatment relationships' },
                  { mode: 'bridge' as IntegratedMode, label: "Bridge", title: 'Only PC → Disease treatment edge' },
                  { mode: '1hop'  as IntegratedMode, label: "1-hop",  title: 'Bridge + direct neighbours on each side' },
                ] as const).map((d) => (
                  <button
                    key={d.mode}
                    title={d.title}
                    onClick={() => setIntegratedMode(d.mode)}
                    className={cn(
                      "flex-1 px-2 py-1 rounded-md text-[10px] font-medium border transition-smooth",
                      integratedMode === d.mode
                        ? "bg-primary text-primary-foreground border-primary"
                        : "bg-background border-border text-muted-foreground hover:text-foreground",
                    )}
                  >
                    {d.label}
                  </button>
                ))}
              </div>
              <p className="mt-1.5 text-[10px] text-muted-foreground leading-relaxed">
                {(
                  <>
                    <strong>Bridge</strong> — only the treatment link (PC → Disease).<br />
                    <strong>1-hop</strong> — bridge + immediate neighbours on each side.
                  </>
                )}
              </p>
            </div>
          </div>
        </aside>
        )}

        <div ref={graphContainerRef} className="order-1 lg:order-2 rounded-xl border border-border bg-card shadow-card overflow-hidden relative h-full flex flex-col">
          {hasStart ? (
            <>
              <div className="shrink-0 flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 border-b border-border/60 text-[11px] text-muted-foreground">
                <GraphStatus nodes={displayNodes.length} edges={displayEdges.length} meta={graphData?.meta} loading={isFetching} error={isError} />
              </div>
              {integratedMode === 'paths' && graphData?.meta?.evidencePaths && <IntegratedPaths key={JSON.stringify(seedIds)} paths={graphData.meta.evidencePaths} continuations={graphData.meta.continuations} />}
              <div className="relative flex-1 min-h-0">
              {isError ? (
                <GraphLoadingState message={"Unable to load integrated graph. Try another filter or reset."} />
              ) : isLoading || (isFetching && displayNodes.length === 0) ? (
                <GraphLoadingState />
              ) : (
                <Suspense fallback={<GraphLoadingState message={"Preparing graph renderer…"} />}>
                  <GraphCanvas
                    nodes={translatedNodes}
                    edges={translatedEdges}
                    nodeColors={reactiveNodeColors as unknown as Record<string, string>}
                    edgeColors={I_EDGE_COLORS as unknown as Record<string, string>}
                    onSelectNode={(n) => handleSelectNode(n as IntegratedGraphNode)}
                    onSelectEdge={(e) => handleSelectEdge(e as IntegratedGraphEdge)}
                    onBackgroundClick={() => {
                      setSelectedNode(undefined);
                      setSelectedEdge(undefined);
                    }}
                    highlightedNodeId={highlightId}
                  />
                </Suspense>
              )}

              </div>
            </>
          ) : (
            <div className="h-full flex flex-col items-center justify-center text-center px-8 max-w-lg mx-auto">
              <span className="flex h-14 w-14 items-center justify-center rounded-full bg-secondary text-herbal mb-4">
                <Compass className="h-6 w-6" strokeWidth={1.6} />
              </span>
              <h3 className="font-display text-lg font-semibold mb-1.5">
                {"Explore the integrated knowledge graph"}
              </h3>
              <p className="text-sm text-muted-foreground leading-relaxed">
                {"Use the search bar above to find a node, choose a suggestion, and explore its connected disease-side and compound-side links."}
              </p>
              <ol className="mt-5 text-xs text-muted-foreground space-y-2 text-left">
                <li className="flex items-start gap-2">
                  <Search className="h-3.5 w-3.5 text-herbal mt-0.5 shrink-0" />
                  <span>
                    {"Type any disease, compound, purified compound, or formulation in the search bar."}
                  </span>
                </li>
                <li className="flex items-start gap-2">
                  <Compass className="h-3.5 w-3.5 text-herbal mt-0.5 shrink-0" />
                  <span>
                    {"Pick a suggestion from the autocomplete list."}
                  </span>
                </li>
                <li className="flex items-start gap-2">
                  <Network className="h-3.5 w-3.5 text-herbal mt-0.5 shrink-0" />
                  <span>
                    {"The graph focuses on that node and reveals its connected subgraph across both knowledge domains."}
                  </span>
                </li>
              </ol>

              <div className="mt-8 flex flex-wrap items-center justify-center gap-2">
                <span className="text-xs font-medium text-muted-foreground mr-1">
                  {"Quick Start:"}
                </span>
                <button 
                  onClick={() => { setSeedIds(["785"]); setHighlightId("785"); }}
                  className="inline-flex items-center rounded-full border border-terracotta/20 bg-terracotta/5 px-3 py-1 text-xs font-semibold text-terracotta transition-colors hover:bg-terracotta/10"
                >
                  Rheumatoid Arthritis
                </button>
                <button 
                  onClick={() => { setSeedIds(["220"]); setHighlightId("220"); }}
                  className="inline-flex items-center rounded-full border border-herbal/20 bg-herbal/5 px-3 py-1 text-xs font-semibold text-herbal transition-colors hover:bg-herbal/10"
                >
                  Purified Guggulu
                </button>
                <button 
                  onClick={() => { setSeedIds(["239"]); setHighlightId("239"); }}
                  className="inline-flex items-center rounded-full border border-stone-400/20 bg-stone-500/5 px-3 py-1 text-xs font-semibold text-stone-600 transition-colors hover:bg-stone-500/10"
                >
                  Naga Bhasma
                </button>
              </div>
            </div>
          )}
        </div>

        {!focused && (
        <aside className="order-3 rounded-xl border border-border bg-card shadow-card flex flex-col h-full min-h-0 overflow-hidden">
            {selectedNode?.type === "Disease" || selectedNode?.type === "Compound" ? (
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
                    {(isBridgeNode(selectedNode) || isBridgeEdge(selectedEdge?.edge)) && (
                      <div className="m-4 mb-0 rounded-md border border-terracotta/30 bg-terracotta/5 p-3">
                        <div className="flex items-center gap-1.5 mb-1.5">
                          <GitBranch className="h-3.5 w-3.5 text-terracotta" />
                          <span className="text-[10px] font-semibold uppercase tracking-wider text-terracotta">
                            {"Cross-domain bridge"}
                          </span>
                        </div>
                        <p className="text-[11px] text-foreground/80 leading-relaxed">
                          {selectedEdge?.edge.bridgeNote
                            ? String(selectedEdge.edge.bridgeNote)
                            : "Purified compounds are the conceptual bridge between the Compound-ShodhanaKG and the Disease-ShodhanaKG — they emerge from Shodhana of raw compounds and re-enter the disease side as ingredients of formulations and as direct treatments."}
                        </p>
                      </div>
                    )}
                    <DetailsPanel
                      selectedNode={selectedNode}
                      selectedEdge={selectedEdge}
                      nodeTypeLabels={I_NODE_TYPE_LABEL as unknown as Record<string, string>}
                      edgeTypeLabels={I_EDGE_TYPE_LABEL as unknown as Record<string, string>}
                      nodeColors={reactiveNodeColors as unknown as Record<string, string>}
                      edgeColors={I_EDGE_COLORS as unknown as Record<string, string>}
                      edgePropertyMap={EDGE_PROP_MAP}
                    />
                    {selectedNode && connectedEntities.length > 0 && (
                      <div className="px-5 pb-5 space-y-3">
                        <div>
                          <div className="text-[10px] uppercase tracking-wider text-muted-foreground mb-1.5 flex items-center gap-1">
                            <Info className="h-3 w-3" />
                            {"Connected entities"} ({connectedEntities.length})
                          </div>
                          <div className="flex flex-wrap gap-1">
                            {connectedEntities.slice(0, 12).map((n) => (
                              <button
                                key={n.id}
                                onClick={() => addSeed(n.id)}
                                title={`${I_NODE_TYPE_LABEL[n.type]} — click to add as starting node`}
                                className="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-md bg-secondary text-foreground/80 hover:bg-secondary/80"
                              >
                                <span
                                  className="h-1.5 w-1.5 rounded-full"
                                  style={{ background: reactiveNodeColors[n.type] }}
                                />
                                {(n.label)}
                              </button>
                            ))}
                            {connectedEntities.length > 12 && (
                              <span className="text-[10px] text-muted-foreground self-center ml-1">+{connectedEntities.length - 12} more</span>
                            )}
                          </div>
                        </div>
                      </div>
                    )}
                  </TabsContent>

                  <TabsContent value="provenance" className="m-0 h-full overflow-y-auto overflow-x-hidden absolute inset-0">
                    {selectedNode?.type === "Disease" ? (
                      <ProvenancePanel diseaseId={selectedNode.id} diseaseName={selectedNode.label} />
                    ) : (
                      <CompoundProvenancePanel compoundId={selectedNode.id} compoundName={selectedNode.label} />
                    )}
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
                    {(isBridgeNode(selectedNode) || isBridgeEdge(selectedEdge?.edge)) && (
                      <div className="m-4 mb-0 rounded-md border border-terracotta/30 bg-terracotta/5 p-3">
                        <div className="flex items-center gap-1.5 mb-1.5">
                          <GitBranch className="h-3.5 w-3.5 text-terracotta" />
                          <span className="text-[10px] font-semibold uppercase tracking-wider text-terracotta">
                            {"Cross-domain bridge"}
                          </span>
                        </div>
                        <p className="text-[11px] text-foreground/80 leading-relaxed">
                          {selectedEdge?.edge.bridgeNote
                            ? String(selectedEdge.edge.bridgeNote)
                            : "Purified compounds are the conceptual bridge between the Compound-ShodhanaKG and the Disease-ShodhanaKG — they emerge from Shodhana of raw compounds and re-enter the disease side as ingredients of formulations and as direct treatments."}
                        </p>
                      </div>
                    )}
                    <DetailsPanel
                      selectedNode={selectedNode}
                      selectedEdge={selectedEdge}
                      nodeTypeLabels={I_NODE_TYPE_LABEL as unknown as Record<string, string>}
                      edgeTypeLabels={I_EDGE_TYPE_LABEL as unknown as Record<string, string>}
                      nodeColors={reactiveNodeColors as unknown as Record<string, string>}
                      edgeColors={I_EDGE_COLORS as unknown as Record<string, string>}
                      edgePropertyMap={EDGE_PROP_MAP}
                    />
                    {selectedNode && connectedEntities.length > 0 && (
                      <div className="px-5 pb-5 space-y-3">
                        <div>
                          <div className="text-[10px] uppercase tracking-wider text-muted-foreground mb-1.5 flex items-center gap-1">
                            <Info className="h-3 w-3" />
                            {"Connected entities"} ({connectedEntities.length})
                          </div>
                          <div className="flex flex-wrap gap-1">
                            {connectedEntities.slice(0, 12).map((n) => (
                              <button
                                key={n.id}
                                onClick={() => addSeed(n.id)}
                                title={`${I_NODE_TYPE_LABEL[n.type]} — click to add as starting node`}
                                className="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-md bg-secondary text-foreground/80 hover:bg-secondary/80"
                              >
                                <span
                                  className="h-1.5 w-1.5 rounded-full"
                                  style={{ background: reactiveNodeColors[n.type] }}
                                />
                                {(n.label)}
                              </button>
                            ))}
                            {connectedEntities.length > 12 && (
                              <span className="text-[10px] text-muted-foreground self-center ml-1">+{connectedEntities.length - 12} more</span>
                            )}
                          </div>
                        </div>
                      </div>
                    )}
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

export default IntegratedGraphView;


