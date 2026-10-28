# Frontend Documentation

## Table of Contents

1. [Tech Stack](#1-tech-stack)
2. [Project Overview](#2-project-overview)
3. [Architecture](#3-architecture)
4. [Component Tree](#4-component-tree)
5. [Routing](#5-routing)
6. [State Management](#6-state-management)
7. [Data Layer](#7-data-layer)
8. [Graph Visualization](#8-graph-visualization)
9. [Page Breakdown](#9-page-breakdown)
10. [Shared Components](#10-shared-components)
11. [Styling and Design System](#11-styling-and-design-system)
12. [Accessibility](#12-accessibility)
13. [Testing](#13-testing)
14. [Build and Configuration](#14-build-and-configuration)
15. [File Reference](#15-file-reference)

---

## 1. Tech Stack

| Category | Technology | Version |
|---|---|---|
| Framework | React | 18.3.1 |
| Language | TypeScript | 5.8.3 |
| Build Tool | Vite | 7.3.7 |
| Routing | react-router-dom | 7.18.4 |
| Data Fetching | TanStack React Query | 5.83.0 |
| Styling | Tailwind CSS | 4.3.3 |
| Component Primitives | Radix UI | Various |
| Graph Visualization | Cytoscape.js | 3.33.2 |
| Charts | Recharts | 2.15.4 |
| Form Handling | react-hook-form / Zod | 7.61.1 / 3.25.76 |
| Icons | Lucide React | 0.462.0 |
| Unit Testing | Vitest / jsdom | 5.0.3 / 20.0.3 |
| End-to-End Testing | Playwright | 1.60.0 |
| Node Engine Requirement | Node.js | ^22.12.0 \|\| ^24.0.0 \|\| >=26.0.0 |

---

## 2. Project Overview

The ShodhanaKG frontend is a single-page React application for visualizing and navigating an Ayurvedic biomedical knowledge graph. It allows users to explore connections across diseases, symptoms, therapies, formulations, medicinal compounds, and Shodhana purification processes.

The frontend connects to the backend PHP API. The API queries an ArcadeDB graph database.

The application provides three explorer modes:
- Disease Explorer: Visualizes clinical conditions, symptoms, dosha associations, diagnostic classifications, therapies, and formulations.
- Compound Explorer: Visualizes raw botanical and mineral compounds, toxic substances, Shodhana purification methods, processing media, and purified products.
- Integrated Explorer: Visualizes cross-domain relationships linking purified compounds to therapeutic formulations and disease treatments.

---

## 3. Architecture

The frontend source code is located in `webservice/frontend/`.

Directory structure:
- `webservice/frontend/vite.config.ts`: Vite build and local development configuration.
- `webservice/frontend/tsconfig.json`: TypeScript configuration files.
- `webservice/frontend/public/`: Static assets, embedded fonts, and open-source font licenses.
- `webservice/frontend/src/`: Application source code.
  - `src/main.tsx`: Entry point rendering the root component.
  - `src/App.tsx`: Root component with application providers, router setup, and lazy route definitions.
  - `src/index.css`: Global styles, design tokens, and colorblind mode CSS variables.
  - `src/lib/`: API clients, Cytoscape styling, TypeScript types, and export utilities.
  - `src/hooks/`: React hooks for colorblind theme context, toast alerts, and mobile viewport detection.
  - `src/data/`: Graph schemas, entity types, domain mappings, and color token definitions.
  - `src/components/`: React components organized by feature (layout, explorer, chatbot, UI primitives).
  - `src/pages/`: Page-level components and explorer views.
  - `src/test/`: Testing setup and unit test specifications.
- `webservice/frontend/tests/`: Playwright browser automation and end-to-end test suites.

---

## 4. Component Tree

The top-level provider hierarchy in `src/App.tsx`:

```
<App>
  <QueryClientProvider>
    <ColorblindProvider>
      <TooltipProvider>
        <Toaster />
        <Sonner />
        <BrowserRouter>
          <Routes>
            <Route element={<AppLayout />}>
              <TopNav />
              <Breadcrumb />
              <Outlet />
              <Footer />
              <ChatbotWidget />
            </Route>
            <Route path="*" element={<NotFound />} />
          </Routes>
        </BrowserRouter>
      </TooltipProvider>
    </ColorblindProvider>
  </QueryClientProvider>
</App>
```

### Explorer Layout Structure

Explorer graph pages use a three-column layout:
- Left Column: Search filters, type visibility checkboxes, and edge visibility toggles.
- Center Column: Color legend strip, interactive Cytoscape graph canvas, and node/edge count overlay.
- Right Column: Tabs for entity properties (`DetailsPanel`) and literature evidence (`ProvenancePanel`).

A focus mode toggle collapses both sidebars for full-screen graph exploration.

---

## 5. Routing

Routes are defined in `src/App.tsx` using `react-router-dom`:

| Route Path | Component | Loading Strategy |
|---|---|---|
| `/` | ModeSelection | Eager |
| `/explorer/disease` | DiseaseExplorer | Eager |
| `/explorer/disease/graph` | DiseaseGraphView | Lazy |
| `/explorer/disease/table` | DiseaseTableView | Eager |
| `/explorer/compound` | CompoundExplorer | Eager |
| `/explorer/compound/graph` | CompoundGraphView | Lazy |
| `/explorer/compound/table` | CompoundTableView | Eager |
| `/explorer/integrated` | IntegratedExplorer (redirects to table) | Eager |
| `/explorer/integrated/graph` | IntegratedGraphView | Lazy |
| `/explorer/integrated/table` | IntegratedTableView | Eager |
| `/search` | SearchResults | Lazy |
| `/advanced-search` | AdvancedSearch | Lazy |
| `/faq` | FAQ | Lazy |
| `/how-to-use` | HowToUse | Lazy |
| `/contact` | Contact | Lazy |
| `/team` | Team | Lazy |
| `*` | NotFound | Lazy |

### URL State Management

Graph explorer views synchronize state with URL search parameters using `useSearchParams()`:
- `filter`: Entity type and identifier filters.
- `hideTypes`: Comma-separated list of hidden vertex types.
- `hideEdges`: Comma-separated list of hidden edge types.
- `seeds`: Seed vertex identifiers for subgraph retrieval.
- `node`: Selected vertex identifier for details and provenance views.
- `integratedMode`: Traversal depth mode (`bridge` or `1hop`).
- `q`: Search query string.
- `tab`: Active domain filter tab on search results.
- `category`: Category filter on table views.

---

## 6. State Management

The application does not use external global state stores like Redux or Zustand. State is partitioned across four layers:
- Server State: Managed by TanStack React Query (`useQuery`). Responses are cached with configurable stale times.
- Application Theme State: Managed by React Context (`ColorblindProvider`). State is persisted to browser local storage.
- Navigation and Filter State: Managed in URL search parameters via `useSearchParams()`.
- Component UI State: Managed using standard React hooks (`useState`, `useRef`, `useMemo`, `useCallback`).

---

## 7. Data Layer

### HTTP Client

API calls are handled in `src/lib/api.ts` using the native browser `fetch` function:
- Base URL is read from `VITE_API_BASE` or falls back to `BASE_URL`.
- Endpoints return JSON conforming to standard response envelopes with an `ok` boolean flag.
- Errors throw JavaScript exceptions containing server error messages.

### React Query Hooks

| Hook | Primary Endpoint | Default Stale Time | Purpose |
|---|---|---|---|
| `useSearch` | `search.php` | 30 seconds | Autocomplete search across entity labels |
| `useGraph` | `graph.php` | 60 seconds | Subgraph retrieval for seed vertices |
| `useKgStats` | `kg-stats.php` | 5 minutes | Knowledge graph vertex and edge totals |
| `useCounts` | `counts.php` | 60 seconds | Vertex counts per domain category |
| `useCategoryStats` | `category-stats.php` | 30 seconds | Paginated relationship records by category |
| `useProvenanceSubgraph` | `provenance-subgraph.php` | 60 seconds | Co-cited disease provenance paths |
| `useCompoundProvenance` | `compound-provenance.php` | 60 seconds | Multi-hop compound processing paths |

Cross-domain bridge records are retrieved directly in `IntegratedTableView.tsx` via `fetchApi("bridge-stats.php")`.

### Core Data Models

TypeScript interfaces are declared in `src/lib/types.ts`:
- `ApiNode`: Generic graph node with `id`, `label`, `type`, and optional description.
- `ApiEdge`: Graph relationship with `id`, `source`, `target`, `type`, and optional literature citation attributes (`pubmedId`, `reference`).
- `ProvenancePath`: Multi-hop path linking disease, therapy, and formulation with matched literature citation metadata.
- `CompoundProvenancePath`: Multi-hop path linking compound, Shodhana method, and purification media.

---

## 8. Graph Visualization

### GraphCanvas Component

The `GraphCanvas` component (`src/components/explorer/GraphCanvas.tsx`) wraps Cytoscape.js directly.

Key characteristics:
- Mounts a Cytoscape instance inside a container reference.
- Computes element definitions from node and edge arrays using `useMemo`.
- Performs incremental updates on element changes to preserve existing node coordinates.
- Adjusts viewport size automatically with `ResizeObserver`.
- Destroys Cytoscape instances on unmount to prevent memory leaks.

### Layout Algorithms

Users can switch layouts using the canvas controls:
- `cose`: Force-directed layout for clustered networks.
- `breadthfirst`: Hierarchical tree layout.
- `circle`: Circular distribution.
- `concentric`: Concentric ring layout sorted by node degree.
- `grid`: Regular rectangular matrix layout.

### Visual Styling

- Nodes: Rendered as circles with background colors assigned from `nodeColors` mapping. Selected nodes expand with darker borders.
- Labels: Displayed beneath nodes with rounded background contrast pills.
- Edges: Rendered as directional bezier curves with target arrows.
- Provenance Highlighting: Validated co-cited paths render with thick green lines. Non-provenance edges are dimmed.

---

## 9. Page Breakdown

- ModeSelection (`src/pages/ModeSelection.tsx`): Landing page with global search, overall graph statistics, and entry cards for the three explorer modes.
- DiseaseExplorer (`src/pages/explorers/DiseaseExplorer.tsx`): Disease domain landing page with autocomplete search and category count cards.
- DiseaseGraphView (`src/pages/explorers/DiseaseGraphView.tsx`): Interactive graph canvas, filter sidebars, category autocompletes, details inspector, and disease provenance viewer.
- DiseaseTableView (`src/pages/explorers/DiseaseTableView.tsx`): Paginated tabular view of disease domain entities and relationships.
- CompoundExplorer (`src/pages/explorers/CompoundExplorer.tsx`): Compound domain landing page with category cards.
- CompoundGraphView (`src/pages/explorers/CompoundGraphView.tsx`): Interactive graph canvas for botanical and mineral compounds, Shodhana methods, media, and purified products.
- CompoundTableView (`src/pages/explorers/CompoundTableView.tsx`): Paginated tabular view of compound domain entities.
- IntegratedExplorer (`src/pages/explorers/IntegratedExplorer.tsx`): Navigation redirect to the integrated table view.
- IntegratedGraphView (`src/pages/explorers/IntegratedGraphView.tsx`): Cross-domain graph canvas supporting bridge exploration and single-hop expansions.
- IntegratedTableView (`src/pages/explorers/IntegratedTableView.tsx`): Tabular view of verified bridge connections linking purified compounds to formulations and clinical indications.
- SearchResults (`src/pages/SearchResults.tsx`): Global multi-domain search results with tabbed filtering and direct navigation to graph views.
- AdvancedSearch (`src/pages/AdvancedSearch.tsx`): Multi-field property search interface.
- Static Information Pages: FAQ (`src/pages/FAQ.tsx`), HowToUse (`src/pages/HowToUse.tsx`), Contact (`src/pages/Contact.tsx`), Team (`src/pages/Team.tsx`), and NotFound (`src/pages/NotFound.tsx`).

---

## 10. Shared Components

- `AppLayout` (`src/components/layout/AppLayout.tsx`): Application layout shell with sticky navigation, main view container, footer, and chatbot launcher.
- `TopNav` (`src/components/layout/TopNav.tsx`): Header navigation bar with dropdown menus, external links, and colorblind mode toggle.
- `DetailsPanel` (`src/components/explorer/DetailsPanel.tsx`): Inspector panel displaying attributes of selected nodes and relationships.
- `MultiAutocomplete` (`src/components/explorer/MultiAutocomplete.tsx`): Filter component with debounced asynchronous search, removable tag chips, and keyboard navigation.
- `Legend` (`src/components/explorer/Legend.tsx`): Color-coded legend displaying active vertex and edge types.
- `ExportMenu` (`src/components/explorer/ExportMenu.tsx`): Dropdown exporting active graph data to CSV, JSON, or PNG image format.
- `ProvenancePanel` (`src/components/explorer/ProvenancePanel.tsx`): Displays verified disease-therapy-formulation pathways with PubMed identifiers and clinical parameters.
- `CompoundProvenancePanel` (`src/components/explorer/CompoundProvenancePanel.tsx`): Displays compound-method-media processing pathways.
- `ChatbotWidget` (`src/components/chatbot/ChatbotWidget.tsx`): Floating chatbot interface connecting to the backend conversational API.
- UI Primitives (`src/components/ui/`): Radix UI components styled with Tailwind CSS.

---

## 11. Styling and Design System

- Design Tokens: Defined in `src/index.css` using HSL CSS custom properties (`--background`, `--foreground`, `--primary`, `--secondary`, `--accent`, `--herbal`, `--terracotta`, `--bark`).
- Typography: Headings use the Fraunces display serif font. Body text uses the Inter sans-serif font.
- Colorblind Palette: Toggling colorblind mode applies the `.colorblind` CSS class to the root element. This remaps entity colors to the accessible Okabe-Ito palette.

---

## 12. Accessibility

- Colorblind Mode: Dedicated palette toggle providing high-contrast distinguishable hues.
- Keyboard Navigation: Dropdown menus, autocomplete inputs, dialogs, and tabs support standard keyboard controls.
- ARIA Attributes: Interactive elements, tabs, tooltips, and modal dialogs include ARIA roles and labels.
- Responsive Viewport: Graph canvas and sidebars adapt to smaller viewports with collapsible panels.

---

## 13. Testing

The frontend test suite includes unit, component, and browser end-to-end tests:

- Unit and Component Tests: Built with Vitest and `@testing-library/react`.
  - Configuration: `webservice/frontend/vitest.config.ts`.
  - Run command: `npm --prefix webservice/frontend test`.
  - Watch mode command: `npm --prefix webservice/frontend run test:watch`.
- End-to-End Tests: Built with Playwright.
  - Configuration: `webservice/frontend/playwright.config.ts`.
  - Test files: Located in `webservice/frontend/tests/`.
  - Run command: `npm --prefix webservice/frontend run test:e2e`.

---

## 14. Build and Configuration

### Environment Variables

Environment variables are loaded from `.env` in the repository root:
- `FRONTEND_HOST`: Host interface for development and preview servers.
- `FRONTEND_PORT`: Port for development and preview servers (default: 8080).
- `API_HOST`: Host of the backend PHP API.
- `API_PORT`: Port of the backend PHP API (default: 8081).
- `API_PROXY_TARGET`: Target URL for Vite API proxy forwarding.
- `VITE_API_BASE`: Base path for API requests (empty for relative proxy in development; path string for subdirectory production).
- `VITE_CHAT_ENABLED`: Boolean toggle to display or hide the chatbot widget (`true` or `false`).

### Development Commands

Run all commands from `webservice/frontend/` or with `--prefix webservice/frontend`:
- Start development server: `npm run dev`
- Typecheck TypeScript files: `npm run typecheck`
- Production build: `npm run build`
- Preview production build: `npm run preview`
- Run linter: `npm run lint`

---

## 15. File Reference

Key configuration files:
- `webservice/frontend/package.json`: Dependencies, scripts, and engine requirements.
- `webservice/frontend/vite.config.ts`: Vite build, proxy, and development server configuration.
- `webservice/frontend/playwright.config.ts`: Playwright browser test runner configuration.
- `webservice/frontend/vitest.config.ts`: Vitest test runner configuration.
- `webservice/frontend/tsconfig.json`: TypeScript compiler options.
- `webservice/frontend/components.json`: Component library configuration.
