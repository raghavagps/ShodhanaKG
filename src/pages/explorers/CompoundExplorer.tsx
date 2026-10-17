import { uiLabels } from '@/data/uiLabels';
import { entityGraphUrl } from '@/lib/entityNavigation';
import { useState, KeyboardEvent, useMemo, ComponentType, useEffect } from "react";
import { NavLink, useNavigate } from "react-router-dom";
import {
  Search,
  ArrowRight,
  Home,
  Network,
  Table as TableIcon,
  FlaskConical,
  Beaker,
  AlertTriangle,
  Sparkles,
  Layers,
  Droplets,
  ClipboardCheck,
  Stethoscope,
  FlaskRound,
  Workflow,
  ChevronDown,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { C_NODE_TYPE_LABEL, C_NODE_COLORS } from "@/data/compoundGraph";
import { useSearch, useCounts } from "@/lib/api";


const CATEGORY_DEFS = [
  { key: "Compound", nameKey: "Compound", countKey: "Compound", icon: FlaskConical, accent: "herbal" as const },
  { key: "ShodhanaMethod", nameKey: "ShodhanaMethod", countKey: "ShodhanaMethod", icon: Beaker, accent: "terracotta" as const },
  { key: "ToxicSubstance", nameKey: "ToxicSubstance", countKey: "ToxicSubstance", icon: AlertTriangle, accent: "terracotta" as const },
  { key: "PurifiedCompound", nameKey: "PurifiedCompound", countKey: "PurifiedCompound", icon: Sparkles, accent: "herbal" as const },
  { key: "ShodhanaType", nameKey: "ShodhanaType", countKey: "ShodhanaType", icon: Layers, accent: "bark" as const },
  { key: "Media", nameKey: "Media", countKey: "Media", icon: Droplets, accent: "herbal" as const },
  { key: "ConfirmationStudy", nameKey: "ConfirmationStudy", countKey: "ConfirmationStudy", icon: ClipboardCheck, accent: "bark" as const },
  { key: "Indication", nameKey: "Indication", countKey: "Indication", icon: Stethoscope, accent: "terracotta" as const },
  { key: "AyurvedicFormulation", nameKey: "AyurvedicFormulation", countKey: "AyurvedicFormulation", icon: FlaskRound, accent: "bark" as const },
  { key: "Process", nameKey: "Process", countKey: "Process", icon: Workflow, accent: "herbal" as const },
];

const accentIcon: Record<string, string> = {
  terracotta: "text-terracotta bg-terracotta/10 group-hover:bg-terracotta group-hover:text-primary-foreground",
  herbal: "text-herbal bg-herbal/10 group-hover:bg-herbal group-hover:text-primary-foreground",
  bark: "text-bark bg-bark/10 group-hover:bg-bark group-hover:text-primary-foreground",
};
const accentBorder: Record<string, string> = {
  terracotta: "group-hover:border-terracotta/50",
  herbal: "group-hover:border-herbal/50",
  bark: "group-hover:border-bark/40",
};

const subNav: { to: string; labelKey: string; icon: ComponentType<{ className?: string }>; end: boolean }[] = [
  { to: "/explorer/compound", labelKey: "home", icon: Home, end: true },
  { to: "/explorer/compound/graph", labelKey: "graphView", icon: Network, end: true },
  { to: "/explorer/compound/table", labelKey: "tableView", icon: TableIcon, end: true },
];

const CompoundExplorer = () => {
  
  const [query, setQuery] = useState("");
  const [showSuggest, setShowSuggest] = useState(false);
  const [isDropdownOpen, setIsDropdownOpen] = useState(false);
  const [searchCategory, setSearchCategory] = useState<string>("all");
  const navigate = useNavigate();

  const getPlaceholderText = () => {
    {
      switch (searchCategory) {
        case "Compound":
          return "Search compounds...";
        case "ShodhanaMethod":
          return "Search Shodhana methods...";
        case "ToxicSubstance":
          return "Search toxic substances...";
        case "PurifiedCompound":
          return "Search purified compounds...";
        case "ShodhanaType":
          return "Search Shodhana types...";
        case "Media":
          return "Search Shodhana media...";
        case "ConfirmationStudy":
          return "Search confirmation studies...";
        case "Indication":
          return "Search indications/diseases...";
        case "AyurvedicFormulation":
          return "Search Ayurvedic formulations...";
        case "Process":
          return "Search process steps...";
        case "all":
        default:
          return "Search compounds, shodhana methods, toxic substances, media, processes...";
      }
    }
  };

  const getSuggestionTiles = () => {
    switch (searchCategory) {
      case "Compound":
        return [
          { query: "Tin", display: ("Tin") },
          { query: "Mercury", display: ("Mercury") },
          { query: "Sulphur", display: ("Sulphur") }
        ];
      case "ShodhanaMethod":
        return [
          { query: "Godugdha Shodhana", display: "Godugdha Shodhana" },
          { query: "Ashta Samskara", display: "Ashta Samskara" },
          { query: "Swedana", display: "Swedana" }
        ];
      case "ToxicSubstance":
        return [
          { query: "Aconitine", display: "Aconitine" },
          { query: "Free Mercury", display: "Free Mercury" },
          { query: "Bhilawanol", display: "Bhilawanol" }
        ];
      case "PurifiedCompound":
        return [
          { query: "Shuddha Vatsanabha", display: "Shuddha Vatsanabha" },
          { query: "Shuddha Parada", display: "Shuddha Parada" },
          { query: "Shuddha Bhallataka", display: "Shuddha Bhallataka" }
        ];
      case "ShodhanaType":
        return [
          { query: "Samanya Shodhana", display: "Samanya Shodhana" },
          { query: "Vishesha Shodhana", display: "Vishesha Shodhana" }
        ];
      case "Media":
        return [
          { query: "Godugdha", display: "Godugdha" },
          { query: "Triphala Kwatha", display: "Triphala Kwatha" },
          { query: "Ishtika Choorna", display: "Ishtika Choorna" }
        ];
      case "ConfirmationStudy":
        return [
          { query: "HPTLC Aconitine Study 2018", display: "HPTLC Aconitine Study" },
          { query: "Mercury Speciation Study 2020", display: "Mercury Speciation Study" },
          { query: "Bhilawanol Reduction 2019", display: "Bhilawanol Reduction" }
        ];
      case "Indication":
        return [
          { query: "Amavata", display: "Amavata" },
          { query: "Kushtha", display: "Kushtha" }
        ];
      case "AyurvedicFormulation":
        return [
          { query: "Mahavatavidhwansa Rasa", display: "Mahavatavidhwansa Rasa" },
          { query: "Bhallataka Rasayana", display: "Bhallataka Rasayana" }
        ];
      case "Process":
        return [
          { query: "Heating", display: "Heating" },
          { query: "Mardana", display: "Mardana" },
          { query: "Filtration", display: "Filtration" }
        ];
      case "all":
      default:
        return [
          { query: "Tin", display: "Tin" },
          { query: "Copper", display: "Copper" },
          { query: "Strychnine", display: "Strychnine" },
          { query: "Dhalana", display: "Dhalana" }
        ];
    }
  };

  useEffect(() => {
    const handleOutsideClick = (e: MouseEvent) => {
      const container = document.getElementById("compound-search-container");
      if (container && !container.contains(e.target as Node)) {
        setShowSuggest(false);
        setIsDropdownOpen(false);
      }
    };
    document.addEventListener("mousedown", handleOutsideClick);
    return () => document.removeEventListener("mousedown", handleOutsideClick);
  }, []);

  const { data: counts } = useCounts("compound");

  const categories = useMemo(() => {


    const blurbsEn: Record<string, string> = {
      Compound: "Phytochemicals and metallic compounds in the knowledge graph.",
      ShodhanaMethod: "Classical purification procedures applied to raw substances.",
      ToxicSubstance: "Raw substances classified as toxic before purification.",
      PurifiedCompound: "Compounds obtained after Shodhana with reduced toxicity profile.",
      ShodhanaType: "Categories of purification (Samanya, Vishesha, etc.).",
      Media: "Liquids and substances used as Shodhana media (Triphala kwatha, Godugdha…).",
      ConfirmationStudy: "Published studies confirming purification efficacy or safety.",
      Indication: "Conditions treated by purified compounds.",
      AyurvedicFormulation: "Preparations using purified compounds as ingredients.",
      Process: "Procedural steps and unit operations of Shodhana protocols.",
    };

    return CATEGORY_DEFS.map((c) => ({
      ...c,
      name: (c.nameKey),
      count: counts?.[c.countKey] ?? 0,
      blurb: blurbsEn[c.nameKey],
    }));
  }, [counts]);

  const { data: suggestions = [] } = useSearch("compound", query, 8, undefined, searchCategory === "all" ? undefined : searchCategory);

  const graphUrl = (nodeId: string) => entityGraphUrl({ id: nodeId, type: suggestions.find(node => node.id === nodeId)?.type ?? '' }, 'compound');

  const goToSearch = () => {
    if (!query.trim()) return;
    const typeParam = searchCategory !== "all" ? `&type=${searchCategory}` : "";
    navigate(`/search?q=${encodeURIComponent(query.trim())}&tab=compound${typeParam}`);
  };

  const onKey = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Enter") goToSearch();
    if (e.key === "Escape") {
      setShowSuggest(false);
      setIsDropdownOpen(false);
    }
  };

  return (
    <section className="container py-10 md:py-14 animate-fade-in">
      {/* Sub-nav */}
      <div className="mb-10 flex justify-center">
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
                    : "text-muted-foreground hover:text-foreground hover:bg-secondary/70"
                )
              }
            >
              <Icon className="h-3.5 w-3.5" />
              {(uiLabels[labelKey] ?? labelKey)}
            </NavLink>
          ))}
        </nav>
      </div>

      {/* Title + search */}
      <div className="text-center max-w-3xl mx-auto">
        <h1 className="font-display text-4xl md:text-5xl font-bold text-foreground tracking-tight">
          Compound Domain
        </h1>
        <p className="mt-3 text-sm md:text-base text-muted-foreground leading-relaxed">
          <strong className="text-foreground/90">
            {"Compound Shodhana"}
          </strong>{" "}
          {"traces classical purification of toxic and raw substances — mapping methods, media, processes and confirmation studies that yield safer, formulation-ready compounds."}
        </p>

        <div id="compound-search-container" className="mt-8 relative max-w-3xl mx-auto w-full z-20">
          <div className="flex items-stretch w-full rounded-2xl border-2 border-border bg-card/60 backdrop-blur-md shadow-card focus-within:border-herbal/70 focus-within:ring-4 focus-within:ring-herbal/10 hover:border-border/80 transition-all">
            {/* Custom Dropdown Selector */}
            <div className="relative shrink-0 flex">
              <button
                type="button"
                onClick={() => setIsDropdownOpen(!isDropdownOpen)}
                className="flex items-center justify-between gap-2 px-4 py-4 bg-primary hover:bg-primary/95 text-primary-foreground font-bold text-xs md:text-sm transition-all focus:outline-none border-r border-primary/20 min-w-[150px] md:min-w-[200px] rounded-l-[14px]"
              >
                <span>
                  {searchCategory === "all"
                    ? ("All Categories")
                    : categories.find(c => c.key === searchCategory)?.name || searchCategory}
                </span>
                <ChevronDown className={cn("h-4 w-4 transition-transform duration-200 shrink-0", isDropdownOpen && "rotate-180")} />
              </button>

              {/* Dropdown Options List */}
              {isDropdownOpen && (
                <div className="absolute top-[108%] left-0 w-56 rounded-xl border border-border bg-card shadow-elevated z-50 p-1.5 text-left animate-in fade-in slide-in-from-top-2 duration-150 backdrop-blur-md">
                  <button
                    type="button"
                    onClick={() => {
                      setSearchCategory("all");
                      setIsDropdownOpen(false);
                    }}
                    className={cn(
                      "w-full text-left px-3 py-2 rounded-lg text-xs md:text-sm font-semibold transition-smooth mb-0.5",
                      searchCategory === "all"
                        ? "bg-primary/10 text-primary"
                        : "text-foreground hover:bg-secondary/60"
                    )}
                  >
                    {"All Categories"}
                  </button>
                  {categories.map((opt) => (
                    <button
                      key={opt.key}
                      type="button"
                      onClick={() => {
                        setSearchCategory(opt.key);
                        setIsDropdownOpen(false);
                      }}
                      className={cn(
                        "w-full text-left px-3 py-2 rounded-lg text-xs md:text-sm font-semibold transition-smooth mb-0.5 last:mb-0",
                        searchCategory === opt.key
                          ? "bg-primary/10 text-primary"
                          : "text-foreground hover:bg-secondary/60"
                      )}
                    >
                      {opt.name}
                    </button>
                  ))}
                </div>
              )}
            </div>
            
            {/* Search icon and Input wrapper */}
            <div className="relative min-w-0 flex-1 flex items-center">
              <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
              <input
                value={query}
                onChange={(e) => {
                  setQuery(e.target.value);
                  setShowSuggest(true);
                }}
                onFocus={() => setShowSuggest(true)}
                onKeyDown={onKey}
                placeholder={getPlaceholderText()}
                className="w-full min-w-0 bg-transparent pl-9 md:pl-12 pr-2 md:pr-32 py-4 md:py-5 text-xs md:text-base outline-none text-bark font-semibold placeholder:text-muted-foreground/60"
              />
            </div>

            {/* Submit Button */}
            <div className="relative flex items-center pr-3 shrink-0">
              <button
                onClick={goToSearch}
                disabled={!query.trim()}
                className="px-5 py-2.5 rounded-xl bg-primary text-primary-foreground text-sm font-semibold hover:bg-primary/90 transition-smooth shadow-sm disabled:opacity-60"
              >
                {"Search"}
              </button>
            </div>
          </div>

          {showSuggest && suggestions.length > 0 && (
            <div className="absolute left-0 right-0 top-full mt-2 z-30 rounded-xl border border-border bg-popover shadow-elevated overflow-hidden text-left animate-fade-in">
              <ul className="max-h-72 overflow-y-auto py-1">
                {suggestions.map((n) => (
                  <li key={n.id}>
                    <button
                      onMouseDown={(e) => {
                        e.preventDefault();
                        navigate(graphUrl(n.id));
                      }}
                      className="w-full flex items-center gap-3 px-4 py-2 text-left hover:bg-secondary/60 transition-smooth"
                    >
                      <span
                        className="h-2.5 w-2.5 rounded-full shrink-0"
                        style={{ background: C_NODE_COLORS[n.type as keyof typeof C_NODE_COLORS] || "hsl(200, 20%, 50%)" }}
                      />
                      <div className="flex-1 min-w-0">
                        <div className="text-sm font-medium text-foreground truncate">{(n.label)}</div>
                        <div className="text-[10px] uppercase tracking-wider text-muted-foreground">
                          {(n.type)}
                        </div>
                      </div>
                    </button>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>

        {/* Dynamic suggestion tiles */}
        <div className="mt-5 flex flex-wrap items-center justify-center gap-2 text-xs text-muted-foreground max-w-3xl mx-auto w-full">
          <span className="font-semibold text-bark flex items-center gap-1.5 animate-pulse">
            <Sparkles className="h-3.5 w-3.5 text-terracotta" />
            {"Try searching:"}
          </span>
          {getSuggestionTiles().map((tag) => (
            <button
              key={tag.query}
              onClick={() => {
                setQuery(tag.query);
                const typeParam = searchCategory !== "all" ? `&type=${searchCategory}` : "";
                navigate(`/search?q=${encodeURIComponent(tag.query)}&tab=compound${typeParam}`);
              }}
              className="px-3 py-1 rounded-full border border-border/80 bg-card hover:bg-secondary hover:border-herbal/30 hover:text-bark transition-smooth shadow-sm font-semibold text-[11px] text-muted-foreground"
            >
              {tag.display}
            </button>
          ))}
        </div>
      </div>

      {/* Categories */}
      <div className="mt-14">
        <div className="flex items-end justify-between mb-5">
          <div>
            <h2 className="font-display text-xl font-semibold text-foreground">
              {"Browse by category"}
            </h2>
            <p className="text-sm text-muted-foreground mt-0.5">
              {"Open any category to view its table with connection statistics."}
            </p>
          </div>
          <span className="hidden md:inline text-xs text-muted-foreground">
            {categories.length} {"categories"}
          </span>
        </div>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
          {categories.map(({ key, countKey, name, count, blurb, icon: Icon, accent }) => (
            <button
              key={key}
              onClick={() => navigate(`/explorer/compound/table?category=${countKey}`)}
              className={cn(
                "group relative text-left flex flex-col rounded-xl border border-border bg-card p-5 shadow-card transition-smooth hover:shadow-elevated hover:-translate-y-0.5",
                accentBorder[accent]
              )}
            >
              <div className="flex items-start justify-between mb-4">
                <span
                  className={cn(
                    "flex h-10 w-10 items-center justify-center rounded-lg transition-smooth",
                    accentIcon[accent]
                  )}
                >
                  <Icon className="h-4.5 w-4.5" strokeWidth={1.8} />
                </span>
                <div className="text-right">
                  <div className="font-display text-2xl font-semibold text-foreground leading-none">
                    {count.toLocaleString()}
                  </div>
                  <div className="text-[10px] uppercase tracking-[0.14em] text-muted-foreground mt-1">
                    {"entries"}
                  </div>
                </div>
              </div>

              <h3 className="font-display text-base font-semibold text-foreground">
                {name}
              </h3>
              <p className="mt-1.5 text-xs text-muted-foreground leading-relaxed flex-1">
                {blurb}
              </p>

              <div className="mt-4 pt-3 border-t border-border/70 flex items-center justify-between text-[11px]">
                <span className="text-muted-foreground">
                  {"Opens table · connections"}
                </span>
                <ArrowRight className="h-3.5 w-3.5 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-foreground" />
              </div>
            </button>
          ))}
        </div>
      </div>
    </section>
  );
};

export default CompoundExplorer;
