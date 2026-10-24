import { SchemaVertexRows, SchemaEdgeRows } from '@/components/explorer/SchemaReference';
import React, { useState } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { 
  BookOpen, Search, Share2, LayoutDashboard, Database, Info, 
  Printer, HelpCircle, Network, ArrowRight, Activity, FlaskConical, 
  ExternalLink, FileText, CheckCircle2, ChevronRight, Layers, FileDown
} from "lucide-react";
import { cn } from "@/lib/utils";

// Helper to resolve base-path safe image URLs
const getImgPath = (name: string) => {
  const base = import.meta.env.BASE_URL || "/";
  return `${base.endsWith("/") ? base : base + "/" }images/help/${name}`;
};

interface Hotspot {
  id: number;
  x: number; // percentage from left
  y: number; // percentage from top
  title: string;
  description: React.ReactNode;
}

// Interactive Hotspot Component
const ImageHotspots = ({ src, hotspots, alt }: { src: string; hotspots: Hotspot[]; alt: string }) => {
  const [activeHotspot, setActiveHotspot] = useState<Hotspot | null>(null);

  return (
    <div className="flex flex-col gap-5 print:gap-4 print:block">
      <div className="relative overflow-hidden rounded-xl border border-border/80 bg-card shadow-card select-none group/img">
        <img src={src} alt={alt} className="w-full object-cover transition-all duration-300" />
        
        {/* Hotspots container (hidden in print) */}
        <div className="absolute inset-0 print:hidden">
          {hotspots.map((spot) => {
            const isActive = activeHotspot?.id === spot.id;
            return (
              <button
                key={spot.id}
                onClick={() => setActiveHotspot(isActive ? null : spot)}
                className={cn(
                  "absolute flex h-7 w-7 items-center justify-center rounded-full border-2 text-[11px] font-bold shadow-md transition-all duration-300",
                  "focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2",
                  isActive
                    ? "bg-primary border-primary-foreground text-primary-foreground scale-110 z-20 shadow-primary/30"
                    : "bg-background border-primary text-primary hover:scale-105 hover:bg-primary hover:text-primary-foreground z-10"
                )}
                style={{ left: `${spot.x}%`, top: `${spot.y}%`, transform: 'translate(-50%, -50%)' }}
              >
                {/* Pulsing ring */}
                {!isActive && (
                  <span className="absolute -inset-1 rounded-full border border-primary/50 animate-ping opacity-75" />
                )}
                {spot.id}
              </button>
            );
          })}
        </div>
      </div>

      {/* Hotspot details card */}
      <div className={cn(
        "p-4 rounded-xl border transition-all duration-300 min-h-[90px] flex items-start gap-3 print:border-none print:p-0 print:mt-4 print:min-h-0 print:block",
        activeHotspot 
          ? "bg-accent/5 border-accent/20 shadow-sm" 
          : "bg-muted/40 border-border/50 text-muted-foreground print:text-foreground"
      )}>
        {activeHotspot ? (
          <div className="animate-fade-in text-left">
            <h4 className="font-display font-semibold text-foreground flex items-center gap-2 print:text-black">
              <span className="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-[10px] text-primary-foreground font-bold print:bg-gray-200 print:text-black">
                {activeHotspot.id}
              </span>
              {activeHotspot.title}
            </h4>
            <p className="text-sm text-muted-foreground mt-1.5 leading-relaxed print:text-gray-700">
              {activeHotspot.description}
            </p>
          </div>
        ) : (
          <div className="w-full">
            {/* Screen view fallback when nothing is clicked */}
            <div className="flex items-center gap-2 text-sm justify-center py-2 print:hidden">
              <Info className="h-4 w-4 text-primary animate-pulse" />
              <span>Click any numbered hotspot on the screenshot above to explore the interface details.</span>
            </div>
            {/* Print view fallback: show all hotspots details in a list */}
            <div className="hidden print:block text-left">
              <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Interface Map & Highlights</h4>
              <div className="grid grid-cols-2 gap-4 text-xs">
                {hotspots.map((spot) => (
                  <div key={spot.id} className="border-l-2 border-primary/30 pl-3 py-1">
                    <span className="font-bold text-gray-900">{spot.id}. {spot.title}:</span>{" "}
                    <span className="text-gray-600">{spot.description}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

const HowToUse = () => {
  const [activeTab, setActiveTab] = useState("getting-started");

  // Hotspots definitions
  const homeHotspots: Hotspot[] = [
    { id: 1, x: 50, y: 3, title: "Navigation Bar", description: "Contains home page tab, Knowledge graph tab, explorer tabs, help, about us, Hindi to Eng translation, and colourblind mode." },
    { id: 2, x: 50, y: 15, title: "Global Header & Logo", description: "The 'ShodhanaKG' main branding and abstract summary section." },
    { id: 3, x: 50, y: 23, title: "Global Search Bar", description: "Search bar with drop-down explorer to instantly query diseases, compounds, or integrated data." },
    { id: 4, x: 50, y: 64, title: "Total Stats Cards", description: "Cards displaying the total counts for Nodes, Relations, and Publications across the database." },
    { id: 5, x: 50, y: 88, title: "Explorer Modes", description: "Quick access cards to launch Disease Explorer, Compound Explorer, or Integrated Explorer." }
  ];

  const searchHotspots: Hotspot[] = [
    { id: 1, x: 50, y: 28, title: "Global Search Bar", description: "Query the knowledge graph for any disease, compound, or formulation to instantly retrieve matched results." },
    { id: 2, x: 20, y: 37, title: "Category Navigation Tabs", description: "Toggle between classifications (e.g., Disease, Compounds) to filter your search matches by their underlying domains." },
    { id: 3, x: 50, y: 48, title: "Matched Results", description: "Displays the matched nodes. Clicking on any result card will route you directly to the relevant explorer with that node pre-loaded." }
  ];

  const advancedSearchHotspots: Hotspot[] = [
    { id: 1, x: 7, y: 7, title: 'Set your filters', description: <>
      <span className="block mb-2"><strong>Reference namespace:</strong> limit results to PubMed, AYUSH, or both.</span>
      <span className="block mb-2"><strong>ID number:</strong> look up one reference by its PubMed or AYUSH ID.</span>
      <span className="block mb-2"><strong>Keywords:</strong> match terms in stored records (e.g., <em>pain</em>, <em>sciatica</em>).</span>
      <span className="block mb-2"><strong>Domain, Entity type, Relationship:</strong> narrow results to a domain, to edges involving a given entity type (e.g., Disease, Dosha), or to a given relationship (e.g., HAS SYMPTOM, TREATED BY).</span>
      <span className="block mb-2"><strong>References per page:</strong> choose how many references are shown per page.</span>
      <span className="block">Filters can be combined. Leave a filter on its default (&quot;All &hellip;&quot;) to ignore it.</span>
    </> },
    { id: 2, x: 93, y: 21.75, title: 'Run the search', description: <>Click <strong>Search KG references</strong>. Use <strong>Reset</strong> to clear all filters.</> },
    { id: 3, x: 7, y: 29.92, title: 'Read the results', description: <>
      <span className="block mb-2">The summary bar shows the number of matching references and their total supporting edges. Use <strong>Sort this page</strong> to reorder the references on the current page.</span>
      <span className="block mb-2">Each result card shows:</span>
      <span className="block mb-2">The reference ID, its namespace badge, and the number of supporting edges;</span>
      <span className="block mb-2">Relationship chips with edge counts for that reference (e.g., ASSESS 3, HAS SYMPTOM 7);</span>
      <span className="block">Edges grouped by relationship type, each as <em>Subject &rarr; Relationship &rarr; Object</em> with entity types shown below the names.</span>
    </> },
    { id: 4, x: 93, y: 55.63, title: 'Explore further', description: <>
      <span className="block mb-2">Click an edge row to expand it for details and <strong>Open in Explorer</strong> links, which show the entity in the knowledge graph.</span>
      <span className="block mb-2">Click <strong>Show all N edges</strong> to see every edge under a card.</span>
      <span className="block">Click <strong>Open ID</strong> to open the original PubMed or AYUSH record.</span>
    </> },
  ];

  const diseaseHotspots: Hotspot[] = [
    { id: 1, x: 55, y: 45, title: "Interactive Graph Workspace", description: "Visualize networks of disease nodes (red), symptoms (yellow), Ayurvedic diagnoses (dark brown), therapies (green), and assessment parameters (blue). Drag, hover, or click entities to trace associations." },
    { id: 2, x: 16, y: 30, title: "Sidebar Autocomplete & Filters", description: "Search and highlight specific nodes within the workspace. Refine connections dynamically by selecting nodes from the multi-autocomplete menu." },
    { id: 3, x: 88, y: 50, title: "Provenance & Details Panel", description: "Inspect deep information for the selected entity or relationship, including dosage, outcome measures, and linked scientific literature (PubMed ID links)." },
    { id: 4, x: 88, y: 13, title: "Data Export Control", description: "Export current visual graph networks as publication-ready PNGs, or download full structural data in structured JSON or CSV sheets." }
  ];

  const diseaseTableHotspots: Hotspot[] = [
    { id: 1, x: 20, y: 39, title: "Search Bar", description: "Instantly filter diseases by typing their name." },
    { id: 2, x: 50, y: 65, title: "Statistics Table", description: "A structured view of the number of connected entities (symptoms, doshas, therapies) per disease across the knowledge graph." },
    { id: 3, x: 88, y: 53, title: "Open Graph Button", description: "Clicking this button opens the interactive Graph View focused entirely on the selected disease." }
  ];

  const compoundHotspots: Hotspot[] = [
    { id: 1, x: 55, y: 45, title: "Graph View", description: "Switch between the visual network canvas and the spreadsheet data." },
    { id: 2, x: 12, y: 30, title: "Filters", description: "Toggle the visibility of specific node types or filter by text to clean up the graph view." },
    { id: 3, x: 88, y: 50, title: "Properties Panel", description: "View the classical properties, indications, and adverse effects of the selected compound or herb." },
    { id: 4, x: 83, y: 12, title: "Export Option", description: "Download the current graph as a PNG or export structured data to CSV/JSON." }
  ];

  const compoundTableHotspots: Hotspot[] = [
    { id: 1, x: 20, y: 39, title: "Search Bar", description: "Instantly filter toxic substances by typing their name." },
    { id: 2, x: 50, y: 65, title: "Statistics Table", description: "A structured view of the number of connected entities (compounds, shodhana methods) per toxic substance." },
    { id: 3, x: 88, y: 53, title: "Open Graph Button", description: "Clicking this button opens the interactive Graph View focused entirely on the selected toxic substance." }
  ];

  const integratedHotspots: Hotspot[] = [
    { id: 1, x: 55, y: 45, title: "Graph View Workspace", description: "The visual network canvas bridging all domains together." },
    { id: 2, x: 82, y: 50, title: "Properties Panel", description: "Inspect detailed properties and provenance evidence for the selected node or relationship." },
    { id: 3, x: 10, y: 81, title: "Hop Selection", description: "Adjust path depth to visualize interconnected nodes across domains (e.g., 1-hop expands to immediate neighbors)." },
    { id: 4, x: 83, y: 12, title: "Export Option", description: "Download the current graph as a publication-ready PNG, or export the structural data." }
  ];

  return (
    <div className="container py-10 max-w-6xl animate-fade-in relative">
      
      {/* Injected styles for clean PDF layout print support */}
      <style dangerouslySetInnerHTML={{ __html: `
        @media print {
          body {
            background: white !important;
            color: black !important;
            font-size: 12px !important;
          }
          header, footer, nav, button, .print-hide, #chatbot-widget-container, [role="tablist"] {
            display: none !important;
          }
          .print-show {
            display: block !important;
          }
          .page-break {
            page-break-before: always;
            padding-top: 2rem;
          }
          .print-container {
            width: 100% !important;
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
            box-shadow: none !important;
            border: none !important;
            background: transparent !important;
          }
          img {
            max-width: 100% !important;
            height: auto !important;
            page-break-inside: avoid;
            border: 1px solid #ccc !important;
          }
          .card {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            background: transparent !important;
          }
          .accent-dot {
            background-color: #333 !important;
          }
        }
      `}} />

      {/* Header section (hidden in print) */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10 print-hide">
        <div className="flex items-center gap-3">
          <div className="p-2.5 rounded-xl bg-gradient-herbal text-white shadow-md">
            <BookOpen className="h-6 w-6" />
          </div>
          <div className="text-left">
            <h1 className="text-3xl font-display font-bold text-foreground">ShodhanaKG User Guide</h1>
            <p className="text-muted-foreground mt-1 text-sm">
              An interactive handbook for exploring the Ayurvedic purification knowledge graph.
            </p>
          </div>
        </div>
        
        {/* PDF Download trigger */}
        <button
          onClick={() => window.print()}
          className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full text-sm font-semibold bg-primary text-primary-foreground hover:bg-primary/95 hover:scale-[1.02] shadow-md shadow-primary/10 transition-all select-none self-start md:self-auto"
        >
          <Printer className="h-4 w-4" />
          Print / Save PDF Manual
        </button>
      </div>

      {/* Screen Layout: Tabs (hidden in print) */}
      <Tabs defaultValue="getting-started" value={activeTab} onValueChange={setActiveTab} className="space-y-8 print-hide">
        <TabsList className="w-full grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 h-auto p-1.5 gap-1.5 bg-muted/60 backdrop-blur-sm border border-border/40 rounded-xl">
          <TabsTrigger value="getting-started" className="py-2.5 rounded-lg text-xs md:text-sm font-medium transition-all">
            1. Getting Started
          </TabsTrigger>
          <TabsTrigger value="disease-explorer" className="py-2.5 rounded-lg text-xs md:text-sm font-medium transition-all">
            2. Disease Explorer
          </TabsTrigger>
          <TabsTrigger value="compound-explorer" className="py-2.5 rounded-lg text-xs md:text-sm font-medium transition-all">
            3. Compound Explorer
          </TabsTrigger>
          <TabsTrigger value="integrated-explorer" className="py-2.5 rounded-lg text-xs md:text-sm font-medium transition-all">
            4. Integrated View
          </TabsTrigger>
          <TabsTrigger value="kg-schema" className="py-2.5 rounded-lg text-xs md:text-sm font-medium transition-all">
            5. Graph Schema
          </TabsTrigger>
          <TabsTrigger value="advanced-search" className="py-2.5 rounded-lg text-xs md:text-sm font-medium transition-all">
            6. Advanced Search
          </TabsTrigger>
        </TabsList>

        {/* 1. GETTING STARTED CONTENT */}
        <TabsContent value="getting-started" className="space-y-8 focus-visible:outline-none">
          <Card className="border-border/60 bg-card/60 backdrop-blur-sm shadow-card overflow-hidden">
            <CardHeader className="text-left border-b border-border/40 pb-4">
              <CardTitle className="text-2xl font-display text-foreground flex items-center gap-2">
                <LayoutDashboard className="h-5 w-5 text-accent" />
                Getting Started with ShodhanaKG
              </CardTitle>
              <CardDescription>
                Understand the home dashboard layout, quick search functions, and access options.
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-6 space-y-8">
              <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div className="lg:col-span-7">
                  <ImageHotspots 
                    src={getImgPath("01-homepage.png")} 
                    hotspots={homeHotspots} 
                    alt="ShodhanaKG Homepage Interface Map" 
                  />
                </div>
                <div className="lg:col-span-5 text-left space-y-5">
                  <h3 className="text-lg font-display font-semibold text-foreground">Welcome to the Knowledge Graph</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">
                    <strong>ShodhanaKG</strong> is a digital explorer suite that maps the classical Ayurvedic purification science of <em>Shodhana</em> to molecular chemistry and clinical disease models. It integrates traditional texts with modern PubMed studies.
                  </p>
                  
                  <div className="space-y-4">
                    <div className="flex gap-3 items-start">
                      <div className="mt-1 h-5 w-5 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0 text-primary">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                      </div>
                      <div className="text-sm">
                        <span className="font-semibold text-foreground">Visual Analytics:</span> Easily toggle between Cytoscape network graphs and structured spreadsheets.
                      </div>
                    </div>
                    <div className="flex gap-3 items-start">
                      <div className="mt-1 h-5 w-5 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0 text-primary">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                      </div>
                      <div className="text-sm">
                        <span className="font-semibold text-foreground">Scientific Provenance:</span> Every edge connection lists verified dosage, duration, outcomes, and linking PubMed ID citations.
                      </div>
                    </div>
                    <div className="flex gap-3 items-start">
                      <div className="mt-1 h-5 w-5 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0 text-primary">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                      </div>
                      <div className="text-sm">
                        <span className="font-semibold text-foreground">Accessibility first:</span> Colorblind mode provides HSL palettes tailored for protanopia, deuteranopia, and tritanopia researchers.
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div className="border-t border-border/40 pt-8 grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div className="lg:col-span-5 text-left space-y-4 lg:order-1 order-2">
                  <h3 className="text-lg font-display font-semibold text-foreground">Global Multi-Domain Search</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">
                    By typing keyword strings in the global search bar, the engine instantly queries ArcadeDB and groups matches.
                  </p>
                  <p className="text-sm text-muted-foreground leading-relaxed">
                    Clicking on any card inside the search results redirects you directly to the relevant Explorer Mode (Disease or Compound) with the target node automatically focused and loaded.
                  </p>
                </div>
                <div className="lg:col-span-7 lg:order-2 order-1">
                  <ImageHotspots 
                    src={getImgPath("02-search-results.png")} 
                    hotspots={searchHotspots} 
                    alt="ShodhanaKG Global Search Results Matcher" 
                  />
                </div>
              </div>
            </CardContent>
          </Card>

        </TabsContent>

        {/* 2. DISEASE EXPLORER GUIDE */}
        <TabsContent value="disease-explorer" className="space-y-8 focus-visible:outline-none">
          <Card className="border-border/60 bg-card/60 backdrop-blur-sm shadow-card overflow-hidden">
            <CardHeader className="text-left border-b border-border/40 pb-4">
              <CardTitle className="text-2xl font-display text-foreground flex items-center gap-2">
                <Activity className="h-5 w-5 text-terracotta" />
                Disease Explorer Mode
              </CardTitle>
              <CardDescription>
                Analyze correlations between modern clinical diseases, traditional symptoms, and Ayurvedic diagnosis terms.
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-6 space-y-8">
              <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div className="lg:col-span-7">
                  <ImageHotspots 
                    src={getImgPath("08-disease-graph.png")} 
                    hotspots={diseaseHotspots} 
                    alt="Disease Explorer Graph workspace" 
                  />
                </div>
                <div className="lg:col-span-5 text-left space-y-4">
                  <h3 className="text-lg font-display font-semibold text-terracotta">Exploring Clinical Manifestations</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">
                    The <strong>Disease Explorer</strong> focuses on clinical manifestations, traditional diagnosis metrics, and symptom-disease overlaps. 
                  </p>
                  
                  <div className="p-4 rounded-xl bg-terracotta/5 border border-terracotta/15 space-y-2">
                    <h4 className="text-xs uppercase font-bold text-terracotta tracking-wider">Node Representation</h4>
                    <ul className="space-y-1.5 text-xs text-muted-foreground">
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[#dc2626] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Disease (Red):</span> Modern clinical conditions.
                      </li>
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[#eab308] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Symptoms (Yellow):</span> Visual physical/mental signs.
                      </li>
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[var(--node-AyurvedicDiagnosis,#7851A9)] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Ayurvedic Diagnosis (Royal Purple):</span> Classic diagnostic terms.
                      </li>
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[#16a34a] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Therapy (Green):</span> Treatment methods.
                      </li>
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[#2563eb] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Assessment Parameter (Blue):</span> Evaluation metrics.
                      </li>
                    </ul>
                  </div>

                  <p className="text-sm text-muted-foreground leading-relaxed">
                    Use the <strong>Sidebar Autocomplete Filter</strong> to highlight specific disease paths, and select any connecting edge to open references in the right-hand **Details Panel**.
                  </p>
                </div>
              </div>

              <div className="border-t border-border/40 pt-8 grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div className="lg:col-span-5 text-left space-y-4 lg:order-1 order-2">
                  <h3 className="text-lg font-display font-semibold text-terracotta">Disease Relationship Statistics</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">
                    Switching to the <strong>Table View</strong> displays a comprehensive statistical overview.
                  </p>
                  <p className="text-sm text-muted-foreground leading-relaxed">
                    This tabular interface provides the exact number of connected entities—such as Symptoms, Ayurvedic Diagnoses, Doshas, Therapies, and Assessment Parameters—associated with each disease. Use the search bar to filter diseases, or click "Open graph" to visualize a specific disease's network.
                  </p>
                </div>
                <div className="lg:col-span-7 lg:order-2 order-1">
                  <ImageHotspots 
                    src={getImgPath("05-disease-table.png")} 
                    hotspots={diseaseTableHotspots} 
                    alt="Disease Table Spreadsheet Reference" 
                  />
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* 3. COMPOUND EXPLORER GUIDE */}
        <TabsContent value="compound-explorer" className="space-y-8 focus-visible:outline-none">
          <Card className="border-border/60 bg-card/60 backdrop-blur-sm shadow-card overflow-hidden">
            <CardHeader className="text-left border-b border-border/40 pb-4">
              <CardTitle className="text-2xl font-display text-foreground flex items-center gap-2">
                <FlaskConical className="h-5 w-5 text-herbal" />
                Compound Explorer Mode
              </CardTitle>
              <CardDescription>
                Map standard Ayurvedic formulations and botanical raw ingredients to their biochemical constituents.
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-6 space-y-8">
              <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div className="lg:col-span-7">
                  <ImageHotspots 
                    src={getImgPath("06-compound-graph.png")} 
                    hotspots={compoundHotspots} 
                    alt="Compound Explorer Graph mapping" 
                  />
                </div>
                <div className="lg:col-span-5 text-left space-y-4">
                  <h3 className="text-lg font-display font-semibold text-herbal">Pharmacology & Botanical Ingredients</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">
                    The <strong>Compound Explorer</strong> visualizes the phytochemistry and formulation architecture. It maps which herbs are contained in traditional polyherbal formulations, and which isolated bioactives originate from those herbs.
                  </p>

                  <div className="p-4 rounded-xl bg-herbal/5 border border-herbal/15 space-y-2">
                    <h4 className="text-xs uppercase font-bold text-herbal tracking-wider">Node Representation</h4>
                    <ul className="space-y-1.5 text-xs text-muted-foreground grid grid-cols-2 gap-x-2">
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[#22c55e] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Compound (Green)</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[#f97316] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Shodhana Method (Orange)</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[#ef4444] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Toxic Substance (Red)</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[#3b82f6] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Purified Compound (Blue)</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[#a855f7] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Shodhana Type (Purple)</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[#0d9488] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Media (Teal Blue)</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[#eab308] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Confirmation Study (Yellow)</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[#ec4899] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Ayurvedic Formulation (Pink)</span>
                      </li>
                      <li className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-[#78350f] flex-shrink-0" />
                        <span className="font-semibold text-foreground">Process (Brown)</span>
                      </li>
                    </ul>
                  </div>

                  <p className="text-sm text-muted-foreground leading-relaxed">
                    <strong>Double-click node expansion:</strong> To follow a pathway, double-click any node in the workspace. The client will query the ArcadeDB database and dynamically add all direct neighbors of the selected node onto the canvas.
                  </p>
                </div>
              </div>

              <div className="border-t border-border/40 pt-8 grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div className="lg:col-span-5 text-left space-y-4 lg:order-1 order-2">
                  <h3 className="text-lg font-display font-semibold text-herbal">Toxic Substance Relationship Statistics</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">
                    Switching to the <strong>Table View</strong> displays a comprehensive statistical overview.
                  </p>
                  <p className="text-sm text-muted-foreground leading-relaxed">
                    This tabular interface provides the exact number of connected entities—such as Compounds and Shodhana Methods—associated with each toxic substance. Use the search bar to filter toxic substances, or click "Open graph" to visualize a specific substance's network.
                  </p>
                </div>
                <div className="lg:col-span-7 lg:order-2 order-1">
                  <ImageHotspots 
                    src={getImgPath("07-compound-table.png")} 
                    hotspots={compoundTableHotspots} 
                    alt="Compound Explorer Structured Table" 
                  />
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* 4. INTEGRATED EXPLORER GUIDE */}
        <TabsContent value="integrated-explorer" className="space-y-8 focus-visible:outline-none">
          <Card className="border-border/60 bg-card/60 backdrop-blur-sm shadow-card overflow-hidden">
            <CardHeader className="text-left border-b border-border/40 pb-4">
              <CardTitle className="text-2xl font-display text-foreground flex items-center gap-2">
                <Network className="h-5 w-5 text-bark" />
                Integrated Explorer View
              </CardTitle>
              <CardDescription>
                A comprehensive cross-domain graph linking traditional clinical therapies directly to molecular bioactives.
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-6 space-y-8">
              <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div className="lg:col-span-7">
                  <ImageHotspots 
                    src={getImgPath("07-integrated-graph.png")} 
                    hotspots={integratedHotspots} 
                    alt="Integrated Explorer unified graph mapping" 
                  />
                </div>
                <div className="lg:col-span-5 text-left space-y-5">
                  <h3 className="text-lg font-display font-semibold text-bark">Bridging Traditional Wisdom & Modern Science</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">
                    The <strong>Integrated Explorer</strong> is ShodhanaKG's core contribution. It overlays clinical disease networks with biochemical networks in a unified visualization.
                  </p>
                  
                  <div className="space-y-3 text-sm">
                    <p className="text-muted-foreground">
                      This interface reveals complex scientific linkages, such as:
                    </p>
                    <div className="border-l-2 border-bark/30 pl-4 py-1 text-xs text-muted-foreground space-y-2">
                      <p>
                        <strong>Clinical Disease</strong> <span className="text-accent">→ Treated By →</span> <strong>Ayurvedic Therapy</strong> <span className="text-accent">→ Uses →</span> <strong>Formulations</strong> <span className="text-accent">→ Contains →</span> <strong>Molecular Compounds</strong>
                      </p>
                      <p>
                        This path helps researchers identify active compounds (e.g., *Curcumin* or *Piperine*) that explain the therapeutic mechanisms of classical treatments.
                      </p>
                    </div>
                  </div>

                  <p className="text-sm text-muted-foreground leading-relaxed">
                    By combining nodes from both domains, you can trace the scientific pathway from traditional medical text diagnoses down to molecular targets.
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* 5. DATABASE SCHEMA & REFERENCE */}
        <TabsContent value="kg-schema" className="space-y-8 focus-visible:outline-none">
          <Card className="border-border/60 bg-card/60 backdrop-blur-sm shadow-card overflow-hidden">
            <CardHeader className="text-left border-b border-border/40 pb-4">
              <CardTitle className="text-2xl font-display text-foreground flex items-center gap-2">
                <Layers className="h-5 w-5 text-primary" />
                Knowledge Graph Schema Reference
              </CardTitle>
              <CardDescription>
                A quick-reference guide detailing entity vertex categories and edge interaction definitions.
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-6 text-left">
              <div className="space-y-8">
                <div>
                  <h3 className="text-lg font-display font-semibold text-foreground mb-4">Vertex Node Types (17 Classes)</h3>
                  <div className="overflow-x-auto rounded-xl border border-border/60 bg-background/50">
                    <table className="w-full text-sm text-left border-collapse">
                      <thead>
                        <tr className="bg-muted/80 text-muted-foreground font-semibold border-b border-border/50">
                          <th className="p-3">Domain</th>
                          <th className="p-3">Entity</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-border/40"><SchemaVertexRows /></tbody>
                    </table>
                  </div>
                </div>

                <div className="border-t border-border/40 pt-8">
                  <h3 className="text-lg font-display font-semibold text-foreground mb-4">Edge Interaction Types (21 Relationships)</h3>
                  <div className="overflow-x-auto rounded-xl border border-border/60 bg-background/50">
                    <table className="w-full text-sm text-left border-collapse">
                      <thead>
                        <tr className="bg-muted/80 text-muted-foreground font-semibold border-b border-border/50">
                          <th className="p-3">Domain</th><th className="p-3">Relationship</th>
                          <th className="p-3 w-1/4">Source → Target</th>
                          <th className="p-3 w-2/4">Stored properties (where present)</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-border/40"><SchemaEdgeRows /></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
        <TabsContent value="advanced-search" className="space-y-8 focus-visible:outline-none">
          <Card className="border-border/60 bg-card/60 backdrop-blur-sm shadow-card overflow-hidden">
            <CardHeader className="text-left border-b border-border/40 pb-4">
              <CardTitle className="text-2xl font-display text-foreground flex items-center gap-2"><Search className="h-5 w-5 text-primary" />Advanced Search</CardTitle>
              <CardDescription>Find stored references and explore the graph edges they support.</CardDescription>
            </CardHeader>
            <CardContent className="pt-6 space-y-8">
              <div className="grid grid-cols-1 md:grid-cols-12 gap-8 items-start">
                <div className="md:col-span-7">
                  <ImageHotspots src={getImgPath('11-advanced-search.png')} hotspots={advancedSearchHotspots} alt="Advanced Search: filters, search button, results summary and expanded edge properties" />
                </div>
                <div className="md:col-span-5 text-left space-y-5">
                  <h3 className="text-lg font-display font-semibold text-bark">Search the Evidence Behind the Graph</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">Advanced Search finds the PubMed and AYUSH references stored in ShodhanaKG and shows the graph edges each one supports. It searches records inside the knowledge graph only, not external article full text.</p>
                  <div className="space-y-3 text-sm">
                    <div className="border-l-2 border-bark/30 pl-4 py-1 text-xs text-muted-foreground space-y-2">
                      <p><strong>Filter (Relationship: TREATED BY)</strong> <span className="text-accent">&rarr;</span> <strong>Keyword (disease name)</strong> <span className="text-accent">&rarr;</span> <strong>Reference cards</strong> <span className="text-accent">&rarr;</span> <strong>Supporting edges</strong> <span className="text-accent">&rarr;</span> <strong>Open in Explorer</strong></p>
                    </div>
                    <p className="text-muted-foreground"><strong>Tip:</strong> To find all evidence for a disease-treatment link, set the Relationship filter (e.g., TREATED BY) and enter the disease name under Keywords.</p>
                  </div>
                  <p className="text-sm text-muted-foreground leading-relaxed">Combine filters to trace any disease-treatment link back to its source reference.</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* PRINT / OFFLINE VIEW MANUAL: Sequentially prints all sections in a gorgeous textbook layout */}
      <div className="hidden print:block text-left print-container">
        
        {/* Title Cover page element */}
        <div className="border-b-4 border-gray-900 pb-6 mb-10">
          <h1 className="text-4xl font-serif font-bold text-black uppercase tracking-tight">ShodhanaKG User Guide & Handbook</h1>
          <p className="text-sm text-gray-600 mt-2 font-mono uppercase tracking-widest">
            Complete System Documentation & Reference Manual
          </p>
          <div className="mt-4 text-xs text-gray-500">
            Generated automatically from ShodhanaKG Suite • API version: v1
          </div>
        </div>

        {/* Section 1 */}
        <div className="space-y-6">
          <h2 className="text-2xl font-serif font-bold border-b border-gray-300 pb-2 text-black">1. Overview & Getting Started</h2>
          <p className="text-sm text-gray-700 leading-relaxed">
            ShodhanaKG is an advanced digital explorer designed to catalog the classical Ayurvedic purification science of *Shodhana* (traditional processing of medicinal materials) alongside modern chemical biology and modern disease ontologies. It integrates traditional diagnostic terms, symptoms, formulations, botanical ingredients, and isolated bioactive compounds in a unified high-performance graph.
          </p>
          <div className="my-6">
            <img src={getImgPath("01-homepage.png")} alt="ShodhanaKG Homepage Print View" className="w-full border" />
            <div className="mt-4">
              <h4 className="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Homepage Highlights</h4>
              <div className="grid grid-cols-2 gap-4 text-xs">
                {homeHotspots.map((spot) => (
                  <div key={spot.id} className="border-l-2 border-gray-400 pl-3">
                    <span className="font-bold">{spot.id}. {spot.title}:</span> {spot.description}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Section 2 */}
        <div className="page-break space-y-6">
          <h2 className="text-2xl font-serif font-bold border-b border-gray-300 pb-2 text-black">2. Disease Explorer Guide</h2>
          <p className="text-sm text-gray-700 leading-relaxed">
            The Disease Explorer traces correlations between clinical diseases, Ayurvedic traditional diagnosis classes (such as *Kushtha*), bodily humors/imbalances (*Doshas*), and symptoms.
          </p>
          <div className="my-6">
            <img src={getImgPath("08-disease-graph.png")} alt="Disease Explorer Print View" className="w-full border" />
            <div className="mt-4">
              <h4 className="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Interface Map & Highlights</h4>
              <div className="grid grid-cols-2 gap-4 text-xs">
                {diseaseHotspots.map((spot) => (
                  <div key={spot.id} className="border-l-2 border-gray-400 pl-3">
                    <span className="font-bold">{spot.id}. {spot.title}:</span> {spot.description}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Section 3 */}
        <div className="page-break space-y-6">
          <h2 className="text-2xl font-serif font-bold border-b border-gray-300 pb-2 text-black">3. Compound Explorer Guide</h2>
          <p className="text-sm text-gray-700 leading-relaxed">
            The Compound Explorer maps polyherbal formulations to their respective botanical source herbs, revealing active molecular compounds that drive therapeutic properties.
          </p>
          <div className="my-6">
            <img src={getImgPath("06-compound-graph.png")} alt="Compound Explorer Print View" className="w-full border" />
            <div className="mt-4">
              <h4 className="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Interface Map & Highlights</h4>
              <div className="grid grid-cols-2 gap-4 text-xs">
                {compoundHotspots.map((spot) => (
                  <div key={spot.id} className="border-l-2 border-gray-400 pl-3">
                    <span className="font-bold">{spot.id}. {spot.title}:</span> {spot.description}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Section 4 */}
        <div className="page-break space-y-6">
          <h2 className="text-2xl font-serif font-bold border-b border-gray-300 pb-2 text-black">4. Integrated View & Pathways</h2>
          <p className="text-sm text-gray-700 leading-relaxed">
            The Integrated Explorer merges the clinical and chemical subgraphs, providing a holistic map of pathways running from traditional therapies, to ingredients, down to clinical outcomes.
          </p>
          <div className="my-6">
            <img src={getImgPath("07-integrated-graph.png")} alt="Integrated Explorer Print View" className="w-full border" />
            <div className="mt-4">
              <h4 className="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Interface Map & Highlights</h4>
              <div className="grid grid-cols-2 gap-4 text-xs">
                {integratedHotspots.map((spot) => (
                  <div key={spot.id} className="border-l-2 border-gray-400 pl-3">
                    <span className="font-bold">{spot.id}. {spot.title}:</span> {spot.description}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Section 5 */}
        <div className="page-break space-y-6">
          <h2 className="text-2xl font-serif font-bold border-b border-gray-300 pb-2 text-black">5. Knowledge Graph Schema Reference</h2>
          <h3 className="text-lg font-bold text-gray-800">Vertex Node Types (17 Classes) and Edge Interaction Types (21 Relationships)</h3>
          <table className="w-full text-xs text-left border border-gray-300 border-collapse mt-2">
            <thead><tr><th className="p-2">Domain</th><th className="p-2">Entity</th></tr></thead>
            <tbody className="divide-y divide-gray-300"><SchemaVertexRows print /></tbody>
          </table>
          <table className="w-full text-xs text-left border border-gray-300 border-collapse mt-2">
            <thead><tr><th className="p-2">Domain</th><th className="p-2">Relationship</th><th className="p-2">Source → Target</th><th className="p-2">Stored properties (where present)</th></tr></thead>
            <tbody className="divide-y divide-gray-300"><SchemaEdgeRows print /></tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default HowToUse;
