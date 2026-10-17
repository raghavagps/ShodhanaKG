import { useState, useEffect, useMemo } from "react";
import { entityGraphUrl } from '@/lib/entityNavigation';
import { Link, useSearchParams } from "react-router-dom";
import { Search, Filter, X, ChevronRight, Activity, FlaskConical, Network } from "lucide-react";
import { cn } from "@/lib/utils";
import { toast } from "sonner";
import { searchApi } from "@/lib/api";

import type { ApiNode } from "@/lib/types";


type SearchTab = "all" | "disease" | "compound";
type EntityType = "Disease" | "Symptom" | "AyurvedicDiagnosis" | "Dosha" | "Therapy" | "Formulation" | "AssessmentParameter" | "Compound" | "ShodhanaMethod" | "ToxicSubstance" | "PurifiedCompound" | "Media" | "ConfirmationStudy" | "Process" | "ShodhanaType" | "Indication" | "AyurvedicFormulation";

interface SearchResult extends ApiNode {
  domain: "disease" | "compound" | "integrated";
}

const TABS: { key: SearchTab; label: string; icon: typeof Activity }[] = [
  { key: "all", label: "All", icon: Network },
  { key: "disease", label: "Disease", icon: Activity },
  { key: "compound", label: "Compound", icon: FlaskConical },
];

const TYPE_TO_DOMAIN: Record<string, SearchTab> = {
  Disease: "disease",
  Symptoms: "disease",
  Symptom: "disease",
  Ayurvedic_Diagnosis: "disease",
  AyurvedicDiagnosis: "disease",
  Dosha: "disease",
  Therapy: "disease",
  Formulations: "disease",
  Formulation: "disease",
  Assessment_Parameter: "disease",
  AssessmentParameter: "disease",
  Compound: "compound",
  Shodhana_Method: "compound",
  ShodhanaMethod: "compound",
  Toxic_Substance: "compound",
  ToxicSubstance: "compound",
  Purified_Compound: "compound",
  PurifiedCompound: "compound",
  Shodhana_Type: "compound",
  ShodhanaType: "compound",
  Media: "compound",
  Confirmation_Study: "compound",
  ConfirmationStudy: "compound",
  Process: "compound",
  Indication: "compound",
  Ayurvedic_Formulation: "compound",
  AyurvedicFormulation: "compound",
};

const TYPE_LABELS: Record<string, string> = {
  Disease: "Disease",
  Symptoms: "Symptom",
  Symptom: "Symptom",
  Ayurvedic_Diagnosis: "Ayurvedic Diagnosis",
  AyurvedicDiagnosis: "Ayurvedic Diagnosis",
  Dosha: "Dosha",
  Therapy: "Therapy",
  Formulations: "Formulation",
  Formulation: "Formulation",
  Assessment_Parameter: "Assessment Parameter",
  AssessmentParameter: "Assessment Parameter",
  Compound: "Compound",
  Shodhana_Method: "Shodhana Method",
  ShodhanaMethod: "Shodhana Method",
  Toxic_Substance: "Toxic Substance",
  ToxicSubstance: "Toxic Substance",
  Purified_Compound: "Purified Compound",
  PurifiedCompound: "Purified Compound",
  Shodhana_Type: "Shodhana Type",
  ShodhanaType: "Shodhana Type",
  Media: "Media",
  Confirmation_Study: "Confirmation Study",
  ConfirmationStudy: "Confirmation Study",
  Process: "Process",
  Indication: "Indication",
  Ayurvedic_Formulation: "Ayurvedic Formulation",
  AyurvedicFormulation: "Ayurvedic Formulation",
};

const TYPE_COLORS: Record<string, string> = {
  Disease: "hsl(16, 45%, 50%)",
  Symptoms: "hsl(35, 60%, 55%)",
  Symptom: "hsl(35, 60%, 55%)",
  Ayurvedic_Diagnosis: "hsl(266.5909090909, 35.2%, 49.0196078431%)",
  AyurvedicDiagnosis: "hsl(266.5909090909, 35.2%, 49.0196078431%)",
  Dosha: "hsl(280, 18%, 45%)",
  Therapy: "hsl(130, 22%, 30%)",
  Formulations: "hsl(140, 20%, 45%)",
  Formulation: "hsl(140, 20%, 45%)",
  Assessment_Parameter: "hsl(200, 25%, 40%)",
  AssessmentParameter: "hsl(200, 25%, 40%)",
  Compound: "hsl(16, 45%, 50%)",
  Shodhana_Method: "hsl(35, 60%, 55%)",
  ShodhanaMethod: "hsl(35, 60%, 55%)",
  Toxic_Substance: "hsl(8, 55%, 40%)",
  ToxicSubstance: "hsl(8, 55%, 40%)",
  Purified_Compound: "hsl(140, 25%, 55%)",
  PurifiedCompound: "hsl(140, 25%, 55%)",
  Shodhana_Type: "hsl(280, 18%, 45%)",
  ShodhanaType: "hsl(280, 18%, 45%)",
  Media: "hsl(200, 28%, 45%)",
  Confirmation_Study: "hsl(35, 50%, 50%)",
  ConfirmationStudy: "hsl(35, 50%, 50%)",
  Process: "hsl(40, 25%, 40%)",
  Indication: "hsl(355, 40%, 50%)",
  Ayurvedic_Formulation: "hsl(260, 20%, 50%)",
  AyurvedicFormulation: "hsl(260, 20%, 50%)",
};

const SearchResults = () => {
  
  const [searchParams, setSearchParams] = useSearchParams();
  const initialQuery = searchParams.get("q") ?? "";
  const requestedTab = searchParams.get("tab");
  const initialTab: SearchTab = requestedTab === "disease" || requestedTab === "compound" ? requestedTab : "all";

  const [query, setQuery] = useState(initialQuery);
  const [activeTab, setActiveTab] = useState<SearchTab>(initialTab);
  const [results, setResults] = useState<SearchResult[]>([]);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);
  const [page, setPage] = useState(0);
  const pageSize = 20;

  // Sync URL params
  useEffect(() => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      if (query) {
        next.set("q", query);
      } else {
        next.delete("q");
      }
      
      if (activeTab !== "all") {
        next.set("tab", activeTab);
      } else {
        next.delete("tab");
      }
      
      return next;
    }, { replace: true });
  }, [query, activeTab, setSearchParams]);

  // Perform search
  const performSearch = async () => {
    if (!query.trim()) {
      toast.error("Please enter a search term");
      return;
    }

    setLoading(true);
    setSearched(true);
    setPage(0);

    try {
      const typeParam = searchParams.get("type") || undefined;
      // Fetch all domains via "integrated"
      const nodes = await searchApi("integrated", query, 2000, typeParam);
      const allResults = nodes.map((n) => ({
        ...n,
        domain: TYPE_TO_DOMAIN[n.type] || "integrated",
        type: n.type,
      })) as SearchResult[];

      setResults(allResults);
      if (allResults.length === 0) {
        toast.info("No results found");
      }
    } catch (error) {
      toast.error("Search failed. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  // Search on initial load if query exists
  useEffect(() => {
    if (initialQuery && !searched) {
      performSearch();
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Filter results by active tab
  const filteredResults = useMemo(() => {
    if (activeTab === "all") return results;
    return results.filter((r) => r.domain === activeTab);
  }, [results, activeTab]);

  const paginatedResults = useMemo(() => {
    const start = page * pageSize;
    return filteredResults.slice(start, start + pageSize);
  }, [filteredResults, page]);

  const finalResults = paginatedResults;

  const totalPages = Math.ceil(filteredResults.length / pageSize);

  useEffect(() => {
    setPage(0);
  }, [activeTab, results]);

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === "Enter") {
      performSearch();
    }
  };

  const getEntityUrl = (result: SearchResult): string => {
    return entityGraphUrl(result);
  };

  const clearSearch = () => {
    setQuery("");
    setResults([]);
    setSearched(false);
  };

  return (
    <section className="container py-8 md:py-10">
      {/* Header */}
      <div className="mb-8">
        <h1 className="font-display text-2xl md:text-3xl font-semibold text-foreground">
          Search ShodhanaKG
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Search across all knowledge domains — diseases, compounds, therapies, formulations, and more.
        </p>
      </div>

      {/* Search bar */}
      <div className="mb-6 relative max-w-2xl">
        <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Search for diseases, compounds, therapies, formulations..."
          className="w-full rounded-xl border border-border bg-card pl-11 pr-12 py-3 text-sm outline-none focus:border-herbal/50 focus:ring-2 focus:ring-herbal/15 transition-smooth"
        />
        {query && (
          <button
            onClick={clearSearch}
            className="absolute right-3 top-1/2 -translate-y-1/2 p-1 rounded-md hover:bg-secondary transition-smooth"
          >
            <X className="h-4 w-4 text-muted-foreground" />
          </button>
        )}
        <button
          onClick={performSearch}
          disabled={loading}
          className="absolute right-14 top-1/2 -translate-y-1/2 px-4 py-1.5 rounded-md bg-primary text-primary-foreground text-xs font-medium hover:bg-primary/90 disabled:opacity-50 transition-smooth"
        >
          {loading ? "Searching..." : "Search"}
        </button>
      </div>

      {/* Tabs */}
      <div className="mb-6 border-b border-border">
        <div className="flex gap-1">
          {TABS.map((tab) => {
            const Icon = tab.icon;
            const count =
              tab.key === "all"
                ? results.length
                : results.filter((r) => r.domain === tab.key).length;
            return (
              <button
                key={tab.key}
                onClick={() => setActiveTab(tab.key)}
                className={cn(
                  "inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-smooth",
                  activeTab === tab.key
                    ? "border-herbal text-herbal"
                    : "border-transparent text-muted-foreground hover:text-foreground hover:border-border"
                )}
              >
                <Icon className="h-4 w-4" />
                {tab.label}
                {searched && (
                  <span
                    className={cn(
                      "ml-1 px-1.5 py-0.5 rounded-full text-[10px]",
                      activeTab === tab.key
                        ? "bg-herbal/10 text-herbal"
                        : "bg-secondary text-muted-foreground"
                    )}
                  >
                    {count}
                  </span>
                )}
              </button>
            );
          })}
        </div>
      </div>

      {/* Results */}
      <div className="space-y-6">
        {!searched ? (
          <div className="text-center py-16">
            <Search className="h-12 w-12 text-muted-foreground/30 mx-auto mb-4" />
            <h3 className="text-base font-medium text-foreground mb-1">
              Start searching
            </h3>
            <p className="text-sm text-muted-foreground">
              Enter a term above to search across all knowledge domains.
            </p>
          </div>
        ) : loading ? (
          <div className="text-center py-16">
            <div className="h-12 w-12 border-2 border-herbal/20 border-t-herbal rounded-full animate-spin mx-auto mb-4" />
            <p className="text-sm text-muted-foreground">Searching...</p>
          </div>
        ) : finalResults.length === 0 ? (
          <div className="text-center py-16">
            <Filter className="h-12 w-12 text-muted-foreground/30 mx-auto mb-4" />
            <h3 className="text-base font-medium text-foreground mb-1">
              No results found
            </h3>
            <p className="text-sm text-muted-foreground">
              Try adjusting your search term or switching to a different tab.
            </p>
          </div>
        ) : (
          <div className="space-y-3">
            {finalResults.map((result) => (
              <Link
                key={`${result.domain}-${result.type}-${result.id}`}
                to={getEntityUrl(result)}
                className="group flex items-center justify-between p-4 rounded-lg border border-border bg-card hover:border-herbal/50 hover:shadow-card transition-smooth"
              >
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <h3 className="font-medium text-foreground group-hover:text-herbal transition-smooth">
                      {result.label}
                    </h3>
                    <span
                      className="px-1.5 py-0.5 rounded text-[10px] font-medium"
                      style={{
                        backgroundColor: (TYPE_COLORS[result.type] || "hsl(200, 20%, 50%)")
                          .replace("hsl(", "hsla(")
                          .replace(")", ", 0.10)"),
                        color: TYPE_COLORS[result.type] || "hsl(200, 20%, 50%)",
                      }}
                    >
                      {(TYPE_LABELS[result.type] || result.type)}
                    </span>
                  </div>
                  {result.description && (
                    <p className="mt-1 text-xs text-muted-foreground line-clamp-1">
                      {result.description}
                    </p>
                  )}
                </div>
                <ChevronRight className="h-4 w-4 text-muted-foreground group-hover:text-herbal transition-smooth" />
              </Link>
            ))}

            {/* Pagination Controls */}
            {totalPages > 1 && (
              <div className="mt-8 flex items-center justify-between border-t border-border pt-4">
                <span className="text-sm text-muted-foreground">
                  Showing {page * pageSize + 1} to {Math.min((page + 1) * pageSize, filteredResults.length)} of {filteredResults.length}
                </span>
                <div className="flex gap-2">
                  <button
                    onClick={() => setPage(p => Math.max(0, p - 1))}
                    disabled={page === 0}
                    className="px-3 py-1.5 text-sm rounded-md border border-border bg-card disabled:opacity-50 disabled:cursor-not-allowed hover:bg-secondary transition-smooth"
                  >
                    Previous
                  </button>
                  <button
                    onClick={() => setPage(p => Math.min(totalPages - 1, p + 1))}
                    disabled={page >= totalPages - 1}
                    className="px-3 py-1.5 text-sm rounded-md border border-border bg-card disabled:opacity-50 disabled:cursor-not-allowed hover:bg-secondary transition-smooth"
                  >
                    Next
                  </button>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </section>
  );
};

export default SearchResults;
