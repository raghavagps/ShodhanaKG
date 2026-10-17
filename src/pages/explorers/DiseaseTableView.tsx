import { useColorblind } from '@/hooks/useColorblind';
import { nodeStyle } from '@/lib/nodeStyles';
import { entityGraphUrl } from '@/lib/entityNavigation';
import { useState } from "react";
import { Link, NavLink, useNavigate, useSearchParams } from "react-router-dom";
import {
  Home,
  Network,
  Table as TableIcon,
  Search,
  ArrowUpDown,
  ArrowUp,
  ArrowDown,
  ExternalLink,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { toast } from "sonner";
import { useCategoryStats, CategoryStatsRow } from "@/lib/api";
import type { CategoryType } from "@/lib/types";



const subNav = [
  { to: "/explorer/disease", label: "Home", icon: Home, end: true },
  { to: "/explorer/disease/graph", label: "Graph View", icon: Network, end: true },
  { to: "/explorer/disease/table", label: "Table View", icon: TableIcon, end: true },
];

interface ColumnDef {
  key: string;
  label: string;
  numeric?: boolean;
  accent?: string;
}

interface CategoryConfig {
  title: string;
  subtitle: string;
  searchPlaceholder: string;
  primaryLabel: string;
  columns: ColumnDef[];
  countKeys: string[];
  defaultSort: string;
}

const CATEGORY_LABEL: Record<string, string> = {
  Disease: "Disease",
  Symptom: "Symptom",
  AyurvedicDiagnosis: "Ayurvedic Diagnosis",
  Dosha: "Dosha",
  Therapy: "Therapy",
  Formulation: "Formulation",
  AssessmentParameter: "Assessment Parameter",
};

const CATEGORY_CONFIGS: Record<string, CategoryConfig> = {
  Disease: {
    title: "Disease relationship statistics",
    subtitle: "Number of connected entities per disease across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter diseases…",
    primaryLabel: "Disease Name",
    columns: [
      { key: "symptoms", label: "Symptoms", numeric: true, accent: "hsl(35, 60%, 55%)" },
      { key: "ayurvedicDiagnosis", label: "Ayurvedic Diagnosis", numeric: true, accent: "#7851A9" },
      { key: "doshas", label: "Doshas", numeric: true, accent: "hsl(280, 18%, 45%)" },
      { key: "therapy", label: "Therapy", numeric: true, accent: "hsl(130, 22%, 30%)" },
      { key: "assessmentParameters", label: "Assessment Parameter", numeric: true, accent: "hsl(200, 25%, 40%)" },
    ],
    countKeys: ["symptoms", "ayurvedicDiagnosis", "doshas", "therapy", "assessmentParameters"],
    defaultSort: "total",
  },
  Therapy: {
    title: "Therapy relationship statistics",
    subtitle: "Number of diseases and formulations linked to each therapy across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter therapies…",
    primaryLabel: "Therapy Name",
    columns: [
      { key: "diseases", label: "Linked Diseases", numeric: true, accent: "hsl(16, 45%, 50%)" },
      { key: "formulations", label: "Formulations", numeric: true, accent: "hsl(140, 20%, 45%)" },
    ],
    countKeys: ["diseases", "formulations"],
    defaultSort: "diseases",
  },
  Symptom: {
    title: "Symptom relationship statistics",
    subtitle: "Number of diseases linked to each symptom across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter symptoms…",
    primaryLabel: "Symptom Name",
    columns: [
      { key: "diseases", label: "Linked Diseases", numeric: true, accent: "hsl(16, 45%, 50%)" },
    ],
    countKeys: ["diseases"],
    defaultSort: "diseases",
  },
  AyurvedicDiagnosis: {
    title: "Ayurvedic Diagnosis relationship statistics",
    subtitle: "Number of diseases linked to each Ayurvedic diagnosis across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter diagnoses…",
    primaryLabel: "Diagnosis Name",
    columns: [
      { key: "diseases", label: "Linked Diseases", numeric: true, accent: "hsl(16, 45%, 50%)" },
    ],
    countKeys: ["diseases"],
    defaultSort: "diseases",
  },
  Dosha: {
    title: "Dosha relationship statistics",
    subtitle: "Number of diseases linked to each dosha across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter doshas…",
    primaryLabel: "Dosha Name",
    columns: [
      { key: "diseases", label: "Linked Diseases", numeric: true, accent: "hsl(16, 45%, 50%)" },
    ],
    countKeys: ["diseases"],
    defaultSort: "diseases",
  },
  Formulation: {
    title: "Formulation relationship statistics",
    subtitle: "Number of therapies linked to each formulation across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter formulations…",
    primaryLabel: "Formulation Name",
    columns: [
      { key: "therapies", label: "Therapies", numeric: true, accent: "hsl(35, 60%, 55%)" },
    ],
    countKeys: ["therapies"],
    defaultSort: "therapies",
  },
  AssessmentParameter: {
    title: "Assessment Parameter relationship statistics",
    subtitle: "Number of diseases linked to each assessment parameter across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter parameters…",
    primaryLabel: "Parameter Name",
    columns: [
      { key: "diseases", label: "Linked Diseases", numeric: true, accent: "hsl(16, 45%, 50%)" },
    ],
    countKeys: ["diseases"],
    defaultSort: "diseases",
  },
};

const fmt = (n: number | undefined) => (n ?? 0).toString().padStart(2, "0");

const DiseaseTableView = () => {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  
  const category = (searchParams.get("category") ?? "Disease") as CategoryType;
  const { isColorblind } = useColorblind();
  const cfg = CATEGORY_CONFIGS[category] ?? CATEGORY_CONFIGS.Disease;

  const [query, setQuery] = useState("");
  const [sortKey, setSortKey] = useState<string>(cfg.defaultSort);
  const [sortDir, setSortDir] = useState<"asc" | "desc">(cfg.defaultSort === "name" ? "asc" : "desc");
  const [page, setPage] = useState(0);
  const pageSize = 25;

  const { data, isLoading, isError } = useCategoryStats("disease", category, query, sortKey, sortDir, pageSize, page * pageSize);
  const rawRows = data?.rows ?? [];
  const total = data?.total ?? 0;

  // ── Translation middleware ─────────────────────────────────────────
  const { translatedRows: rows } = { translatedRows: rawRows };

  const onSort = (k: string) => {
    if (sortKey === k) setSortDir((d) => (d === "asc" ? "desc" : "asc"));
    else {
      setSortKey(k);
      setSortDir(k === "name" ? "asc" : "desc");
    }
    setPage(0);
  };

  const graphUrl = (row: CategoryStatsRow) => entityGraphUrl({ id: row.id, type: category }, 'disease');

  const openInGraph = (row: CategoryStatsRow) => {
    toast.success(`Opening graph filtered to "${row.name}"`);
    navigate(graphUrl(row));
  };

  const SortIcon = ({ k }: { k: string }) => {
    if (sortKey !== k) return <ArrowUpDown className="h-3 w-3 opacity-40" />;
    return sortDir === "asc" ? <ArrowUp className="h-3 w-3" /> : <ArrowDown className="h-3 w-3" />;
  };

  const totalPages = Math.ceil(total / pageSize);
  const catLabel = CATEGORY_LABEL[category] ?? "Entity";

  return (
    <section className="container py-8 md:py-10">
      {/* Sub-nav */}
      <div className="mb-6 flex items-center justify-between gap-3 flex-wrap">
        <nav className="inline-flex items-center gap-1 rounded-full border border-border bg-card/70 p-1 shadow-sm">
          {subNav.map(({ to, label, icon: Icon, end }) => (
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
              {label}
            </NavLink>
          ))}
        </nav>

      </div>

      {/* Title */}
      <div className="mb-5">
        <h1 className="font-display text-2xl md:text-3xl font-semibold text-foreground">
          {cfg.title}
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          {cfg.subtitle}
        </p>
      </div>

      {/* Search */}
      <div className="mb-4 relative max-w-sm">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
        <input
          value={query}
          onChange={(e) => {
            setQuery(e.target.value);
            setPage(0);
          }}
          placeholder={cfg.searchPlaceholder}
          className="w-full rounded-md border border-border bg-card pl-8 pr-3 py-2 text-sm outline-none focus:border-herbal/50 focus:ring-2 focus:ring-herbal/15 transition-smooth"
        />
      </div>

      {/* Table */}
      <div className="rounded-xl border border-border bg-card shadow-card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-secondary/60 border-b border-border">
                <th className="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground text-left whitespace-nowrap">
                  {cfg.primaryLabel}
                </th>
                {cfg.columns.map((col) => (
                  <th
                    key={col.key}
                    onClick={() => onSort(col.key)}
                    className={cn(
                      "px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground cursor-pointer select-none hover:text-foreground transition-smooth whitespace-nowrap",
                      col.numeric ? "text-center" : "text-left"
                    )}
                  >
                    <div
                      className={cn(
                        "flex items-center gap-1",
                        col.numeric ? "justify-center" : "justify-start"
                      )}
                    >
                      {col.accent && (
                        <span className="h-2 w-2 rounded-full shrink-0" style={{ background: col.key === "ayurvedicDiagnosis" ? nodeStyle("AyurvedicDiagnosis",isColorblind).fill : col.accent }} />
                      )}
                      {(col.label)} <SortIcon k={col.key} />
                    </div>
                  </th>
                ))}
                <th className="px-4 py-3 w-10" />
              </tr>
            </thead>
            <tbody>
              {isLoading && (
                <tr>
                  <td colSpan={cfg.columns.length + 2} className="px-4 py-10 text-center text-sm text-muted-foreground">
                    Loading...
                  </td>
                </tr>
              )}
              {isError && (
                <tr>
                  <td colSpan={cfg.columns.length + 2} className="px-4 py-10 text-center text-sm text-destructive">
                    Failed to load data
                  </td>
                </tr>
              )}
               {!isLoading && !isError && rows.map((row, idx) => (
                <tr
                  key={row.id}
                  onClick={() => openInGraph(row)}
                  className={cn(
                    "group border-b border-border/60 last:border-0 cursor-pointer transition-smooth",
                    "hover:bg-secondary/40",
                    idx % 2 === 1 && "bg-background/30"
                  )}
                >
                  <td className="px-4 py-3.5">
                    <div className="font-medium text-foreground">
                      {row.name}
                    </div>
                  </td>
                  {cfg.countKeys.map((k) => (
                    <Cell key={k} value={row[k] as number | undefined} />
                  ))}
                  <td className="px-4 py-3.5 text-right">
                    <Link
                      to={graphUrl(row)}
                      onClick={(e) => e.stopPropagation()}
                      className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-medium bg-primary/10 text-primary hover:bg-primary hover:text-primary-foreground transition-smooth whitespace-nowrap"
                    >
                      Open graph
                      <ExternalLink className="h-3 w-3" />
                    </Link>
                  </td>
                </tr>
              ))}

              {!isLoading && !isError && rows.length === 0 && (
                <tr>
                  <td colSpan={cfg.columns.length + 2} className="px-4 py-10 text-center text-sm text-muted-foreground">
                    No {catLabel.toLowerCase()}s match "{query}".
                  </td>
                </tr>
              )}
            </tbody>

          </table>
        </div>
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="mt-4 flex items-center justify-between">
          <span className="text-xs text-muted-foreground">
            Showing {page * pageSize + 1}–{Math.min((page + 1) * pageSize, total)} of {total}
          </span>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setPage((p) => Math.max(0, p - 1))}
              disabled={page === 0}
              className="px-3 py-1.5 text-xs rounded-md border border-border bg-card disabled:opacity-50 disabled:cursor-not-allowed hover:bg-secondary transition-smooth"
            >
              Previous
            </button>
            <span className="text-xs text-muted-foreground">
              Page {page + 1} of {totalPages}
            </span>
            <button
              onClick={() => setPage((p) => Math.min(totalPages - 1, p + 1))}
              disabled={page >= totalPages - 1}
              className="px-3 py-1.5 text-xs rounded-md border border-border bg-card disabled:opacity-50 disabled:cursor-not-allowed hover:bg-secondary transition-smooth"
            >
              Next
            </button>
          </div>
        </div>
      )}

      <p className="mt-3 text-[11px] text-muted-foreground">
        Counts reflect direct relationships in the current knowledge graph.
      </p>
    </section>
  );
};

const Cell = ({ value }: { value: number | undefined }) => (
  <td className="px-4 py-3.5 text-center">
    <span
      className={cn(
        "inline-flex items-center justify-center min-w-[2rem] h-7 px-2 rounded-md text-xs font-mono font-semibold",
        (value ?? 0) > 0 ? "bg-secondary text-foreground" : "text-muted-foreground/50"
      )}
    >
      {fmt(value)}
    </span>
  </td>
);

export default DiseaseTableView;
