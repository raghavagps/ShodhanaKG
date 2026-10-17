import { Fragment, useMemo, useState, useEffect } from "react";
import { Link, NavLink, useNavigate } from "react-router-dom";
import {
  Home,
  Network,
  Table as TableIcon,
  Search,
  ArrowUpDown,
  ArrowUp,
  ArrowDown,
  ExternalLink,
  Sparkles,
  ChevronRight,
  Loader2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { I_NODE_TYPE_LABEL, I_NODE_COLORS } from "@/data/integratedGraph";
import { fetchApi } from "@/lib/api";



const subNav = [
  { to: "/explorer/integrated", label: "Home", icon: Home, end: true },
  { to: "/explorer/integrated/graph", label: "Graph View", icon: Network, end: true },
  { to: "/explorer/integrated/table", label: "Table View", icon: TableIcon, end: true },
];

interface BridgeRow {
  purifiedId: string;
  purifiedLabel: string;
  purifiedDescription: string;
  compoundIds: string[];
  diseaseCount: number;
  diseaseLabels: string[];
  studies: number;
}

type SortKey = "name" | "diseases" | "studies";

const IntegratedTableView = () => {
  const navigate = useNavigate();
  
  const [query, setQuery] = useState("");
  const [sortKey, setSortKey] = useState<SortKey>("diseases");
  const [sortDir, setSortDir] = useState<"asc" | "desc">("desc");
  const [expanded, setExpanded] = useState<Set<string>>(new Set());
  const [rawRows, setRawRows] = useState<BridgeRow[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isError, setIsError] = useState(false);

  // Adapt BridgeRow to TranslatableRow shape, then translate
  const adaptedRows = rawRows.map((r) => ({ ...r, name: r.purifiedLabel, description: r.purifiedDescription }));
  const { translatedRows: translatedAdapted } = { translatedRows: adaptedRows };
  const rows = translatedAdapted.map((r, i) => ({
    ...rawRows[i],
    purifiedLabel: r.name ?? rawRows[i].purifiedLabel,
    purifiedDescription: r.description ?? rawRows[i].purifiedDescription,
  }));

  useEffect(() => {
    setIsLoading(true);
    setIsError(false);
    fetchApi<{ ok: boolean; rows: Record<string, unknown>[] }>("bridge-stats.php?limit=100")
      .then((data) => {
        const mapped: BridgeRow[] = (data.rows ?? []).map((r) => ({
          purifiedId: String(r.purifiedId ?? ""),
          purifiedLabel: String(r.purifiedLabel ?? ""),
          purifiedDescription: String(r.purifiedDescription ?? ""),
          compoundIds: (r.compoundIds as string[]) ?? [],
          diseaseCount: Number(r.diseaseCount ?? 0),
          diseaseLabels: (r.diseaseLabels as string[]) ?? [],
          studies: Number(r.studies ?? 0),
        }));
        setRawRows(mapped);
        setIsLoading(false);
      })
      .catch(() => {
        setIsError(true);
        setIsLoading(false);
      });
  }, []);

  const toggleExpand = (id: string) => {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    let filtered = rows;
    if (q) {
      filtered = filtered.filter((r) =>
        [r.purifiedLabel, ...r.diseaseLabels]
          .join(" ")
          .toLowerCase()
          .includes(q),
      );
    }
    const sorted = [...filtered].sort((a, b) => {
      const get = (r: BridgeRow): string | number => {
        switch (sortKey) {
          case "diseases": return r.diseaseCount;
          case "studies": return r.studies;
          default: return r.purifiedLabel;
        }
      };
      const av = get(a);
      const bv = get(b);
      if (typeof av === "string" && typeof bv === "string") {
        return sortDir === "asc" ? av.localeCompare(bv) : bv.localeCompare(av);
      }
      return sortDir === "asc"
        ? (av as number) - (bv as number)
        : (bv as number) - (av as number);
    });
    return sorted;
  }, [rows, query, sortKey, sortDir]);

  const onSort = (k: SortKey) => {
    if (sortKey === k) setSortDir((d) => (d === "asc" ? "desc" : "asc"));
    else {
      setSortKey(k);
      setSortDir(k === "name" ? "asc" : "desc");
    }
  };

  const graphUrl = (row: BridgeRow) => {
    const seeds = [row.purifiedId, ...row.compoundIds].filter(Boolean);
    const params = new URLSearchParams({ seeds: seeds.join(","), depth: "1" });
    return `/explorer/integrated/graph?${params.toString()}`;
  };

  const openInGraph = (row: BridgeRow) => {
    navigate(graphUrl(row));
  };

  const SortIcon = ({ k }: { k: SortKey }) => {
    if (sortKey !== k) return <ArrowUpDown className="h-3 w-3 opacity-40" />;
    return sortDir === "asc" ? <ArrowUp className="h-3 w-3" /> : <ArrowDown className="h-3 w-3" />;
  };

  return (
    <section className="container py-8 md:py-10">
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
                  isActive
                    ? "bg-primary text-primary-foreground shadow-sm"
                    : "text-muted-foreground hover:text-foreground hover:bg-secondary/70",
                )
              }
            >
              <Icon className="h-3.5 w-3.5" />
              {label}
            </NavLink>
          ))}
        </nav>
      </div>

      <div className="mb-5">
        <div className="inline-flex items-center gap-1.5 text-xs font-medium uppercase tracking-[0.18em] text-terracotta mb-1.5">
          <Sparkles className="h-3.5 w-3.5" />
          Cross-domain bridges
        </div>
        <h1 className="font-display text-2xl md:text-3xl font-semibold text-foreground">
          Where the Two Domains Meet
        </h1>
        <p className="mt-1 text-sm text-muted-foreground max-w-3xl">
          Each row shows a purified compound that connects the compound and disease knowledge domains —
          the diseases it treats. Click a row to open the integrated graph.
        </p>
      </div>

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div className="relative flex-1 min-w-[220px] max-w-sm">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
          <input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Filter by purified compound or disease…"
            className="w-full rounded-md border border-border bg-card pl-8 pr-3 py-2 text-sm outline-none focus:border-herbal/50 focus:ring-2 focus:ring-herbal/15 transition-smooth"
          />
        </div>
      </div>

      <div className="rounded-xl border border-border bg-card shadow-card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-secondary/60 border-b border-border">
                <th className="px-4 py-3 w-10" />
                <Th onClick={() => onSort("name")} sortIcon={<SortIcon k="name" />}>
                  {"Purified compound"}
                </Th>
                <Th onClick={() => onSort("diseases")} sortIcon={<SortIcon k="diseases" />} centered>
                  {"Disease Count"}
                </Th>
                <th className="px-4 py-3 w-10" />
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr>
                  <td colSpan={4} className="px-4 py-10 text-center text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin inline mr-2" />
                    Loading bridge data…
                  </td>
                </tr>
              ) : isError ? (
                <tr>
                  <td colSpan={4} className="px-4 py-10 text-center text-sm text-muted-foreground">
                    Unable to load bridge data.
                  </td>
                </tr>
              ) : filtered.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-4 py-10 text-center text-sm text-muted-foreground">
                    {query ? `No entries match "${query}".` : "No bridge data available."}
                  </td>
                </tr>
              ) : (
                filtered.map((row, idx) => {
                  const isOpen = expanded.has(row.purifiedId);
                  return (
                    <Fragment key={row.purifiedId}>
                      <tr
                        onClick={() => openInGraph(row)}
                        className={cn(
                          "group border-b border-border/60 cursor-pointer transition-smooth",
                          "hover:bg-secondary/40",
                          idx % 2 === 1 && !isOpen && "bg-background/30",
                          isOpen && "bg-secondary/30 border-b-0",
                        )}
                      >
                        <td className="pl-4 pr-0 py-3.5 align-middle w-10">
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              toggleExpand(row.purifiedId);
                            }}
                            aria-label={isOpen ? "Collapse details" : "Expand details"}
                            className="inline-flex h-6 w-6 items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-secondary transition-smooth"
                          >
                            <ChevronRight
                              className={cn(
                                "h-3.5 w-3.5 transition-transform",
                                isOpen && "rotate-90",
                              )}
                            />
                          </button>
                        </td>

                        <td className="px-4 py-3.5 min-w-[220px] align-middle">
                          <div className="flex items-center gap-2">
                            <span
                              className="h-2 w-2 rounded-full shrink-0"
                              style={{ background: I_NODE_COLORS.PurifiedCompound }}
                            />
                            <div>
                              <div className="font-medium text-foreground group-hover:text-herbal transition-smooth leading-tight">
                                {row.purifiedLabel}
                              </div>
                              <div className="text-[10px] uppercase tracking-wider text-muted-foreground mt-0.5">
                                {I_NODE_TYPE_LABEL.PurifiedCompound}
                              </div>
                            </div>
                          </div>
                        </td>

                        <td className="px-4 py-3.5 align-middle text-center">
                          <CountBadge value={row.diseaseCount} color={I_NODE_COLORS.Disease} />
                        </td>

                        <td className="px-4 py-3.5 text-right align-middle">
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

                      {isOpen && (
                        <tr className="bg-secondary/20 border-b border-border/60">
                          <td />
                          <td colSpan={3} className="px-4 pt-2 pb-5">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                              <DetailGroup
                                title="Connected diseases"
                                count={row.diseaseCount}
                                accent={I_NODE_COLORS.Disease}
                                items={row.diseaseLabels}
                              />
                            </div>
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      <p className="mt-3 text-[11px] text-muted-foreground">
        Each row shows one purified compound with its connected disease counts.
        Click a row to open the integrated graph view, or use the chevron to expand the connected
        entity names inline.
      </p>
    </section>
  );
};

const Th = ({
  children,
  onClick,
  sortIcon,
  centered = false,
}: {
  children: React.ReactNode;
  onClick: () => void;
  sortIcon: React.ReactNode;
  centered?: boolean;
}) => (
  <th
    onClick={onClick}
    className={cn(
      "px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground cursor-pointer select-none hover:text-foreground transition-smooth whitespace-nowrap",
      centered ? "text-center" : "text-left",
    )}
  >
    <span className={cn("inline-flex items-center gap-1.5", centered && "justify-center")}>
      {children}
      {sortIcon}
    </span>
  </th>
);

const CountBadge = ({ value, color }: { value: number; color: string }) => (
  <span
    className="inline-flex items-center justify-center min-w-[2.25rem] h-7 px-2 rounded-md font-mono text-sm font-semibold tabular-nums"
    style={{ background: `${color}18`, color }}
  >
    {value}
  </span>
);

const DetailGroup = ({
  title,
  count,
  accent,
  items,
}: {
  title: string;
  count: number;
  accent: string;
  items: string[];
}) => (
  <div className="rounded-lg border border-border bg-card/60 p-3">
    <div className="flex items-center justify-between mb-2">
      <div className="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-muted-foreground">
        <span className="h-1.5 w-1.5 rounded-full" style={{ background: accent }} />
        {title}
      </div>
      <span className="text-[10px] font-mono text-muted-foreground">{count}</span>
    </div>
    {items.length === 0 ? (
      <span className="text-[11px] text-muted-foreground/60">—</span>
    ) : (
      <div className="flex flex-wrap gap-1">
        {items.map((label) => (
          <span
            key={label}
            className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-medium bg-secondary text-foreground/85 text-[11px]"
          >
            <span className="h-1.5 w-1.5 rounded-full" style={{ background: accent }} />
            {label}
          </span>
        ))}
      </div>
    )}
  </div>
);

export default IntegratedTableView;
