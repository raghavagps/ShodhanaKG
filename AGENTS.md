# AGENTS.md — ShodhanaKG Explorer Suite

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
2. `npm run build` — runs `npm run typecheck` before Vite bundling
3. `npm test` — vitest with jsdom environment
4. `npx playwright test --project chromium --grep "Route smoke|Mobile"` — fast smoke check in chromium only (requires dev server on :8080)

## Critical rule
- **NEVER kill or restart the npm dev server automatically.** After any file change or build, always ask the user to restart the dev server themselves. The dev server belongs to the user's session and should not be managed by the agent.
- **Never implement anything on production server.** Production server can run commands or make changes which are essential for the deployment to run, any code change, refactoring, logical implementation, architectural change, data analysis, data inference and visualization needs to run on local system only except when testing scalability, correctness, debugging and testing the publically released server.

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
- Backend entry points live in `api/`; shared chatbot/retrieval services live in `api/core/`. Use the file inventory rather than a fixed historical file count.
- Connects to **ArcadeDB** (graph database) via REST API v1: `POST {ARCADE_URL}/api/v1/command/{db}`
- 3-tier connection fallback: cURL → `file_get_contents` → `fsockopen` (with manual chunked-transfer decoding)
- Configure via env vars: `ARCADE_URL`, `ARCADE_DB`, `ARCADE_USER`, `ARCADE_PASS`, `ALLOWED_ORIGINS`
- `graph_service.php` — shared schema mapping (17 supported vertex categories, 21 populated edge types; legacy lists also retain RESOLVED_BY), type normalization, field resolution (label/description/search fields per type), edge property mapping
- Not part of the Vite/React build — served separately: `php -S 127.0.0.1:8081 -t .`
- CORS allowed origins default to `*`
- Current database vertex types use canonical names (`Symptom`, `Ayurvedic_Diagnosis`, `Dosha`, `Assessment_Parameter`, etc.). Legacy plural/spaced aliases remain normalization inputs; query the live schema rather than assuming aliases are stored types.
- Node labels/descriptions and edge display properties are resolved through shared metadata and actual stored fields; preserve raw fields and exact edge identity.
- Edge types (Disease domain): `HAS_SYMPTOM`, `HAS_AYURVEDIC_DIAGNOSIS`, `HAS_DOSHA_IMBALANCE`, `TREATED_BY`, `PRE_TREATED_BY`, `POST_TREATED_BY`, `USES_FORMULATION`, `ASSESS`, `RESOLVED_BY`
- Edge types (Compound domain): `UNDERGOES`, `CONTAINS`, `VALIDATED_BY`, `USES_MEDIA`, `CONFIRMS_SAFETY_OF`, `DERIEVED_FROM`, `DETOXIFIES`, `CLASSIFIED_AS`, `USED_FOR_TREATMENT`, `USED_IN_FORMULATION`, `FOLLOWED_BY`, `TREATS`, `PRODUCES`
- Edge properties: `Pubmed ID` → `pubmedId`, `Outcome Measure` (space!) → `outputMeasure`, `Dose` → `dose`, `Duration` → `duration`, `Procedure` → `procedure`, `Result` → `result`
- ArcadeDB SQL uses `.toLowerCase()` for case-insensitive search, `SKIP` not `OFFSET`, no `V`/`E` base types, no `LOWER()` function
- 7 React Query hooks: `useSearch`, `useGraph`, `useKgStats`, `useCounts`, `useCategoryStats`, `useProvenanceSubgraph`, `useCompoundProvenance`

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
- Set `VITE_CHAT_ENABLED=true` for the frontend. Optional language assistance uses `OLLAMA_URL` (`OLLAMA_BASE_URL` remains a compatibility alias); deterministic KG answers do not require a model.
- Resolves entities and structured conversation context against ArcadeDB, retrieves validated facts and edge-bound namespace-aware references, and enumerates results deterministically. Generic descriptions use only stored node properties.
- Frontend supports JSON and SSE in `src/lib/chatApi.ts`, with deadlines, cancellation and matching terminal metadata.
- Current scope, limitations and complete release command: `docs/chatbot/CURRENT_SYSTEM_STATUS.md`.
