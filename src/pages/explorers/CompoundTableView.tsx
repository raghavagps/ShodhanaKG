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
  { to: "/explorer/compound", label: "Home", icon: Home, end: true },
  { to: "/explorer/compound/graph", label: "Graph View", icon: Network, end: true },
  { to: "/explorer/compound/table", label: "Table View", icon: TableIcon, end: true },
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
  Compound: "Compound",
  ShodhanaMethod: "Shodhana Method",
  ToxicSubstance: "Toxic Substance",
  PurifiedCompound: "Purified Compound",
  ShodhanaType: "Shodhana Type",
  Media: "Media",
  ConfirmationStudy: "Confirmation Study",
  Process: "Process",
  Indication: "Indication",
  AyurvedicFormulation: "Ayurvedic Formulation",
};

// Column accent colors for visual consistency
const ACCENTS = {
  compound: "hsl(16, 45%, 50%)",
  shodhanaMethod: "hsl(35, 60%, 55%)",
  toxicSubstance: "hsl(8, 55%, 40%)",
  purifiedCompound: "hsl(140, 25%, 55%)",
  shodhanaType: "hsl(280, 18%, 45%)",
  media: "hsl(200, 28%, 45%)",
  confirmationStudy: "hsl(35, 50%, 50%)",
  process: "hsl(40, 25%, 40%)",
  indication: "hsl(355, 40%, 50%)",
  ayurvedicFormulation: "hsl(260, 20%, 50%)",
};

// Category configurations with columns in ALPHABETICAL order (as requested)
// Columns show counts of OUTGOING relationships (entity is source)
const CATEGORY_CONFIGS: Record<string, CategoryConfig> = {
  Compound: {
    title: "Compound relationship statistics",
    subtitle: "Number of connected entities per compound across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter compounds…",
    primaryLabel: "Compound Name",
    columns: [
      { key: "purifiedCompounds", label: "Purified Compound", numeric: true, accent: ACCENTS.purifiedCompound },
      { key: "shodhanaMethods", label: "Shodhana Method", numeric: true, accent: ACCENTS.shodhanaMethod },
      { key: "toxicSubstances", label: "Toxic Substance", numeric: true, accent: ACCENTS.toxicSubstance },
    ],
    countKeys: ["purifiedCompounds", "shodhanaMethods", "toxicSubstances"],
    defaultSort: "shodhanaMethods",
  },
  ShodhanaMethod: {
    title: "Shodhana Method relationship statistics",
    subtitle: "Number of connected entities per shodhana method across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter shodhana methods…",
    primaryLabel: "Shodhana Method Name",
    columns: [
      { key: "compounds", label: "Compound", numeric: true, accent: ACCENTS.compound },
      { key: "confirmationStudies", label: "Confirmation Study", numeric: true, accent: ACCENTS.confirmationStudy },
      { key: "media", label: "Media", numeric: true, accent: ACCENTS.media },
      { key: "processes", label: "Process", numeric: true, accent: ACCENTS.process },
      { key: "purifiedCompounds", label: "Purified Compound", numeric: true, accent: ACCENTS.purifiedCompound },
      { key: "shodhanaTypes", label: "Shodhana Type", numeric: true, accent: ACCENTS.shodhanaType },
      { key: "toxicSubstances", label: "Toxic Substance", numeric: true, accent: ACCENTS.toxicSubstance },
    ],
    countKeys: ["compounds", "confirmationStudies", "media", "processes", "purifiedCompounds", "shodhanaTypes", "toxicSubstances"],
    defaultSort: "purifiedCompounds",
  },
  ToxicSubstance: {
    title: "Toxic Substance relationship statistics",
    subtitle: "Number of connected entities per toxic substance across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter toxic substances…",
    primaryLabel: "Toxic Substance Name",
    columns: [
      { key: "compounds", label: "Compound", numeric: true, accent: ACCENTS.compound },
      { key: "shodhanaMethods", label: "Shodhana Method", numeric: true, accent: ACCENTS.shodhanaMethod },
    ],
    countKeys: ["compounds", "shodhanaMethods"],
    defaultSort: "compounds",
  },
  PurifiedCompound: {
    title: "Purified Compound relationship statistics",
    subtitle: "Number of connected entities per purified compound across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter purified compounds…",
    primaryLabel: "Purified Compound Name",
    columns: [
      { key: "ayurvedicFormulations", label: "Ayurvedic Formulation", numeric: true, accent: ACCENTS.ayurvedicFormulation },
      { key: "compounds", label: "Compound", numeric: true, accent: ACCENTS.compound },
      { key: "confirmationStudies", label: "Confirmation Study", numeric: true, accent: ACCENTS.confirmationStudy },
      { key: "indications", label: "Indication", numeric: true, accent: ACCENTS.indication },
      { key: "processes", label: "Process", numeric: true, accent: ACCENTS.process },
      { key: "shodhanaMethods", label: "Shodhana Method", numeric: true, accent: ACCENTS.shodhanaMethod },
    ],
    countKeys: ["ayurvedicFormulations", "compounds", "confirmationStudies", "indications", "processes", "shodhanaMethods"],
    defaultSort: "indications",
  },
  ShodhanaType: {
    title: "Shodhana Type relationship statistics",
    subtitle: "Number of shodhana methods per type across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter shodhana types…",
    primaryLabel: "Shodhana Type Name",
    columns: [
      { key: "shodhanaMethods", label: "Shodhana Method", numeric: true, accent: ACCENTS.shodhanaMethod },
    ],
    countKeys: ["shodhanaMethods"],
    defaultSort: "shodhanaMethods",
  },
  Media: {
    title: "Media relationship statistics",
    subtitle: "Number of connected entities per media across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter media…",
    primaryLabel: "Media Name",
    columns: [
      { key: "processes", label: "Process", numeric: true, accent: ACCENTS.process },
      { key: "shodhanaMethods", label: "Shodhana Method", numeric: true, accent: ACCENTS.shodhanaMethod },
    ],
    countKeys: ["processes", "shodhanaMethods"],
    defaultSort: "shodhanaMethods",
  },
  ConfirmationStudy: {
    title: "Confirmation Study relationship statistics",
    subtitle: "Number of connected entities per confirmation study across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter confirmation studies…",
    primaryLabel: "Confirmation Study Name",
    columns: [
      { key: "processes", label: "Process", numeric: true, accent: ACCENTS.process },
      { key: "purifiedCompounds", label: "Purified Compound", numeric: true, accent: ACCENTS.purifiedCompound },
      { key: "shodhanaMethods", label: "Shodhana Method", numeric: true, accent: ACCENTS.shodhanaMethod },
    ],
    countKeys: ["processes", "purifiedCompounds", "shodhanaMethods"],
    defaultSort: "shodhanaMethods",
  },
  Process: {
    title: "Process relationship statistics",
    subtitle: "Number of connected entities per process across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter processes…",
    primaryLabel: "Process Name",
    columns: [
      { key: "confirmationStudies", label: "Confirmation Study", numeric: true, accent: ACCENTS.confirmationStudy },
      { key: "media", label: "Media", numeric: true, accent: ACCENTS.media },
      { key: "nextProcesses", label: "Next Process", numeric: true, accent: ACCENTS.process },
      { key: "purifiedCompounds", label: "Purified Compound", numeric: true, accent: ACCENTS.purifiedCompound },
      { key: "shodhanaMethods", label: "Shodhana Method", numeric: true, accent: ACCENTS.shodhanaMethod },
    ],
    countKeys: ["confirmationStudies", "media", "nextProcesses", "purifiedCompounds", "shodhanaMethods"],
    defaultSort: "nextProcesses",
  },
  Indication: {
    title: "Indication relationship statistics",
    subtitle: "Number of connected entities per indication across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter indications…",
    primaryLabel: "Indication Name",
    columns: [
      { key: "purifiedCompounds", label: "Purified Compound", numeric: true, accent: ACCENTS.purifiedCompound },
    ],
    countKeys: ["purifiedCompounds"],
    defaultSort: "purifiedCompounds",
  },
  AyurvedicFormulation: {
    title: "Ayurvedic Formulation relationship statistics",
    subtitle: "Number of connected entities per formulation across the knowledge graph. Click a row to open it in the graph view.",
    searchPlaceholder: "Filter formulations…",
    primaryLabel: "Formulation Name",
    columns: [
      { key: "purifiedCompounds", label: "Purified Compound", numeric: true, accent: ACCENTS.purifiedCompound },
    ],
    countKeys: ["purifiedCompounds"],
    defaultSort: "purifiedCompounds",
  },
};

const fmt = (n: number | undefined) => (n ?? 0).toString().padStart(2, "0");

const CompoundTableView = () => {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  
  const category = (searchParams.get("category") ?? "Compound") as CategoryType;
  const cfg = CATEGORY_CONFIGS[category] ?? CATEGORY_CONFIGS.Compound;

  const [query, setQuery] = useState("");
  const [sortKey, setSortKey] = useState<string>(cfg.defaultSort);
  const [sortDir, setSortDir] = useState<"asc" | "desc">(cfg.defaultSort === "name" ? "asc" : "desc");
  const [page, setPage] = useState(0);
  const pageSize = 25;

  const { data, isLoading, isError } = useCategoryStats("compound", category, query, sortKey, sortDir, pageSize, page * pageSize);
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

  const graphUrl = (row: CategoryStatsRow) => entityGraphUrl({ id: row.id, type: category }, 'compound');

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
                    <span className={cn("inline-flex items-center gap-1.5", col.numeric && "justify-center")}>
                      {col.accent && (
                        <span className="h-2 w-2 rounded-full shrink-0" style={{ background: col.accent }} />
                      )}
                      {(col.label)}
                      <SortIcon k={col.key} />
                    </span>
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
                    <div className="font-medium text-foreground group-hover:text-herbal transition-smooth">
                      {row.name}
                    </div>
                    {category !== "ShodhanaMethod" && row.description && row.description.trim() !== "" && row.description.trim().toLowerCase() !== row.name.trim().toLowerCase() && (
                      <div className="text-[11px] text-muted-foreground line-clamp-1 max-w-[280px]">
                        {row.description}
                      </div>
                    )}
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
                    No {catLabel.toLowerCase()}s match &quot;{query}&quot;.
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
        Counts reflect outgoing relationships in the current knowledge graph.
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

export default CompoundTableView;
