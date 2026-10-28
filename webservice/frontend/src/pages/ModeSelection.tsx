import { uiLabels } from '@/data/uiLabels';
import { entityGraphUrl } from '@/lib/entityNavigation';
import { TYPE_LABELS, TYPE_COLORS } from "@/data/entityTypes";
import { Link, useNavigate } from "react-router-dom";
import { Activity, FlaskConical, Network, ArrowUpRight, Leaf, Sparkles, Database, GitBranch, BookOpen, Search, AlertCircle, ChevronDown } from "lucide-react";
import { useKgStats, useSearch } from "@/lib/api";
import type { ApiNode } from "@/lib/types";
import { useState, useEffect, useRef } from "react";

import { cn } from "@/lib/utils";

const modes = [
  {
    to: "/explorer/disease",
    icon: Activity,
    title: "Disease",
    full: "Disease-ShodhanaKG Explorer",
    desc: "Trace disease through Ayurvedic diagnosis, dosha and therapy",
    accent: "terracotta",
    tag: "Clinical",
  },
  {
    to: "/explorer/compound",
    icon: FlaskConical,
    title: "Compound",
    full: "Compound-ShodhanaKG Explorer",
    desc: "Follow toxic substances through Shodhana to safe, usable compounds",
    accent: "herbal",
    tag: "Molecular",
  },
  {
    to: "/explorer/integrated",
    icon: Network,
    title: "Integrated",
    full: "Integrated-ShodhanaKG Explorer",
    desc: "Cross the disease-compound boundary to find shared Shodhana pathways",
    accent: "bark",
    tag: "Holistic",
  },
] as const;

const accentRing: Record<string, string> = {
  terracotta: "group-hover:border-terracotta/50",
  herbal: "group-hover:border-herbal/50",
  bark: "group-hover:border-bark/40",
};
const accentIcon: Record<string, string> = {
  terracotta: "text-terracotta bg-terracotta/10 group-hover:bg-terracotta group-hover:text-primary-foreground",
  herbal: "text-herbal bg-herbal/10 group-hover:bg-herbal group-hover:text-primary-foreground",
  bark: "text-bark bg-bark/10 group-hover:bg-bark group-hover:text-primary-foreground",
};
const accentTag: Record<string, string> = {
  terracotta: "text-terracotta",
  herbal: "text-herbal",
  bark: "text-bark",
};

function formatStatNumber(n: number): string {
  return n.toLocaleString();
}


const AnimatedCounter = ({ value, duration = 2000 }: { value: number; duration?: number }) => {
  const [count, setCount] = useState(0);
  const ref = useRef<HTMLSpanElement>(null);
  
  useEffect(() => {
    const observer = new IntersectionObserver((entries) => {
      if (entries[0].isIntersecting) {
        let startTimestamp: number;
        const step = (timestamp: number) => {
          if (!startTimestamp) startTimestamp = timestamp;
          const progress = Math.min((timestamp - startTimestamp) / duration, 1);
          const easeProgress = 1 - Math.pow(1 - progress, 4);
          setCount(Math.floor(easeProgress * value));
          if (progress < 1) {
            window.requestAnimationFrame(step);
          } else {
            setCount(value);
          }
        };
        window.requestAnimationFrame(step);
        observer.disconnect(); // Run once
      }
    }, { threshold: 0.1 });
    
    if (ref.current) {
      observer.observe(ref.current);
    }
    
    return () => observer.disconnect();
  }, [value, duration]);

  return <span ref={ref}>{formatStatNumber(count)}</span>;
};

const ModeSelection = () => {
  const { data: kgStats, isLoading: statsLoading } = useKgStats();
  
  const [searchQuery, setSearchQuery] = useState("");
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(-1);
  const [searchMode, setSearchMode] = useState<"disease" | "compound" | "integrated">("disease");
  const [isDropdownOpen, setIsDropdownOpen] = useState(false);
  const navigate = useNavigate();

  const { data: searchResults = [], isFetching: isSearching } = useSearch(searchMode, searchQuery, 8);
  const suggestions = searchQuery.trim() ? searchResults : [];

  const getPlaceholderText = () => {
    {
      switch (searchMode) {
        case "disease":
          return "Search disease, Dosha, Symptoms, or Shodhana therapy...";
        case "compound":
          return "Search compound, toxic substance, media, or Shodhana process...";
        case "integrated":
        default:
          return "Search disease, compound, or Shodhana process...";
      }
    }
  };

  const getSuggestionTiles = () => {
    switch (searchMode) {
      case "disease":
        return [
          { query: "Psoriasis", display: "Psoriasis" },
          { query: "Rheumatoid Arthritis", display: "Rheumatoid Arthritis" },
          { query: "Swelling", display: "Swelling" },
          { query: "Basti", display: "Basti" },
        ];
      case "compound":
        return [
          { query: "Tin", display: "Tin" },
          { query: "Copper", display: "Copper" },
          { query: "Strychnine", display: "Strychnine" },
          { query: "Dhalana", display: "Dhalana" },
        ];
      case "integrated":
      default:
        return [
          { query: "Shodhita Chitraka", display: "Shodhita Chitraka" },
          { query: "Eczema", display: "Eczema" },
          { query: "Lauha Bhasma", display: "Lauha Bhasma" },
          { query: "Diabetes Mellitus", display: "Diabetes Mellitus" },
        ];
    }
  };

  const handleSelectSuggestion = (node: ApiNode) => {
    navigate(entityGraphUrl(node));
  };

  useEffect(() => {
    setHighlightedIndex(-1);
  }, [searchQuery, searchMode]);

  useEffect(() => {
    const handleOutsideClick = (e: MouseEvent) => {
      const container = document.getElementById("global-search-container");
      if (container && !container.contains(e.target as Node)) {
        setShowSuggestions(false);
        setIsDropdownOpen(false);
      }
    };
    document.addEventListener("mousedown", handleOutsideClick);
    return () => document.removeEventListener("mousedown", handleOutsideClick);
  }, []);

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (!showSuggestions || suggestions.length === 0) return;

    if (e.key === "ArrowDown") {
      e.preventDefault();
      setHighlightedIndex((prev) => Math.min(prev + 1, suggestions.length - 1));
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      setHighlightedIndex((prev) => Math.max(prev - 1, 0));
    } else if (e.key === "Enter") {
      if (highlightedIndex >= 0 && highlightedIndex < suggestions.length) {
        e.preventDefault();
        handleSelectSuggestion(suggestions[highlightedIndex]);
      }
    } else if (e.key === "Escape") {
      setShowSuggestions(false);
    }
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      const tabParam = searchMode === "disease" ? "&tab=disease" : searchMode === "compound" ? "&tab=compound" : "";
      navigate(`/search?q=${encodeURIComponent(searchQuery.trim())}${tabParam}`);
    }
  };

  const statCards = [
    {
      label: "Total Nodes",
      value: kgStats?.totalNodes,
      icon: Database,
      accent: "text-herbal",
      bg: "bg-herbal/10",
      border: "border-herbal/20",
      description: "Vertices across all knowledge domains",
    },
    {
      label: "Total Relations",
      value: kgStats?.totalRelations,
      icon: GitBranch,
      accent: "text-terracotta",
      bg: "bg-terracotta/10",
      border: "border-terracotta/20",
      description: "Edges connecting entities in the graph",
    },
    {
      label: "Total Studies",
      value: kgStats?.totalStudies,
      icon: BookOpen,
      accent: "text-bark",
      bg: "bg-bark/10",
      border: "border-bark/20",
      description: "unique studies count",
    },
  ];

  return (
    <div className="flex flex-col">
      {/* Disclaimer */}
      <div className="w-full bg-red-50 border-b border-red-100 py-2.5 overflow-hidden select-none">
        <style dangerouslySetInnerHTML={{__html: `
          @keyframes marquee {
            0% { transform: translate3d(100%, 0, 0); }
            100% { transform: translate3d(-100%, 0, 0); }
          }
          .animate-marquee {
            display: inline-flex;
            white-space: nowrap;
            animation: marquee 30s linear infinite;
          }
          .animate-marquee:hover {
            animation-play-state: paused;
          }
        `}} />
        <div className="w-full overflow-hidden relative">
          <div className="animate-marquee items-center gap-2 cursor-pointer w-full">
            <AlertCircle className="h-4 w-4 text-red-600 shrink-0 inline-block" />
            <p className="text-xs md:text-sm font-medium text-red-700 inline-block">
              <span className="font-bold">{"Disclaimer:"}</span> {"This platform is strictly for academic and research purposes only. For medical advice and diagnosis, please consult a professional."}
            </p>
          </div>
        </div>
      </div>

      <section className="container flex flex-col items-center py-12 md:py-20 animate-fade-in relative overflow-hidden">
        {/* Decorative earthy background accents */}
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 -z-10 opacity-[0.55]"
          style={{
            backgroundImage:
              "radial-gradient(circle at 15% 20%, hsl(var(--herbal) / 0.10), transparent 45%), radial-gradient(circle at 85% 30%, hsl(var(--terracotta) / 0.10), transparent 50%), radial-gradient(circle at 50% 100%, hsl(var(--bark) / 0.08), transparent 55%)",
          }}
        />

        {/* Hero */}
        <div className="text-center max-w-2xl">
          <div className="inline-flex items-center gap-2 rounded-full border border-border/70 bg-card/70 px-3 py-1 text-[11px] uppercase tracking-[0.18em] text-muted-foreground shadow-sm animate-fade-in">
            <Leaf className="h-3 w-3 text-herbal" />
            {"Integrative Ayurvedic purification knowledge graph"}
          </div>

          <h1 className="mt-5 font-display text-5xl md:text-6xl font-bold text-foreground tracking-tight">
            Shodhana<span className="text-herbal">KG</span>
          </h1>

          <p className="mt-5 text-base md:text-lg text-foreground/80 leading-relaxed">
            {"Where classical purification meets modern evidence: explore diseases, compounds and the pathways that connect them."}
          </p>

          {/* Global Search Bar Container */}
          <div id="global-search-container" className="mt-8 relative max-w-2xl mx-auto w-full z-20">
            <form onSubmit={handleSearch} className="flex items-stretch w-full rounded-2xl border-2 border-border bg-card/60 backdrop-blur-md shadow-md focus-within:border-herbal/70 focus-within:ring-4 focus-within:ring-herbal/10 hover:border-border/80 transition-all">
              {/* Custom Dropdown Selector */}
              <div className="relative shrink-0 flex">
                <button
                  type="button"
                  onClick={() => setIsDropdownOpen(!isDropdownOpen)}
                  className="flex items-center justify-between gap-2 px-4 py-4 bg-primary hover:bg-primary/95 text-primary-foreground font-bold text-xs md:text-sm transition-all focus:outline-none border-r border-primary/20 min-w-[140px] md:min-w-[160px] rounded-l-[14px]"
                >
                  <span>
                    {searchMode === "disease"
                      ? ("Disease-Shodhana")
                      : searchMode === "compound"
                      ? ("Compound-Shodhana")
                      : ("Integrated")}
                  </span>
                  <ChevronDown className={cn("h-4 w-4 transition-transform duration-200 shrink-0", isDropdownOpen && "rotate-180")} />
                </button>

                {/* Dropdown Options List */}
                {isDropdownOpen && (
                  <div className="absolute top-[108%] left-0 w-48 rounded-xl border border-border bg-card shadow-elevated z-50 p-1.5 text-left animate-in fade-in slide-in-from-top-2 duration-150 backdrop-blur-md">
                    {[
                      { value: "disease", label: "Disease-Shodhana" },
                      { value: "compound", label: "Compound-Shodhana" },
                      { value: "integrated", label: "Integrated" },
                    ].map((opt) => (
                      <button
                        key={opt.value}
                        type="button"
                        onClick={() => {
                          setSearchMode(opt.value as "disease" | "compound" | "integrated");
                          setIsDropdownOpen(false);
                        }}
                        className={cn(
                          "w-full text-left px-3 py-2 rounded-lg text-xs md:text-sm font-semibold transition-smooth mb-0.5 last:mb-0",
                          searchMode === opt.value
                            ? "bg-primary/10 text-primary"
                            : "text-foreground hover:bg-secondary/60"
                        )}
                      >
                        {opt.label}
                      </button>
                    ))}
                  </div>
                )}
              </div>
              
              {/* Search icon and Input wrapper */}
              <div className="relative flex-1 flex items-center">
                <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => {
                    setSearchQuery(e.target.value);
                    setShowSuggestions(true);
                  }}
                  onFocus={() => setShowSuggestions(true)}
                  onKeyDown={handleKeyDown}
                  placeholder={getPlaceholderText()}
                  className="w-full bg-transparent pl-12 pr-28 py-4 text-xs md:text-base outline-none text-bark font-semibold placeholder:text-muted-foreground/60"
                />
              </div>

              {/* Submit Button */}
              <div className="relative flex items-center pr-3 shrink-0">
                <button
                  type="submit"
                  className="px-5 py-2.5 rounded-xl bg-primary text-primary-foreground text-sm font-semibold hover:bg-primary/90 transition-smooth shadow-sm"
                >
                  {"Search"}
                </button>
              </div>
            </form>

            {/* Dynamic Autocomplete Suggestions Dropdown */}
            {showSuggestions && (isSearching || suggestions.length > 0) && (
              <div className="absolute top-[105%] left-0 w-full rounded-2xl border border-border bg-card shadow-elevated z-50 p-2 text-left animate-in fade-in slide-in-from-top-2 duration-200 max-h-[360px] overflow-y-auto">
                {isSearching && suggestions.length === 0 && (
                  <div className="py-4 px-4 text-sm text-muted-foreground flex items-center justify-center gap-2">
                    <span className="h-4 w-4 border-2 border-herbal/20 border-t-herbal rounded-full animate-spin" />
                    {"Searching suggestions..."}
                  </div>
                )}
                {suggestions.map((item, idx) => {
                  const typeLabel = (TYPE_LABELS[item.type] || item.type);
                  const typeColor = TYPE_COLORS[item.type] || "hsl(200, 20%, 50%)";
                  const isHighlighted = idx === highlightedIndex;

                  return (
                    <button
                      key={item.id}
                      onClick={() => {
                        handleSelectSuggestion(item);
                        setShowSuggestions(false);
                      }}
                      onMouseEnter={() => setHighlightedIndex(idx)}
                      className={`w-full flex items-center justify-between px-4 py-2.5 rounded-xl text-left text-sm font-medium transition-smooth ${
                        isHighlighted ? "bg-secondary text-bark" : "text-foreground hover:bg-secondary/50"
                      }`}
                    >
                      <div className="flex-1 min-w-0 pr-4">
                        <span className="block font-semibold text-bark truncate">{(item.label)}</span>
                        {item.description && (
                          <span className="block text-[11px] text-muted-foreground truncate leading-relaxed">
                            {item.description}
                          </span>
                        )}
                      </div>
                      <span
                        className="px-2 py-0.5 rounded text-[10px] font-bold tracking-wide shrink-0"
                        style={{
                          backgroundColor: typeColor.replace("hsl(", "hsla(").replace(")", ", 0.12)"),
                          color: typeColor,
                        }}
                      >
                        {typeLabel}
                      </span>
                    </button>
                  );
                })}
              </div>
            )}
          </div>

          {/* Popular / Example Searches */}
          <div className="mt-5 flex flex-wrap items-center justify-center gap-2 text-xs text-muted-foreground">
            <span className="font-semibold text-bark flex items-center gap-1.5 animate-pulse">
              <Sparkles className="h-3.5 w-3.5 text-terracotta" />
              {"Try searching:"}
            </span>
            {getSuggestionTiles().map((tag) => (
              <button
                key={tag.query}
                onClick={() => {
                  setSearchQuery(tag.query);
                  const tabParam = searchMode === "disease" ? "&tab=disease" : searchMode === "compound" ? "&tab=compound" : "";
                  navigate(`/search?q=${encodeURIComponent(tag.query)}${tabParam}`);
                }}
                className="px-3 py-1 rounded-full border border-border/80 bg-card hover:bg-secondary hover:border-herbal/30 hover:text-bark transition-smooth shadow-sm font-semibold text-[11px] text-muted-foreground"
              >
                {tag.display}
              </button>
            ))}
          </div>

        </div>

        {/* Abstract Section */}
        <div className="mt-8 w-full max-w-6xl flex flex-col items-center animate-fade-in">
          <div className="w-full bg-card/40 border border-border/80 backdrop-blur-md rounded-2xl p-6 md:p-8 shadow-sm">
            <h3 className="font-display text-2xl font-bold text-bark mb-6 flex items-center gap-2">
              <BookOpen className="h-5 w-5 text-herbal" />
              {"About ShodhanaKG"}
            </h3>
            
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
              <div className="lg:col-span-5 text-sm md:text-base text-foreground/80 leading-relaxed space-y-4">
                {(
                  <>
                    <p>
                      ShodhanaKG is a knowledge graph system that integrates Ayurvedic Shodhana science, spanning both bodily purification through Panchakarma therapies and compound purification through Rasashastra methods, with their disease associations, manually curated from PubMed, Dhara, and the AYUSH Research Portal.
                    </p>
                    <p>
                      The system offers three exploration tools: the <strong>Compound Shodhana Explorer</strong> for purification processes and pharmacological studies, the <strong>Disease Shodhana Explorer</strong> for disease-related therapeutic information, and an <strong>Integrative Explorer</strong> connecting purified compounds to the diseases they treat.
                    </p>
                    <p>
                      By bridging ancient Ayurvedic knowledge with modern knowledge-graph technology, ShodhanaKG offers a structured, evidence-linked resource for researchers in Ayurvedic pharmacology and integrative healthcare.
                    </p>
                  </>
                )}
              </div>

              <div className="lg:col-span-7 w-full">
                {/* Abstract Graphic/Image (Replaced with Graph Animation iframe) */}
                <div className="rounded-2xl border border-border/80 bg-card/60 overflow-hidden shadow-elevated group/img relative transition-all duration-300 hover:shadow-elevated hover:border-herbal/30 aspect-[1200/800] w-full">
                  <iframe 
                    src={`${import.meta.env.BASE_URL}graph_animation.html`} 
                    title="ShodhanaKG Schema and Architecture Flow Animation" 
                    className="w-full h-full border-none pointer-events-none"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* KG Stats cards */}
        <div className="mt-16 w-full max-w-3xl grid gap-4 grid-cols-1 sm:grid-cols-3">
          {statCards.map(({ label, value, icon: Icon, accent, bg, border, description }) => {
            const metricKey = label === 'Total Nodes' ? 'totalNodes' : label === 'Total Relations' ? 'totalRelations' : 'totalStudies';
            const descriptionKey = label === 'Total Nodes' ? 'nodesDescription' : label === 'Total Relations' ? 'relationsDescription' : 'studiesDescription';
            const translatedLabel = uiLabels[metricKey] ?? label;
            const translatedDesc = uiLabels[descriptionKey] ?? description;

            return (
              <div
                key={label}
                className={`flex flex-col items-center text-center rounded-xl border ${border} ${bg} px-5 py-5 shadow-sm`}
              >
                <span className={`flex h-9 w-9 items-center justify-center rounded-lg bg-card shadow-sm mb-3 ${accent}`}>
                  <Icon className="h-4 w-4" strokeWidth={1.8} />
                </span>
                <span className={`font-display text-3xl font-bold tabular-nums ${accent}`}>
                  {value == null ? "Unavailable" : <AnimatedCounter value={value} />}
                </span>
                <span className="mt-1 text-sm font-semibold text-foreground">{translatedLabel}</span>
                {translatedDesc ? <span className="mt-0.5 text-[11px] text-muted-foreground leading-snug">{translatedDesc}</span> : null}
              </div>
            );
          })}
        </div>

        {/* Section Divider & Header */}
        <div className="mt-16 mb-8 text-center max-w-2xl animate-fade-in">
          <div className="mx-auto h-px w-24 bg-gradient-to-r from-transparent via-border to-transparent mb-6" />
          <h2 className="font-display text-3xl font-bold text-bark tracking-tight">
            {"Choose Your Path Through the Graph"}
          </h2>
          <p className="mt-3 text-sm md:text-base text-muted-foreground leading-relaxed">
            {"Choose a domain path below to start visualizing classical Ayurvedic pathways, clinical evidence, and molecular properties."}
          </p>
        </div>

        {/* Mode cards */}
        <div className="mt-4 w-full max-w-6xl grid gap-6 md:grid-cols-3">
          {modes.map(({ to, icon: Icon, full, desc, accent, tag }) => {
            const translatedTitle = full.startsWith("Disease") 
              ? "Disease Explorer" 
              : full.startsWith("Compound") 
              ? "Compound Explorer" 
              : "Integrated Explorer";

            const translatedAction = full.startsWith("Disease") 
              ? "Open Disease mode" 
              : full.startsWith("Compound") 
              ? "Open Compound mode" 
              : "Open Integrated mode";

            // Custom Hindi descriptions for modes to make it completely translated and professional
            const translatedDescMap: Record<string, string> = {
              "Disease": "आयुर्वेदिक निदान, दोष, चिकित्सा और योग (फॉर्मूलेशन) के माध्यम से नैदानिक स्थितियों का अन्वेषण करें।",
              "Compound": "विषाक्त द्रव्यों को शोधन विधियों, शोधन माध्यमों और प्रक्रियाओं के माध्यम से सुरक्षित द्रव्य में बदलते हुए देखें।",
              "Integrated": "क्रॉस-डोमेन शोधन मार्गों को सतह पर लाने के लिए रोग और द्रव्य आलेखों को एक साथ जोड़ें।",
            };
            const translatedDesc = desc;

            return (
              <Link
                key={to}
                to={to}
                className={`group relative flex flex-col rounded-xl border border-border bg-card p-7 shadow-card transition-all duration-300 hover:shadow-elevated hover:-translate-y-1 ${accentRing[accent]} animate-fade-in`}
              >
                <div className="flex items-start justify-between mb-5">
                  <span
                    className={`flex h-12 w-12 items-center justify-center rounded-lg transition-all duration-300 group-hover:scale-110 ${accentIcon[accent]}`}
                  >
                    <Icon className="h-5 w-5" strokeWidth={1.8} />
                  </span>
                  <span className={`text-[10px] uppercase tracking-[0.16em] font-semibold ${accentTag[accent]}`}>
                    {(tag)}
                  </span>
                </div>

                <h2 className="font-display text-xl md:text-2xl font-semibold text-foreground leading-tight">
                  {translatedTitle}
                </h2>

                <p className="mt-3 text-sm text-muted-foreground leading-relaxed flex-1">
                  {translatedDesc}
                </p>

                <div className="mt-6 pt-4 border-t border-border/70 flex items-center justify-between">
                  <span className="text-xs font-semibold text-foreground/80">{translatedAction}</span>
                  <ArrowUpRight className="h-4 w-4 text-muted-foreground transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-foreground" />
                </div>
              </Link>
            );
          })}
        </div>
      </section>
    </div>
  );
};

export default ModeSelection;
