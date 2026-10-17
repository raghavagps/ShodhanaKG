# GEMINI.md — ShodhanaKG Explorer Suite

## Stack
- **Framework:** React 18 + Vite 5 + TypeScript (strict: false, noImplicitAny: false)
- **Routing:** react-router-dom v6 (nested routes via `<Outlet>` in `AppLayout`)
- **Data fetching:** @tanstack/react-query v5 (native fetch, no axios)
- **Styling:** Tailwind CSS v3 + `tailwindcss-animate` + `@tailwindcss/typography`
- **UI library:** shadcn/ui (Radix primitives + `class-variance-authority` + `tailwind-merge`)
- **Graph visualization:** raw cytoscape (NOT react-cytoscapejs — manual ref-based mount)
- **Charts:** recharts
- **Forms:** react-hook-form + zod + @hookform/resolvers
- **Dev server:** port 8080, HMR overlay disabled
- **Package manager:** npm (lockfile: `package-lock.json`); `bun.lock`/`bun.lockb` are stale

## Commands
```sh
npm run dev          # Vite dev server on :8080
npm run build        # Production build to dist/
npm run build:dev    # Build with --mode development
npm run lint         # eslint . (flat config, ignores dist/)
npm run preview      # Vite preview of production build
npm test             # vitest run (single run)
npm run test:watch   # vitest (watch mode)
npx playwright test              # full Playwright suite (all browsers)
npx playwright test --grep "Route smoke"  # route smoke tests only
npx playwright test --project chromium --grep "Mobile|overflow"  # mobile overflow in chromium only
```

## Developer workflow (order matters)
1. `npm run lint` — catches TS + react-hooks issues; `@typescript-eslint/no-unused-vars` is **off**, so unused vars won't fail
2. `npm run build` — type-check is baked into Vite (no separate `tsc` step)
3. `npm test` — vitest with jsdom environment
4. `npx playwright test --project chromium --grep "Route smoke|Mobile"` — fast smoke check in chromium only (requires dev server on :8080)

## Critical rule
- **NEVER kill or restart the npm dev server automatically.** After any file change or build, always ask the user to restart the dev server themselves. The dev server belongs to the user's session and should not be managed by the agent.

## Project structure

```
api/                  # PHP backend — ArcadeDB connector (not part of Vite build)
src/
  main.tsx            # Entrypoint — renders <App />
  App.tsx             # Routes + providers (QueryClient, BrowserRouter, TooltipProvider)
  pages/
    Index.tsx         # Landing page
    ModeSelection.tsx # Explorer mode picker
    NotFound.tsx      # 404
    explorers/        # 3 explorer modes × 3 views each
      DiseaseExplorer / DiseaseGraphView / DiseaseTableView
      CompoundExplorer / CompoundGraphView / CompoundTableView
      IntegratedExplorer / IntegratedGraphView / IntegratedTableView
      ExplorerShell.tsx  # Shared explorer layout wrapper
  components/
    ui/               # shadcn/ui components (49 files — do NOT edit directly; they are boilerplate)
    explorer/         # Shared explorer widgets: GraphCanvas, DetailsPanel, Legend, ExportMenu, MultiAutocomplete
    layout/           # AppLayout, TopNav
    chatbot/          # ChatbotWidget
  data/               # Static graph data (diseaseGraph.ts, compoundGraph.ts, integratedGraph.ts)
  lib/
    utils.ts          # cn() helper
    graphExport.ts    # CSV / JSON / PNG export helpers
    types.ts          # API DTOs (ApiNode, ApiEdge, ApiSearchResponse, Domain, etc.)
    api.ts            # HTTP client + React Query hooks with fallback to static data
  hooks/              # use-mobile, use-toast
  test/
    setup.ts          # vitest setup — imports @testing-library/jest-dom, mocks matchMedia
    example.test.ts   # placeholder
```

## Key conventions
- **Path alias:** `@/` maps to `src/` (both Vite and tsconfig)
- **CSS:** Tailwind utility classes. All brand colors are HSL custom properties in `src/index.css`. Custom utility classes: `bg-gradient-warm`, `bg-gradient-herbal`, `shadow-card`, `shadow-elevated`, `transition-smooth`
- **Typography:** Display heads use `Fraunces` (serif), body uses `Inter` (sans-serif). Use `font-display` class for headings
- **shadcn/ui:** Do not hand-edit components in `src/components/ui/`. To add new ones, use `npx shadcn@latest add <component>`
- **Routes** are nested under `<AppLayout />` which renders `<TopNav />`, breadcrumb, footer, and `<ChatbotWidget />` globally

## PHP backend (`api/`)
- 17 files: `config.php`, `cors.php`, `response.php`, `db.php`, `graph_service.php`, `health.php`, `search.php`, `graph.php`, `node.php`, `stats.php`, `category-stats.php`, `kg-stats.php`, `counts.php`, `bridge-stats.php`, `provenance-subgraph.php`, `compound-provenance.php`, `version.php`
- Connects to **ArcadeDB** (graph database) via REST API v1: `POST {ARCADE_URL}/api/v1/command/{db}`
- 3-tier connection fallback: cURL → `file_get_contents` → `fsockopen` (with manual chunked-transfer decoding)
- Configure via env vars: `ARCADE_URL`, `ARCADE_DB`, `ARCADE_USER`, `ARCADE_PASS`, `ALLOWED_ORIGINS`
- `graph_service.php` — shared schema mapping (17 vertex types, 22 edge types), type normalization, field resolution (label/description/search fields per type), edge property mapping
- Not part of the Vite/React build — served separately: `php -S localhost:8081 -t .`
- CORS allowed origins default to `*`
- **ArcadeDB schema quirks:** vertex type names are plural/spaced (`Symptoms`, `Ayurvedic Diagnosis`, `Assessment Parameters`, `Doshas`). Label field varies per type:
  `Disease` → `Disease`, `Symptoms` → `Symptoms`, `Ayurvedic_Diagnosis` → label, `Doshas` → `Doshas`,
  `Therapy` → `Therapy`, `Formulation` → `Formulation`,
  `Assessment Parameters` → `Assessment Parameters`
- Description fields: `Disease` → `Description`, `Ayurvedic Diagnosis` → `Description.1`, `Therapy` → `Therapy_Description`, `Formulation` → `Description.4`
- Edge types (Disease domain): `HAS_SYMPTOM`, `HAS_AYURVEDIC_DIAGNOSIS`, `HAS_DOSHA_IMBALANCE`, `TREATED_BY`, `PRE_TREATED_BY`, `POST_TREATED_BY`, `USES_FORMULATION`, `ASSESS`, `RESOLVED_BY`
- Edge types (Compound domain): `UNDERGOES`, `CONTAINS`, `VALIDATED_BY`, `USES_MEDIA`, `CONFIRMS_SAFETY_OF`, `DERIEVED_FROM`, `DETOXIFIES`, `CLASSIFIED_AS`, `USED_FOR_TREATMENT`, `USED_IN_FORMULATION`, `FOLLOWED_BY`, `TREATS`, `PRODUCES`
- Edge properties: `Pubmed ID` → `pubmedId`, `Outcome Measure` (space!) → `outputMeasure`, `Dose` → `dose`, `Duration` → `duration`, `Procedure` → `procedure`, `Result` → `result`
- ArcadeDB SQL uses `.toLowerCase()` for case-insensitive search, `SKIP` not `OFFSET`, no `V`/`E` base types, no `LOWER()` function
# GEMINI.md — ShodhanaKG Explorer Suite

## Stack
- **Framework:** React 18 + Vite 5 + TypeScript (strict: false, noImplicitAny: false)
- **Routing:** react-router-dom v6 (nested routes via `<Outlet>` in `AppLayout`)
- **Data fetching:** @tanstack/react-query v5 (native fetch, no axios)
- **Styling:** Tailwind CSS v3 + `tailwindcss-animate` + `@tailwindcss/typography`
- **UI library:** shadcn/ui (Radix primitives + `class-variance-authority` + `tailwind-merge`)
- **Graph visualization:** raw cytoscape (NOT react-cytoscapejs — manual ref-based mount)
- **Charts:** recharts
- **Forms:** react-hook-form + zod + @hookform/resolvers
- **Dev server:** port 8080, HMR overlay disabled
- **Package manager:** npm (lockfile: `package-lock.json`); `bun.lock`/`bun.lockb` are stale

## Commands
```sh
npm run dev          # Vite dev server on :8080
npm run build        # Production build to dist/
npm run build:dev    # Build with --mode development
npm run lint         # eslint . (flat config, ignores dist/)
npm run preview      # Vite preview of production build
npm test             # vitest run (single run)
npm run test:watch   # vitest (watch mode)
npx playwright test              # full Playwright suite (all browsers)
npx playwright test --grep "Route smoke"  # route smoke tests only
npx playwright test --project chromium --grep "Mobile|overflow"  # mobile overflow in chromium only
```

## Developer workflow (order matters)
1. `npm run lint` — catches TS + react-hooks issues; `@typescript-eslint/no-unused-vars` is **off**, so unused vars won't fail
2. `npm run build` — type-check is baked into Vite (no separate `tsc` step)
3. `npm test` — vitest with jsdom environment
4. `npx playwright test --project chromium --grep "Route smoke|Mobile"` — fast smoke check in chromium only (requires dev server on :8080)

## Critical rule
- **NEVER kill or restart the npm dev server automatically.** After any file change or build, always ask the user to restart the dev server themselves. The dev server belongs to the user's session and should not be managed by the agent.

## Project structure

```
api/                  # PHP backend — ArcadeDB connector (not part of Vite build)
src/
  main.tsx            # Entrypoint — renders <App />
  App.tsx             # Routes + providers (QueryClient, BrowserRouter, TooltipProvider)
  pages/
    Index.tsx         # Landing page
    ModeSelection.tsx # Explorer mode picker
    NotFound.tsx      # 404
    explorers/        # 3 explorer modes × 3 views each
      DiseaseExplorer / DiseaseGraphView / DiseaseTableView
      CompoundExplorer / CompoundGraphView / CompoundTableView
      IntegratedExplorer / IntegratedGraphView / IntegratedTableView
      ExplorerShell.tsx  # Shared explorer layout wrapper
  components/
    ui/               # shadcn/ui components (49 files — do NOT edit directly; they are boilerplate)
    explorer/         # Shared explorer widgets: GraphCanvas, DetailsPanel, Legend, ExportMenu, MultiAutocomplete
    layout/           # AppLayout, TopNav
    chatbot/          # ChatbotWidget
  data/               # Static graph data (diseaseGraph.ts, compoundGraph.ts, integratedGraph.ts)
  lib/
    utils.ts          # cn() helper
    graphExport.ts    # CSV / JSON / PNG export helpers
    types.ts          # API DTOs (ApiNode, ApiEdge, ApiSearchResponse, Domain, etc.)
    api.ts            # HTTP client + React Query hooks with fallback to static data
  hooks/              # use-mobile, use-toast
  test/
    setup.ts          # vitest setup — imports @testing-library/jest-dom, mocks matchMedia
    example.test.ts   # placeholder
```

## Key conventions
- **Path alias:** `@/` maps to `src/` (both Vite and tsconfig)
- **CSS:** Tailwind utility classes. All brand colors are HSL custom properties in `src/index.css`. Custom utility classes: `bg-gradient-warm`, `bg-gradient-herbal`, `shadow-card`, `shadow-elevated`, `transition-smooth`
- **Typography:** Display heads use `Fraunces` (serif), body uses `Inter` (sans-serif). Use `font-display` class for headings
- **shadcn/ui:** Do not hand-edit components in `src/components/ui/`. To add new ones, use `npx shadcn@latest add <component>`
- **Routes** are nested under `<AppLayout />` which renders `<TopNav />`, breadcrumb, footer, and `<ChatbotWidget />` globally

## PHP backend (`api/`)
- 17 files: `config.php`, `cors.php`, `response.php`, `db.php`, `graph_service.php`, `health.php`, `search.php`, `graph.php`, `node.php`, `stats.php`, `category-stats.php`, `kg-stats.php`, `counts.php`, `bridge-stats.php`, `provenance-subgraph.php`, `compound-provenance.php`, `version.php`
- Connects to **ArcadeDB** (graph database) via REST API v1: `POST {ARCADE_URL}/api/v1/command/{db}`
- 3-tier connection fallback: cURL → `file_get_contents` → `fsockopen` (with manual chunked-transfer decoding)
- Configure via env vars: `ARCADE_URL`, `ARCADE_DB`, `ARCADE_USER`, `ARCADE_PASS`, `ALLOWED_ORIGINS`
- `graph_service.php` — shared schema mapping (17 vertex types, 22 edge types), type normalization, field resolution (label/description/search fields per type), edge property mapping
- Not part of the Vite/React build — served separately: `php -S localhost:8081 -t .`
- CORS allowed origins default to `*`
- **ArcadeDB schema quirks:** vertex type names are plural/spaced (`Symptoms`, `Ayurvedic Diagnosis`, `Assessment Parameters`, `Doshas`). Label field varies per type:
  `Disease` → `Disease`, `Symptoms` → `Symptoms`, `Ayurvedic_Diagnosis` → label, `Doshas` → `Doshas`,
  `Therapy` → `Therapy`, `Formulation` → `Formulation`,
  `Assessment Parameters` → `Assessment Parameters`
- Description fields: `Disease` → `Description`, `Ayurvedic Diagnosis` → `Description.1`, `Therapy` → `Therapy_Description`, `Formulation` → `Description.4`
- Edge types (Disease domain): `HAS_SYMPTOM`, `HAS_AYURVEDIC_DIAGNOSIS`, `HAS_DOSHA_IMBALANCE`, `TREATED_BY`, `PRE_TREATED_BY`, `POST_TREATED_BY`, `USES_FORMULATION`, `ASSESS`, `RESOLVED_BY`
- Edge types (Compound domain): `UNDERGOES`, `CONTAINS`, `VALIDATED_BY`, `USES_MEDIA`, `CONFIRMS_SAFETY_OF`, `DERIEVED_FROM`, `DETOXIFIES`, `CLASSIFIED_AS`, `USED_FOR_TREATMENT`, `USED_IN_FORMULATION`, `FOLLOWED_BY`, `TREATS`, `PRODUCES`
- Edge properties: `Pubmed ID` → `pubmedId`, `Outcome Measure` (space!) → `outputMeasure`, `Dose` → `dose`, `Duration` → `duration`, `Procedure` → `procedure`, `Result` → `result`
- ArcadeDB SQL uses `.toLowerCase()` for case-insensitive search, `SKIP` not `OFFSET`, no `V`/`E` base types, no `LOWER()` function
- 8 React Query hooks: `useSearch`, `useGraph`, `useKgStats`, `useStats`, `useCounts`, `useCategoryStats`, `useProvenanceSubgraph`, `useCompoundProvenance`

## Testing quirks
- Vitest config is in `vitest.config.ts` (not inside `vite.config.ts`)
- Tests must be in `src/` and match `*.{test,spec}.{ts,tsx}`
- `setupFiles` polyfills `matchMedia` — tests interacting with Radix UI or responsive components need this
- E2E tests in `tests/` with Playwright
- PHP unit tests in `api/tests/`

## Documentation
- **[Frontend Documentation](./FRONTEND_DOCUMENTATION.md)** — Component tree, state management, GraphCanvas internals, all pages, styling system
- **[Backend Documentation](./BACKEND_DOCUMENTATION.md)** — PHP API architecture, ArcadeDB schema, 3-tier connection, deployment
- **[Chatbot Documentation](./docs/chatbot/README.md)** — Full deterministic chatbot documentation

## AI Chatbot (Ollama + GraphRAG)
- Uses `api/chatbot_response.php` for question answering.
- Uses `api/chatbot_health.php` for health checks.
- Requires `OLLAMA_URL` in env and `VITE_CHAT_ENABLED=true` in `.env.production`.
- Fetches real-time context from ArcadeDB (nodes and PMIDs) and sends it as a system prompt to the LLM.
- Frontend uses standard JSON fetching implemented in `src/lib/chatApi.ts`.
