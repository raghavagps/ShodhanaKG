> Architecture reference with historical sections. For current statistics, namespace handling, chatbot behavior and release verification, use [Current system status](docs/chatbot/CURRENT_SYSTEM_STATUS.md). Superseded fallback/count/PMID-normalization examples below must not be used as implementation guidance.

# ShodhanaKG Explorer Suite — Frontend Documentation

## Table of Contents

1. [Tech Stack](#1-tech-stack)
2. [Project Overview](#2-project-overview)
3. [Architecture](#3-architecture)
4. [Component Tree](#4-component-tree)
5. [Routing](#5-routing)
6. [State Management](#6-state-management)
7. [Data Layer](#7-data-layer)
8. [Graph Visualization](#8-graph-visualization)
9. [Page-by-Page Breakdown](#9-page-by-page-breakdown)
10. [Shared Components](#10-shared-components)
11. [Styling & Design System](#11-styling--design-system)
12. [Accessibility](#12-accessibility)
13. [Testing](#13-testing)
14. [Build & Deployment](#14-build--deployment)
15. [File Reference](#15-file-reference)

---

## 1. Tech Stack

| Category | Technology | Version |
|---|---|---|
| **Framework** | React | 18.3.1 |
| **Build Tool** | Vite | 5.4.19 |
| **Language** | TypeScript | 5.8.3 (strict: false) |
| **Routing** | react-router-dom | 6.30.1 |
| **Data Fetching** | @tanstack/react-query | 5.83.0 |
| **Styling** | Tailwind CSS | 3.4.17 |
| **UI Components** | shadcn/ui (Radix primitives) | — |
| **Graph Visualization** | cytoscape | 3.33.2 |
| **Charts** | recharts | 2.15.4 |
| **Forms** | react-hook-form + zod | 7.61.1 / 3.25.76 |
| **Icons** | lucide-react | 0.462.0 |
| **Class Utils** | clsx + tailwind-merge + class-variance-authority | — |
| **Pkg Manager** | npm | — |

### Key Dev Dependencies

| Tool | Purpose |
|---|---|
| Vite SWC plugin (`@vitejs/plugin-react-swc`) | Fast JSX transform |
| ESLint (+ ts + react-hooks plugins) | Linting |
| Vitest + jsdom | Unit testing |
| Playwright | E2E testing |
| @tailwindcss/typography | Prose styling |
| tailwindcss-animate | Animation utilities |

---

## 2. Project Overview

The ShodhanaKG Explorer Suite is an Ayurvedic biomedical knowledge graph visualization platform. It allows researchers to explore relationships between **Diseases**, **Therapies**, **Formulations**, **Compounds**, and **Shodhana (purification) Methods** through interactive graph and table views.

The project follows a **React SPA** architecture with lazy-loaded graph views, URL-driven filter state, and a PHP backend connecting to an ArcadeDB graph database.

### Three Explorer Modes

| Mode | Purpose | Entity Types |
|---|---|---|
| **Disease Explorer** | Clinical landscape | Disease, Symptom, AyurvedicDiagnosis, Dosha, Therapy, Formulation, AssessmentParameter |
| **Compound Explorer** | Pharmacological processing | Compound, ShodhanaMethod, ToxicSubstance, PurifiedCompound, ShodhanaType, Media, ConfirmationStudy, Process |
| **Integrated Explorer** | Cross-domain bridge | All of the above + bridge edges purified compound → formulation |

---

## 3. Architecture

```
vite.config.ts          ← Vite config: SWC plugin, path alias (@/), port 8080, HMR overlay off
tailwind.config.ts      ← Tailwind theme: brand colors, keyframes, animations
tsconfig.app.json       ← TS config: strict:false, noImplicitAny:false, path alias @/ → src/

src/
├── main.tsx                    ← Entrypoint: renders <App />
├── App.tsx                     ← Root: providers + routing + lazy graph views
├── index.css                   ← All CSS: Tailwind directives, HSL design tokens, colorblind mode
│
├── lib/
│   ├── api.ts                  ← HTTP client + React Query hooks (378 lines)
│   ├── types.ts                ← All TypeScript interfaces (146 lines)
│   ├── utils.ts                ← cn() helper (clsx + tailwind-merge)
│   └── graphExport.ts          ← CSV / JSON / PNG export helpers
│
├── hooks/
│   ├── useColorblind.tsx        ← Colorblind mode context + provider
│   ├── use-toast.ts             ← shadcn toast state management
│   └── use-mobile.tsx           ← Mobile detection hook
│
├── data/
│   ├── diseaseGraph.ts          ← Disease node/edge types, colors, sample data (155 lines)
│   ├── compoundGraph.ts         ← Compound node/edge types, colors, sample data (239 lines)
│   └── integratedGraph.ts       ← Merged types, bridge edges, k-hop traversal (355 lines)
│
├── components/
│   ├── ui/                      ← 49 shadcn/ui boilerplate components (DO NOT hand-edit)
│   ├── layout/
│   │   ├── AppLayout.tsx        ← Shell: TopNav + breadcrumb + Outlet + footer + chatbot
│   │   └── TopNav.tsx           ← Sticky header with dropdown navigation + colorblind toggle
│   ├── explorer/
│   │   ├── GraphCanvas.tsx      ← Cytoscape graph renderer (500 lines)
│   │   ├── DetailsPanel.tsx     ← Node/edge property viewer
│   │   ├── Legend.tsx           ← Color legend
│   │   ├── MultiAutocomplete.tsx← Tag-based async autocomplete
│   │   ├── ExportMenu.tsx       ← CSV/JSON/PNG export dropdown
│   │   ├── ProvenancePanel.tsx  ← Disease provenance paths (Disease→Therapy→Formulation)
│   │   ├── CompoundProvenancePanel.tsx ← Compound provenance paths (Compound→Method→Media)
│   │   └── GraphProvenance.tsx  ← Provenance highlighting hook + helpers
│   └── chatbot/
│       └── ChatbotWidget.tsx    ← Floating chatbot panel
│
├── pages/
│   ├── Index.tsx                ← Re-exports ModeSelection
│   ├── ModeSelection.tsx        ← Landing page: hero, global search, KG stats, 3 mode cards
│   ├── SearchResults.tsx        ← Cross-domain search results
│   ├── NotFound.tsx             ← 404 page
│   ├── FAQ.tsx                  ← Accordion FAQ
│   ├── HowToUse.tsx             ← Step-by-step usage guide
│   ├── Contact.tsx              ← Contact info
│   ├── Team.tsx                 ← Dev team profiles
│   ├── TestProvenance.tsx       ← Test harness for provenance feature
│   └── explorers/
│       ├── DiseaseExplorer.tsx         ← Disease home: search + category cards
│       ├── DiseaseGraphView.tsx        ← Disease graph: 3-panel interactive graph
│       ├── DiseaseTableView.tsx        ← Disease table: sortable/paginated
│       ├── CompoundExplorer.tsx        ← Compound home
│       ├── CompoundGraphView.tsx       ← Compound graph: 3-panel interactive graph
│       ├── CompoundTableView.tsx       ← Compound table
│       ├── IntegratedExplorer.tsx      ← Redirects to table view
│       ├── IntegratedGraphView.tsx     ← Integrated graph: top search + 3 panels
│       └── IntegratedTableView.tsx     ← Bridge table (client-side filtering)
│
└── test/
    ├── setup.ts                  ← Vitest setup (matchMedia mock)
    └── example.test.ts           ← Placeholder test
```

---

## 4. Component Tree

```
<App>                                     [src/App.tsx]
  <QueryClientProvider>
    <ColorblindProvider>
      <TooltipProvider>
        <Toaster />
        <Sonner />
        <BrowserRouter>
          <Routes>
            <Route element={<AppLayout />}>              [src/components/layout/AppLayout.tsx]
              ├── <TopNav />                             [src/components/layout/TopNav.tsx]
              ├── Breadcrumb (conditional)
              ├── <Outlet />  (animated fade-in)
              │   ├── "/" → <ModeSelection />            [src/pages/ModeSelection.tsx]
              │   ├── "/explorer/disease" → <DiseaseExplorer />
              │   ├── "/explorer/disease/graph" → <DiseaseGraphView />  (lazy)
              │   ├── "/explorer/disease/table" → <DiseaseTableView />
              │   ├── "/explorer/compound" → <CompoundExplorer />
              │   ├── "/explorer/compound/graph" → <CompoundGraphView />  (lazy)
              │   ├── "/explorer/compound/table" → <CompoundTableView />
              │   ├── "/explorer/integrated" → <IntegratedExplorer />  (redirect)
              │   ├── "/explorer/integrated/graph" → <IntegratedGraphView />  (lazy)
              │   ├── "/explorer/integrated/table" → <IntegratedTableView />
              │   ├── "/search" → <SearchResults />
              │   ├── "/faq" → <FAQ />
              │   ├── "/how-to-use" → <HowToUse />
              │   ├── "/contact" → <Contact />
              │   └── "/team" → <Team />
              ├── <Footer> (About, Resources, Other Databases)
              └── <ChatbotWidget />                     (hidden on /graph routes)
            <Route path="*"> → <NotFound />
          </Routes>
        </BrowserRouter>
      </TooltipProvider>
    </ColorblindProvider>
  </QueryClientProvider>
```

### Graph View Internal Layout (DiseaseGraphView / CompoundGraphView / IntegratedGraphView)

All three graph views follow the same 3-column layout pattern:

```
┌────────────────┬────────────────────────┬────────────────┐
│  LEFT SIDEBAR  │       CENTER           │ RIGHT SIDEBAR  │
│  (300px fixed) │     (flex: 1)          │ (320px fixed)  │
│                │                        │                │
│  Filters       │  <Legend /> strip      │ <DetailsPanel> │
│  MultiAuto-    │                        │ +              │
│  complete ×7-10│  <GraphCanvas />       │ <Provenance-   │
│                │  (Cytoscape)           │  Panel />      │
│  Node/Edge     │                        │ (tabbed)       │
│  Visibility    │  Stats overlay         │                │
│  Toggles       │                        │                │
│                │                        │                │
└────────────────┴────────────────────────┴────────────────┘
```

- **Focus mode** toggle hides both sidebars for full-screen graph
- **ResizeObserver** on the GraphCanvas container keeps Cytoscape canvas in sync

---

## 5. Routing

### Route Configuration (`src/App.tsx`)

| Path | Component | Loading |
|---|---|---|
| `/` | ModeSelection | Eager |
| `/explorer/disease` | DiseaseExplorer | Eager |
| `/explorer/disease/graph` | DiseaseGraphView | **Lazy** |
| `/explorer/disease/table` | DiseaseTableView | Eager |
| `/explorer/compound` | CompoundExplorer | Eager |
| `/explorer/compound/graph` | CompoundGraphView | **Lazy** |
| `/explorer/compound/table` | CompoundTableView | Eager |
| `/explorer/integrated` | IntegratedExplorer (redirect → table) | Eager |
| `/explorer/integrated/graph` | IntegratedGraphView | **Lazy** |
| `/explorer/integrated/table` | IntegratedTableView | Eager |
| `/search` | SearchResults | Eager |
| `/faq` | FAQ | Eager |
| `/how-to-use` | HowToUse | Eager |
| `/contact` | Contact | Eager |
| `/team` | Team | Eager |
| `/test-provenance` | TestProvenance | Eager |
| `*` | NotFound | Eager |

### URL State Management

Graph views use `useSearchParams()` to persist filter state in the URL:

| Param | Used By | Purpose |
|---|---|---|
| `filter=Disease:id1,Symptom:id2` | DiseaseGraphView, CompoundGraphView | Entity type + ID filters |
| `hideTypes=Therapy` | All graph views | Hidden node types |
| `hideEdges=USES` | All graph views | Hidden edge types |
| `seeds=id1,id2` | All graph views | Seed nodes for subgraph fetch |
| `node=id` | DiseaseGraphView, CompoundGraphView | Selected/highlighted node |
| `integratedMode=bridge\|1hop` | IntegratedGraphView | Traversal depth mode |
| `q=` | SearchResults | Search query |
| `tab=disease\|compound\|all` | SearchResults | Active search tab |
| `category=` | Table views | Entity category filter |

### Lazy Loading Pattern

```typescript
const DiseaseGraphView = lazy(() => import("./pages/explorers/DiseaseGraphView"));

// Usage with Suspense
<Suspense fallback={<div className="container py-10 text-sm text-muted-foreground">Loading explorer…</div>}>
  <DiseaseGraphView />
</Suspense>
```

---

## 6. State Management

The application uses **no global state library** (no Redux, Zustand, etc.). State is managed through:

| Mechanism | Where Used | Purpose |
|---|---|---|
| **TanStack React Query** | All API data | Server state caching (staleTime: 30s–5min) |
| **React Context** | `ColorblindProvider` | Colorblind mode (persisted to localStorage) |
| **URL Search Params** | Graph views | Filter, selection state (sharable URLs) |
| **React Hooks** | All components | Local UI state (useState, useRef, etc.) |
| **Sonner Toasts** | SearchResults, GraphViews | User notifications |

### URL State Flow (typical graph view)

```
URL ──parseGraphParams()──► useState(filters, visibleTypes, visibleEdges, seeds)
                                │
                     useEffect(sync to URL) ◄── state changes
                                │
                                ▼
                         useGraph(domain, seeds, depth, ...)
                                │
                                ▼
                        GraphCanvas (nodes, edges)
```

### Colorblind Context

```typescript
// src/hooks/useColorblind.tsx
const { isColorblind, toggleColorblind, getPalette } = useColorblind();
```

- Toggled via button in `TopNav`
- Adds `.colorblind` class to `<html>` → CSS variable overrides (Okabe-Ito palette)
- `getPalette()` function maps node type keys to colorblind-safe hex codes
- Persisted to `localStorage` key: `shodhana-colorblind`

---

## 7. Data Layer

### 7.1 HTTP Client

```typescript
// src/lib/api.ts
const API_BASE = import.meta.env.VITE_API_BASE || import.meta.env.BASE_URL.replace(/\/$/, "");

export async function fetchApi<T>(path: string): Promise<T> {
  const res = await fetch(`${API_BASE}/api/${path}`);
  if (!res.ok) throw new Error(`API ${res.status} ${res.statusText}`);
  const body = await res.json();
  if (body && typeof body === "object" && "ok" in body && !body.ok) {
    throw new Error(body.error?.message || `API request failed`);
  }
  return body as T;
}
```

- Uses **native `fetch`** (no axios/ky)
- API base defaults to `import.meta.env.BASE_URL` (Vite base path)
- All API responses follow `{ ok: boolean, ...data }` convention

### 7.2 API Endpoints

| Endpoint | Hook | Query Key | Purpose |
|---|---|---|---|
| `search.php?domain=&q=&limit=` | `useSearch()` | `["search", domain, query, limit]` | Autocomplete search (250ms debounce) |
| `graph.php?domain=&seeds=&depth=&limitNodes=&limitEdges=&integratedMode=` | `useGraph()` | `["graph", domain, seeds, depth, ...]` | Subgraph for seed nodes |
| `kg-stats.php` | `useKgStats()` | `["kg-stats"]` | Total nodes/relations/studies |
| `counts.php?domain=` | `useCounts()` | `["counts", domain]` | Per-category counts |
| `category-stats.php?domain=&category=...` | `useCategoryStats()` | `["category-stats", domain, category, ...]` | Relationship stats by category |
| `provenance-subgraph.php?diseaseId=&pubmedId=&limit=` | `useProvenanceSubgraph()` | `["provenance-subgraph", diseaseId, ...]` | Disease provenance paths |
| `compound-provenance.php?compoundId=&limit=` | `useCompoundProvenance()` | `["compound-provenance", compoundId, limit]` | Compound provenance paths |
| `bridge-stats.php` | Direct `fetchApi` (no hook) | — | Integrated bridge data |

### 7.3 React Query Configuration

| Hook | staleTime | Notes |
|---|---|---|
| `useSearch` | 30s | placeholderData keeps previous results |
| `useGraph` | 60s | placeholderData keeps previous results |
| `useKgStats` | 5min | Rarely changes |
| `useCounts` | 60s | Category counts |
| `useCategoryStats` | 30s | Paginated category stats |
| `useProvenanceSubgraph` | 60s | Enabled only when diseaseId is truthy |
| `useCompoundProvenance` | 60s | Enabled only when compoundId is truthy |

### 7.4 Fallback Data

When the live API is unreachable, the app falls back to static data defined in:

- `src/data/diseaseGraph.ts` — 20 sample nodes, 19 sample edges
- `src/data/compoundGraph.ts` — 31 sample nodes, 42 sample edges
- `src/data/integratedGraph.ts` — Merged + 5 bridge edges, `kHopSubgraph()` helper

The `searchWithFallback()` pattern:

```typescript
try {
  return await searchApi(domain, query, limit);
} catch {
  return searchFallback(fallbackNodes, query, limit);
}
```

### 7.5 TypeScript Types (`src/lib/types.ts`)

```typescript
export interface ApiNode {
  id: string;
  label: string;
  type: string;
  description?: string;
  [key: string]: unknown;  // Flexible for DB extras
}

export interface ApiEdge {
  id: string;
  source: string;
  target: string;
  type: string;
  pubmedId?: string;
  reference?: string;
  adjuvant?: string;
  dose?: string;
  duration?: string;
  outputMeasure?: string;
  procedure?: string;
  result?: string;
  mediaUsed?: string;
  bridgeNote?: string;
}

export type Domain = "disease" | "compound" | "integrated";

export type CategoryType =
  | "Disease" | "Symptom" | "AyurvedicDiagnosis" | "Dosha"
  | "Therapy" | "Formulation" | "AssessmentParameter"
  | "Compound" | "ShodhanaMethod" | "ToxicSubstance"
  | "PurifiedCompound" | "ShodhanaType" | "Media"
  | "ConfirmationStudy" | "Process";
```

**Provenance types:**

```typescript
interface ProvenancePath {
  disease: ProvenancePathNode;
  edge1: Partial<ProvenancePathEdge>;   // Disease→Therapy
  therapy: ProvenancePathNode;
  edge2: ProvenancePathEdge;             // Therapy→Formulation (with dose/duration/adjuvant)
  formulation: ProvenancePathNode;
  provenance: { pubmedId: string; confidence: 'high' };
}

interface CompoundProvenancePath {
  compound: ProvenancePathNode;
  edge1: { type: string; procedure?: string };  // Compound→ShodhanaMethod
  shodhanaMethod: ProvenancePathNode;
  edge2: { type: string; procedure?: string };  // ShodhanaMethod→Media
  media: ProvenancePathNode;
}
```

---

## 8. Graph Visualization

### 8.1 GraphCanvas Component (`src/components/explorer/GraphCanvas.tsx`)

This is the core visualization component (500 lines), wrapping **raw Cytoscape.js** (not react-cytoscapejs).

#### Props

```typescript
interface Props {
  nodes: GenericNode[];          // Array of { id, label, type, description }
  edges: GenericEdge[];          // Array of { id, source, target, type, pubmedId? }
  nodeColors: Record<string, string>;  // Type → HSL color map
  edgeColors?: Record<string, string>;
  onSelectNode: (n: GenericNode) => void;
  onSelectEdge: (e: GenericEdge) => void;
  onBackgroundClick: () => void;
  highlightedNodeId?: string;
  provenancePaths?: { edge1Id: string; edge2Id: string; pubmedId: string }[];
}
```

#### Implementation Pattern

1. **Container ref** `div` acts as the Cytoscape mount point
2. **`useEffect` on mount** creates `cytoscape({ container, elements, style, layout })`
3. **`useMemo`** computes `ElementDefinition[]` from `nodes` + `edges` props
4. **`useEffect` on elements change** diff-updates: removes old, adds new, preserves positions; runs new layout only when new nodes are detected
5. **Cleanup**: `cy.destroy()` on unmount

#### Node Styling

```
- 34×34 circles (40×40 on selection)
- background-color from nodeColors[type], fallback hsl(25, 25%, 35%)
- Labels below node with text-background (cream pill)
- text-wrap: ellipsis, max-width: 120px
- Transition on selection: border-width 2→3, size 34→40
- Selection: border-color darkens (hsl(25, 25%, 15%)), neighborhood highlights
```

#### Edge Styling

```
- 1.5px bezier curves (2.5px on selection)
- target-arrow-shape: triangle
- Labels autorotate, monospace, 8px, with text-background
- Uniform fallback color: hsl(25, 18%, 45%) — UNIFORM_EDGE_COLOR
- Can be colored per type via edgeColors prop
```

#### Available Layouts

| Key | Name | Parameters |
|---|---|---|
| `cose` | Force-directed | nodeRepulsion 4500, idealEdgeLength 95, gravity 0.45 |
| `breadthfirst` | Hierarchical | directed, spacingFactor 1.2 |
| `circle` | Circular | spacingFactor 1.4 |
| `concentric` | Concentric | By degree, minNodeSpacing 30 |
| `grid` | Grid | avoidOverlap |

Controls in top-right: `<select>` dropdown + zoom in/out/fit buttons.

#### Interactivity

- **Tap node** → select, expand Therapy nodes (toggle), highlight neighborhood, fade rest
- **Tap edge** → select edge, show in DetailsPanel
- **Tap background** → deselect all, reset highlights
- **Hover node** → tooltip popup (label + type)
- **ResizeObserver** → `cy.resize()` on container size change
- **Zoom**: `wheelSensitivity: 0.2`, range [0.3, 2.5]
- **Provenance**: `.provenance-highlight` edges (green 3px, z-999), `.provenance-highlight-dim` (0.2 opacity)

### 8.2 Data-Driven Graph Types

Three domain-specific graph data files define the node/edge schema:

#### Disease (`src/data/diseaseGraph.ts`)

```typescript
type NodeType = "Disease" | "AyurvedicDiagnosis" | "Therapy" | "Formulation"
              | "Symptom" | "AssessmentParameter" | "Dosha";

type EdgeType = "HAS_SYMPTOM" | "HAS_AYURVEDIC_DIAGNOSIS" | "HAS_DOSHA_IMBALANCE"
              | "TREATED_BY" | "PRETREATED_BY" | "POSTTREATED_BY" | "ASSESS" | "USES";
```

#### Compound (`src/data/compoundGraph.ts`)

```typescript
type NodeType = "Compound" | "ShodhanaMethod" | "ToxicSubstance" | "PurifiedCompound"
              | "ShodhanaType" | "Media" | "ConfirmationStudy" | "Process"
              | "Indication" | "AyurvedicFormulation";

type EdgeType = "UNDERGOES" | "USES_MEDIA" | "PRODUCES" | "CLASSIFIED_AS"
              | "CONFIRMS_SAFETY_OF" | "USED_FOR" | "DOSAGE" | "HAS_PROCESS"
              | "OUTCOME_MEASURE" | "TREATED_BY_COMPOUND" | "FORMULATED_IN"
              | "USING_FORMULATION" | "TREATED_WITH" | "TREATS";
```

#### Integrated (`src/data/integratedGraph.ts`)

Imports types from both, adds bridge edge types:
```typescript
type BridgeEdgeType = "PURIFIED_COMPOUND_TREATS" | "PURIFIED_COMPOUND_FORMULATED_IN";
```

Includes a `kHopSubgraph(nodes, edges, seedId, depth)` utility function.

---

## 9. Page-by-Page Breakdown

### 9.1 ModeSelection (Landing Page) — `src/pages/ModeSelection.tsx` (227 lines)

**Rendered at**: `/`

**Components / Features:**
- Red disclaimer banner (academic use only)
- Hero section with title "ShodhanaKG", tagline
- **Global search bar**: navigates to `/search?q=<query>`
- **KG Stats cards**: Total Nodes, Total Relations, Total Studies — fetched via `useKgStats()` with loading skeleton animation
- **3 Mode cards**: Disease, Compound, Integrated — each links to `/explorer/{mode}` with accent colors (terracotta/herbal/bark), hover effects (elevation, icon color shift)

### 9.2 DiseaseExplorer — `src/pages/explorers/DiseaseExplorer.tsx` (229 lines)

**Rendered at**: `/explorer/disease`

**Sub-nav**: Disease Explorer / Graph View / Table View (NavLink with `end` prop)

**Features:**
- Search bar with autocomplete via `useSearch("disease", query, 8)`
- 7 category cards (Disease, Symptom, AyurvedicDiagnosis, Dosha, Therapy, Formulation, AssessmentParameter) — counts from `useCounts("disease")`
- Each card links to `/explorer/disease/table?category={CategoryType}`

### 9.3 DiseaseGraphView — `src/pages/explorers/DiseaseGraphView.tsx` (518 lines)

**Rendered at**: `/explorer/disease/graph`

**Layout**: 3-column grid (300px | 1fr | 320px)

**Left Sidebar:**
- "Filters" section with reset button
- Selected filter chips (color-coded, removable)
- **7 MultiAutocomplete** components: one per node type, each with `onSearch` callback to `searchApi("disease", q, 30, vertexType)`
- Node visibility checkboxes (All/None toggles)
- Edge type visibility checkboxes

**Center:**
- **Legend** strip (node type color dots)
- **GraphCanvas** (Cytoscape) — or empty/loading/error `<GraphLoadingState>`
- Stats overlay: `{nodes} nodes · {edges} edges` + "Truncated" warning
- **Focus mode** toggle (hides sidebars)

**Right Sidebar:**
- Tabs: **Properties** | **Provenance** (shadcn Tabs component)
- Properties: `<DetailsPanel>` with edge property maps for `USES` (dose/duration/adjuvant) and `ASSESS` (outputMeasure/pubmedId)
- Provenance: `<ProvenancePanel>` with `useProvenanceSubgraph(selectedDiseaseId)`, filter by PubMed ID
- Graph provenance highlighting via `useProvenanceHighlighting()` hook

**URL Sync**: `filter=`, `hideTypes=`, `hideEdges=`

### 9.4 DiseaseTableView — `src/pages/explorers/DiseaseTableView.tsx` (367 lines)

**Rendered at**: `/explorer/disease/table`

**Features:**
- Category-driven columns (7 category configs with different columns)
- Client-side category filter from URL `?category=`
- Server-side search, sortable columns, paginated via `useCategoryStats(25 rows/page)`
- Each row links to `/explorer/disease/graph?node=id`

### 9.5 CompoundExplorer — `src/pages/explorers/CompoundExplorer.tsx` (234 lines)

**Rendered at**: `/explorer/compound`

Same pattern as DiseaseExplorer but with 10 category cards. Uses button + `useNavigate` instead of NavLink.

### 9.6 CompoundGraphView — `src/pages/explorers/CompoundGraphView.tsx` (523 lines)

**Rendered at**: `/explorer/compound/graph`

Same 3-column layout as DiseaseGraphView with differences:

- 10 MultiAutocomplete components (one per compound node type)
- `NODE_TYPE_TO_VERTEX` mapping for search API (e.g., `Compound` → `Compound`, `ShodhanaMethod` → `Shodhana_Method`)
- Custom tab toggle (local state with manual buttons, not shadcn Tabs)
- Right panel: `<DetailsPanel>` + `<CompoundProvenancePanel>`
- Edge property maps for `UNDERGOES` (procedure), `USES_MEDIA` (procedure/mediaUsed), `CLASSIFIED_AS` (result), `CONFIRMS_SAFETY_OF` (result/dose/duration)
- Graph limits: 120 nodes / 300 edges

### 9.7 CompoundTableView — `src/pages/explorers/CompoundTableView.tsx` (433 lines)

Same pattern as DiseaseTableView with 10 categories. Each category has its own column definitions.

### 9.8 IntegratedExplorer — `src/pages/explorers/IntegratedExplorer.tsx` (7 lines)

Just a redirect: `<Navigate to="/explorer/integrated/table" replace />`

### 9.9 IntegratedGraphView — `src/pages/explorers/IntegratedGraphView.tsx` (702 lines)

**Rendered at**: `/explorer/integrated/graph`

**Most complex page**. Key differences from other graph views:

- **No MultiAutocomplete** components
- **Single search bar** at top with debounced async autocomplete (250ms) via `searchApi("integrated", q, 30)`
- **Left sidebar**: filter chips, node/edge visibility, **depth mode buttons** (`bridge` | `1hop`)
- **Right sidebar**: bridge node/edge banner, DetailsPanel, connected-entity chips
- **Graph limits**: 300 nodes / 900 edges (larger due to merged data)
- Custom `mapApiNode`/`mapApiEdge` to add `side: "disease" | "compound" | "bridge"` metadata
- URL sync: `seeds=`, `hideTypes=`, `hideEdges=`, `integratedMode=`

### 9.10 IntegratedTableView — `src/pages/explorers/IntegratedTableView.tsx` (389 lines)

**No React Query** — uses direct `fetchApi("bridge-stats.php")` wrapped in `useEffect` + local state.

**Features:**
- Client-side filtering/sorting via `useMemo`
- **Expandable rows** showing connected disease labels
- Shows purified-compound → disease bridge data only
- No URL state management

### 9.11 SearchResults — `src/pages/SearchResults.tsx` (383 lines)

**Rendered at**: `/search?q=<query>&tab=<tab>`

- Employs TypeScript record maps (`TYPE_TO_DOMAIN`, `TYPE_LABELS`, `TYPE_COLORS`) for type metadata
- Searches across all domains via `searchApi("integrated", query, 2000)`
- **Tab navigation**: All / Disease / Compound with result counts
- Each result links to the appropriate graph view: `?node={id}` for domain-specific, `?seeds={id}` for integrated
- Client-side pagination (20 per page)
- URL sync: `q=`, `tab=`

### 9.12 Static Pages

| Page | File | Content |
|---|---|---|
| **FAQ** | `src/pages/FAQ.tsx` | Accordion with questions about the platform |
| **HowToUse** | `src/pages/HowToUse.tsx` | Step-by-step guide with screenshots |
| **Contact** | `src/pages/Contact.tsx` | Contact information + image |
| **Team** | `src/pages/Team.tsx` | Developer profiles |
| **NotFound** | `src/pages/NotFound.tsx` | 404 with `useLocation` logging |
| **TestProvenance** | `src/pages/TestProvenance.tsx` | Test harness for provenance feature |

---

## 10. Shared Components

### 10.1 AppLayout — `src/components/layout/AppLayout.tsx` (89 lines)

Renders the persistent shell around all routes:
- `<TopNav />` (sticky header)
- Breadcrumb (shown only on explorer sub-pages)
- `<main>` with `<Outlet />` wrapped in `key={location.pathname}` for animation
- Footer with About, Resources, Other Databases links, copyright
- `<ChatbotWidget />` (hidden on `/graph` routes)

### 10.2 TopNav — `src/components/layout/TopNav.tsx` (157 lines)

Sticky header with:
- Brand logo + "ShodhanaKG" title + tagline
- **Home** link
- **Explorer dropdown**: Disease / Compound / Integrated
- **Knowledge Graph dropdown**: Disease Graph / Compound Graph / Integrated Graph
- **Help dropdown**: FAQs / How to Use
- **About Us dropdown**: Developers / Contact
- **Colorblind toggle** (rounded pill button, blue when active)

### 10.3 GraphCanvas — `src/components/explorer/GraphCanvas.tsx` (500 lines)

Detailed in [Section 8](#8-graph-visualization).

### 10.4 DetailsPanel — `src/components/explorer/DetailsPanel.tsx` (180 lines)

Props:
```typescript
interface Props {
  selectedNode?: GenericNode;
  selectedEdge?: { edge: GenericEdge; source: GenericNode; target: GenericNode };
  nodeTypeLabels: Record<string, string>;
  edgeTypeLabels: Record<string, string>;
  nodeColors: Record<string, string>;
  edgeColors: Record<string, string>;
  edgePropertyMap?: Record<string, { label: string; key: string }[]>;
}
```

- Empty state: "Nothing selected" prompt
- **Node mode**: color dot + type badge + label + description + dynamic properties (filters out `id`/`label`/`type`/`description`/`@cat`, skips null/empty)
- **Edge mode**: source→target visual flow (colored pills with arrow), type label, property grid, PubMed link button

### 10.5 MultiAutocomplete — `src/components/explorer/MultiAutocomplete.tsx` (286 lines)

Props:
```typescript
interface Props {
  label: string;
  options: Option[];
  selected: string[];
  onChange: (ids: string[]) => void;
  placeholder?: string;
  accentClass?: string;
  onSearch?: (query: string) => Promise<Option[]>;
}
```

- **Tag input** with selected chips (removable) + search input
- **Async search** via `onSearch` callback with 250ms debounce + race condition guard (sequence counter)
- **Fixed-position dropdown** that flips above if space below is insufficient
- **Keyboard navigation**: ArrowDown/ArrowUp (navigate), Enter (select), Backspace (remove last), Escape (close)
- 8 result limit in dropdown
- Seen options cache (`SEEN` Map ref) to preserve labels for selected IDs

### 10.6 Legend — `src/components/explorer/Legend.tsx` (49 lines)

Two-column node type legend + single-column edge type legend with color indicators.

### 10.7 ExportMenu — `src/components/explorer/ExportMenu.tsx` (69 lines)

Dropdown with 3 options:
- **CSV**: Nodes + Edges as CSV with headers
- **JSON**: Full graph dump with timestamp
- **PNG**: Composite screenshot of Cytoscape canvases with cream background

Calls functions from `src/lib/graphExport.ts`.

### 10.8 ProvenancePanel — `src/components/explorer/ProvenancePanel.tsx` (277 lines)

Displays Disease→Therapy→Formulation paths from `useProvenanceSubgraph()`:

- **Filter by PubMed ID** via `<Select>` dropdown
- Each path rendered as a `PathCard`:
  - Provenance badge (green checkmark) + PMID link
  - Visual path: D → arrow → T → arrow → F with colored circles
  - Clinical details: dose, duration, adjuvant
  - Descriptions for therapy and formulation
- Loading / error / empty states
- Query time footer

### 10.9 CompoundProvenancePanel — `src/components/explorer/CompoundProvenancePanel.tsx` (176 lines)

Same pattern as ProvenancePanel for Compound→ShodhanaMethod→Media paths:
- No PubMed filter (no co-citation for compound provenance)
- Shows procedure details on edges
- Color-coded circles: C (green), M (orange), Md (teal)

### 10.10 GraphProvenance — `src/components/explorer/GraphProvenance.tsx` (98 lines)

Utility module:
- `useProvenanceHighlighting(diseaseId)` — extracts edge IDs for graph highlighting
- `filterGraphByProvenance()` — filters visible edges to provenance-validated only

### 10.11 ChatbotWidget — `src/components/chatbot/ChatbotWidget.tsx` (135 lines)

Floating chatbot with:
- Green gradient circular toggle button (bottom-right)
- Panel with header, scrollable message area, input form
- Rule-based replies matching keywords (disease, compound, filter, therapy, etc.)
- Suggestion chips on first message
- Hidden on `/graph` routes via AppLayout

### 10.12 UI Components (shadcn/ui) — `src/components/ui/`

49 boilerplate components generated via `npx shadcn@latest add`. **DO NOT hand-edit.**

Includes: accordion, alert, alert-dialog, aspect-ratio, avatar, badge, breadcrumb, button, calendar, card, carousel, chart, checkbox, collapsible, command, context-menu, dialog, drawer, dropdown-menu, form, hover-card, input, input-otp, label, menubar, navigation-menu, pagination, popover, progress, radio-group, resizable, scroll-area, select, separator, sheet, sidebar, skeleton, slider, sonner, switch, table, tabs, textarea, toast, toaster, toggle, toggle-group, tooltip, use-toast.

---

## 11. Styling & Design System

### 11.1 Design Tokens (`src/index.css`)

All brand colors as **HSL custom properties** on `:root`:

```css
--background: 40 38% 95%;           /* warm cream */
--foreground: 25 25% 15%;           /* deep brown ink */
--primary: 130 22% 30%;             /* muted herbal green */
--secondary: 38 30% 88%;            /* soft sand */
--accent: 16 45% 50%;               /* terracotta */
--herbal: 130 22% 30%;              /* brand green */
--terracotta: 16 45% 50%;          /* brand terracotta */
--bark: 25 30% 25%;                 /* deep brown */
--radius: 0.625rem;
```

Custom utility classes:
- `bg-gradient-warm` / `bg-gradient-herbal`
- `shadow-card` / `shadow-elevated`
- `transition-smooth` — `all 0.25s cubic-bezier(0.4, 0, 0.2, 1)`

### 11.2 Typography

| Usage | Font | Applied Via |
|---|---|---|
| Headings | **Fraunces** (serif) | `font-display` class |
| Body | **Inter** (sans-serif) | Default on `<html>` |

### 11.3 Colorblind Mode

Toggle adds `.colorblind` class to `<html>`:

```css
:root.colorblind {
  --primary: 164 100% 31%;          /* Bluish Green */
  --terracotta: 39 100% 45%;        /* Orange */
  --accent: 26 100% 42%;            /* Vermillion */
  /* Graph node colors → Okabe-Ito palette */
  --muted-teal: #009E73;            /* Bluish Green */
  --yale-blue: #0072B2;             /* Blue */
  --royal-gold: #F0E442;            /* Yellow */
  --brown-red: #D55E00;             /* Vermillion */
  --pink-orchid: #CC79A7;           /* Reddish Purple */
  --deep-walnut: #000000;           /* Black */
}
```

### 11.4 Tailwind Configuration (`tailwind.config.ts`)

- `darkMode: ["class"]` (class-based, not system)
- All design tokens mapped to Tailwind color utilities (e.g., `bg-herbal`, `text-terracotta`, `border-bark`)
- Custom `sidebar` color set
- `accordion-down` / `accordion-up` keyframes

### 11.5 Vite Configuration (`vite.config.ts`)

```typescript
export default defineConfig(({ mode }) => ({
  base: "/APP_BASE_PATH/",               // Production base path
  server: {
    host: "::",                                // IPv6 + IPv4
    port: 8080,
    hmr: { overlay: false },                   // No HMR error overlay
  },
  plugins: [react()],                          // @vitejs/plugin-react-swc
  resolve: {
    alias: { "@": path.resolve(__dirname, "./src") },
    dedupe: ["react", "react-dom", ...],       // Avoid duplicate React instances
  },
}));
```

---

## 12. Accessibility

- **Colorblind mode** — full Okabe-Ito color palette toggle
- **ARIA labels** on all interactive elements (graph controls, chatbot, export)
- **Tooltip** on graph node hover and provenance badges
- **Keyboard navigation** in MultiAutocomplete (arrows, enter, backspace, escape)
- **Screen reader-friendly** empty/loading/error states
- **Focus indicators** on all form controls and interactive elements

---

## 13. Testing

### Unit Tests (Vitest)

| File | Purpose |
|---|---|
| `src/test/setup.ts` | Polyfills `matchMedia` for Radix UI components |
| `src/test/example.test.ts` | Placeholder |
| `src/lib/api.provenance.test.tsx` | API test for provenance |

**Commands:**
```bash
npm test              # vitest run (single run)
npm run test:watch    # vitest (watch mode)
```

### E2E Tests (Playwright)

Located in `e2e/` directory. Config: `playwright.config.ts`.

**Commands:**
```bash
npx playwright test                    # Full suite (all browsers)
npx playwright test --grep "Route smoke"  # Route smoke tests only
npx playwright test --project chromium --grep "Mobile|overflow"
```

---

## 14. Build & Deployment

### Development

```bash
npm install
npm run dev            # Vite dev server on http://localhost:8080
```

### Production Build

```bash
npm run build          # Output to dist/, base path: /APP_BASE_PATH/
npm run build:dev      # Build with --mode development
npm run preview        # Vite preview of production build
npm run lint           # ESLint on src/ (flat config)
```

### Environment Variables

See `.env` and `.env.production`:
```
VITE_API_BASE=http://localhost:8081
```

The API base defaults to `import.meta.env.BASE_URL` if `VITE_API_BASE` is not set.

### PHP Backend

```bash
cd api
php -S localhost:8081 -t .
```

---

## 15. File Reference

### All Frontend Source Files

| # | File Path | Lines | Type |
|---|---|---|---|
| 1 | `src/main.tsx` | 5 | Entrypoint |
| 2 | `src/App.tsx` | 71 | Root component |
| 3 | `src/index.css` | 195 | Styles |
| 4 | `src/lib/api.ts` | 378 | API client + hooks |
| 5 | `src/lib/types.ts` | 146 | TypeScript types |
| 6 | `src/lib/utils.ts` | 6 | cn() helper |
| 7 | `src/lib/graphExport.ts` | 103 | CSV/JSON/PNG export |
| 8 | `src/hooks/useColorblind.tsx` | 80 | Colorblind context |
| 9 | `src/hooks/use-toast.ts` | 47 | Toast hook |
| 10 | `src/hooks/use-mobile.tsx` | 17 | Mobile detection |
| 11 | `src/data/diseaseGraph.ts` | 155 | Disease graph data |
| 12 | `src/data/compoundGraph.ts` | 239 | Compound graph data |
| 13 | `src/data/integratedGraph.ts` | 355 | Integrated graph data |
| 14 | `src/components/layout/AppLayout.tsx` | 89 | Shell layout |
| 15 | `src/components/layout/TopNav.tsx` | 157 | Navigation header |
| 16 | `src/components/explorer/GraphCanvas.tsx` | 500 | Cytoscape graph |
| 17 | `src/components/explorer/DetailsPanel.tsx` | 180 | Node/edge inspector |
| 18 | `src/components/explorer/Legend.tsx` | 49 | Color legend |
| 19 | `src/components/explorer/MultiAutocomplete.tsx` | 286 | Tag autocomplete |
| 20 | `src/components/explorer/ExportMenu.tsx` | 69 | Export dropdown |
| 21 | `src/components/explorer/ProvenancePanel.tsx` | 277 | Disease provenance |
| 22 | `src/components/explorer/CompoundProvenancePanel.tsx` | 176 | Compound provenance |
| 23 | `src/components/explorer/GraphProvenance.tsx` | 98 | Provenance hooks |
| 24 | `src/components/chatbot/ChatbotWidget.tsx` | 135 | Chatbot |
| 25 | `src/pages/Index.tsx` | 5 | Re-export |
| 26 | `src/pages/ModeSelection.tsx` | 227 | Landing page |
| 27 | `src/pages/SearchResults.tsx` | 383 | Search results |
| 28 | `src/pages/NotFound.tsx` | 24 | 404 error |
| 29 | `src/pages/FAQ.tsx` | ~50 | FAQs |
| 30 | `src/pages/HowToUse.tsx` | ~50 | Usage guide |
| 31 | `src/pages/Contact.tsx` | ~50 | Contact |
| 32 | `src/pages/Team.tsx` | ~50 | Team |
| 33 | `src/pages/TestProvenance.tsx` | ~50 | Provenance test |
| 34 | `src/pages/explorers/DiseaseExplorer.tsx` | 229 | Disease home |
| 35 | `src/pages/explorers/DiseaseGraphView.tsx` | 518 | Disease graph |
| 36 | `src/pages/explorers/DiseaseTableView.tsx` | 367 | Disease table |
| 37 | `src/pages/explorers/CompoundExplorer.tsx` | 234 | Compound home |
| 38 | `src/pages/explorers/CompoundGraphView.tsx` | 523 | Compound graph |
| 39 | `src/pages/explorers/CompoundTableView.tsx` | 433 | Compound table |
| 40 | `src/pages/explorers/IntegratedExplorer.tsx` | 7 | Redirect |
| 41 | `src/pages/explorers/IntegratedGraphView.tsx` | 702 | Integrated graph |
| 42 | `src/pages/explorers/IntegratedTableView.tsx` | 389 | Integrated table |

### Configuration Files

| File | Purpose |
|---|---|
| `vite.config.ts` | Vite build config |
| `tailwind.config.ts` | Tailwind theme |
| `tsconfig.json` | Root TS config |
| `tsconfig.app.json` | App TS config |
| `tsconfig.node.json` | Node TS config |
| `eslint.config.js` | ESLint flat config |
| `postcss.config.js` | PostCSS config |
| `playwright.config.ts` | E2E test config |
| `vitest.config.ts` | Unit test config |
| `components.json` | shadcn/ui config |
| `.env` / `.env.production` | Environment variables |
| `apache.conf.example` | Apache deployment config |
| `index.html` | Vite HTML entrypoint |
