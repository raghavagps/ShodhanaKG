import { entityGraphUrl } from '@/lib/entityNavigation';
import { useState, KeyboardEvent, useMemo, useEffect } from "react";
import { NavLink, useNavigate } from "react-router-dom";
import {
  Search,
  Stethoscope,
  Activity,
  ScrollText,
  Flame,
  HeartPulse,
  FlaskRound,
  ClipboardList,
  ArrowRight,
  Home,
  Network,
  Table as TableIcon,
  ChevronDown,
  Sparkles,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { NODE_TYPE_LABEL, NODE_COLORS } from "@/data/diseaseGraph";
import { useSearch, useCounts } from "@/lib/api";


const accentIcon: Record<string, string> = {
  terracotta:
    "text-terracotta bg-terracotta/10 group-hover:bg-terracotta group-hover:text-primary-foreground",
  herbal:
    "text-herbal bg-herbal/10 group-hover:bg-herbal group-hover:text-primary-foreground",
  bark: "text-bark bg-bark/10 group-hover:bg-bark group-hover:text-primary-foreground",
};

const accentBorder: Record<string, string> = {
  terracotta: "group-hover:border-terracotta/50",
  herbal: "group-hover:border-herbal/50",
  bark: "group-hover:border-bark/40",
};

const subNav = [
  { to: "/explorer/disease", label: "Home", icon: Home, end: true },
  { to: "/explorer/disease/graph", label: "Graph View", icon: Network, end: true },
  { to: "/explorer/disease/table", label: "Table View", icon: TableIcon, end: true },
];

const DiseaseExplorer = () => {
  
  const [query, setQuery] = useState("");
  const [showSuggest, setShowSuggest] = useState(false);
  const [isDropdownOpen, setIsDropdownOpen] = useState(false);
  const [searchCategory, setSearchCategory] = useState<string>("all");
  const navigate = useNavigate();

  const getPlaceholderText = () => {
    {
      switch (searchCategory) {
        case "Disease":
          return "Search diseases...";
        case "Dosha":
          return "Search doshas...";
        case "Symptom":
          return "Search symptoms...";
        case "AyurvedicDiagnosis":
          return "Search Ayurvedic diagnoses...";
        case "Therapy":
          return "Search therapies...";
        case "Formulation":
          return "Search formulations...";
        case "AssessmentParameter":
          return "Search assessment parameters...";
        case "all":
        default:
          return "Search diseases, symptoms, therapies, formulations, dosha, assessment parameters...";
      }
    }
  };

  const getSuggestionTiles = () => {
    switch (searchCategory) {
      case "Disease":
        return [
          { query: "Rheumatoid Arthritis", display: "Rheumatoid Arthritis" },
          { query: "Type 2 Diabetes", display: "Type 2 Diabetes" },
          { query: "Bronchial Asthma", display: "Bronchial Asthma" }
        ];
      case "Dosha":
        return [
          { query: "Vata", display: "Vata" },
          { query: "Pitta", display: "Pitta" },
          { query: "Kapha", display: "Kapha" }
        ];
      case "Symptom":
        return [
          { query: "Joint Pain", display: "Joint Pain" },
          { query: "Fever", display: "Fever" },
          { query: "Polyuria", display: "Polyuria" }
        ];
      case "AyurvedicDiagnosis":
        return [
          { query: "Amavata", display: "Amavata" },
          { query: "Madhumeha", display: "Madhumeha" },
          { query: "Tamaka Shvasa", display: "Tamaka Shvasa" }
        ];
      case "Therapy":
        return [
          { query: "Virechana", display: "Virechana" },
          { query: "Basti", display: "Basti" },
          { query: "Nasya", display: "Nasya" }
        ];
      case "Formulation":
        return [
          { query: "Simhanada Guggulu", display: "Simhanada Guggulu" },
          { query: "Arogyavardini Vati", display: "Arogyavardini Vati" },
          { query: "Triphala Churna", display: "Triphala Churna" }
        ];
      case "AssessmentParameter":
        return [
          { query: "DAS-28 Score", display: "DAS-28 Score" },
          { query: "HbA1c", display: "HbA1c" },
          { query: "PEFR", display: "PEFR" }
        ];
      case "all":
      default:
        return [
          { query: "Rheumatoid Arthritis", display: "Rheumatoid Arthritis" },
          { query: "Amavata", display: "Amavata" },
          { query: "Basti", display: "Basti" },
          { query: "HbA1c", display: "HbA1c" }
        ];
    }
  };

  useEffect(() => {
    const handleOutsideClick = (e: MouseEvent) => {
      const container = document.getElementById("disease-search-container");
      if (container && !container.contains(e.target as Node)) {
        setShowSuggest(false);
        setIsDropdownOpen(false);
      }
    };
    document.addEventListener("mousedown", handleOutsideClick);
    return () => document.removeEventListener("mousedown", handleOutsideClick);
  }, []);

  const { data: counts } = useCounts("disease");

  const categories = useMemo(() => {


    return [
      { key: "Disease", name: ("Disease"), count: counts?.Disease ?? 0, blurb: "Clinical conditions with cross-linked Ayurvedic & biomedical evidence.", icon: Stethoscope, accent: "terracotta" as const },
      { key: "Symptom", name: ("Symptom"), count: counts?.Symptom ?? 0, blurb: "Reported symptom phenotypes mapped to diseases and doshas.", icon: Activity, accent: "herbal" as const },
      { key: "AyurvedicDiagnosis", name: ("Ayurvedic Diagnosis"), count: counts?.AyurvedicDiagnosis ?? 0, blurb: "Classical diagnostic categories (Nidana, Roga) with modern correlates.", icon: ScrollText, accent: "bark" as const },
      { key: "Dosha", name: ("Dosha"), count: counts?.Dosha ?? 0, blurb: "Vata, Pitta, Kapha — constitutional links to disease and therapy.", icon: Flame, accent: "terracotta" as const },
      { key: "Therapy", name: ("Therapy"), count: counts?.Therapy ?? 0, blurb: "Panchakarma and classical procedures linked to indications.", icon: HeartPulse, accent: "herbal" as const },
      { key: "Formulation", name: ("Formulation"), count: counts?.Formulation ?? 0, blurb: "Classical & proprietary preparations with ingredients and uses.", icon: FlaskRound, accent: "bark" as const },
      { key: "AssessmentParameter", name: ("Assessment Parameter"), count: counts?.AssessmentParameter ?? 0, blurb: "Standardized scales and clinical indicators used in studies.", icon: ClipboardList, accent: "terracotta" as const },
    ];
  }, [counts]);

  const { data: suggestions = [] } = useSearch("disease", query, 8, undefined, searchCategory === "all" ? undefined : searchCategory);

  const graphUrl = (nodeId: string) => entityGraphUrl({ id: nodeId, type: suggestions.find(node => node.id === nodeId)?.type ?? '' }, 'disease');

  const goToSearch = () => {
    if (!query.trim()) return;
    const typeParam = searchCategory !== "all" ? `&type=${searchCategory}` : "";
    navigate(`/search?q=${encodeURIComponent(query.trim())}&tab=disease${typeParam}`);
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
                    : "text-muted-foreground hover:text-foreground hover:bg-secondary/70"
                )
              }
            >
              <Icon className="h-3.5 w-3.5" />
              {label}
            </NavLink>
          ))}
        </nav>
      </div>

      {/* Title + search */}
      <div className="text-center max-w-3xl mx-auto">
        <h1 className="font-display text-4xl md:text-5xl font-bold text-foreground tracking-tight">
          Disease Domain
        </h1>
        <p className="mt-3 text-sm md:text-base text-muted-foreground leading-relaxed">
          {"Disease Shodhana explores how clinical conditions connect to Ayurvedic diagnostics, doshas, therapies and formulations — linking symptoms and assessment parameters to evidence-backed interventions."}
        </p>

        <div id="disease-search-container" className="mt-8 relative max-w-3xl mx-auto w-full z-20">
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
                Search
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
                        style={{ background: NODE_COLORS[n.type as keyof typeof NODE_COLORS] || "hsl(200, 20%, 50%)" }}
                      />
                      <div className="flex-1 min-w-0">
                        <div className="text-sm font-medium text-foreground truncate">{n.label}</div>
                        <div className="text-[10px] uppercase tracking-wider text-muted-foreground">
                          {NODE_TYPE_LABEL[n.type as keyof typeof NODE_TYPE_LABEL] || n.type}
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
                navigate(`/search?q=${encodeURIComponent(tag.query)}&tab=disease${typeParam}`);
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
              Browse by category
            </h2>
            <p className="text-sm text-muted-foreground mt-0.5">
              Open any category to view its table with relationship & connection statistics.
            </p>
          </div>
          <span className="hidden md:inline text-xs text-muted-foreground">
            {categories.length} categories
          </span>
        </div>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {categories.map(({ key, name, count, blurb, icon: Icon, accent }) => (
            <NavLink
              key={key}
              to={`/explorer/disease/table?category=${key}`}
              className={cn(
                "group relative flex flex-col rounded-xl border border-border bg-card p-5 shadow-card transition-smooth hover:shadow-elevated hover:-translate-y-0.5",
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
                    entries
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
                  Opens table · relationships
                </span>
                <ArrowRight className="h-3.5 w-3.5 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-foreground" />
              </div>
            </NavLink>
          ))}
        </div>
      </div>
    </section>
  );
};

export default DiseaseExplorer;

