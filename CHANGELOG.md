# CHANGELOG

## 2026-10-18 — Streamline repository documentation and harden codebase for production
Streamline documentation across repository; remove redundant draft implementation plan, dated audit records, manuscript writing drafts, and obsolete color audit scripts (`DETAILED_IMPLEMENTATION_PLAN.md`, `docs/manuscript/`, `docs/chatbot/audits/*`, `scripts/audit_node_colors*.py`, `scripts/capture_node_colors.cjs`); retain core architectural documentation (`README.md`, `FRONTEND_DOCUMENTATION.md`, `BACKEND_DOCUMENTATION.md`, `DEPLOYMENT.md`, `QUICK_START.md`, `api_endpoints_table.md`, and core `docs/chatbot/` specs); configure Python bytecode ignore rules in `.gitignore`; verify full platform benchmarks, unit tests, and release packaging.
Files/Modules: `README.md`, `DETAILED_IMPLEMENTATION_PLAN.md`, `docs/manuscript/`, `docs/chatbot/`, `docs/chatbot/audits/`, `scripts/`, `.gitignore`, `CHANGELOG.md`.
Impact: Streamlined repository footprint; eliminated stale and redundant documentation; guaranteed 100% test pass rate across frontend, backend, evaluation suites, and release packaging.
Reason: Repository hygiene and production readiness.

## 2026-10-18 — Remove figures directory and update chatbot and repository documentation
Remove figures directory, figure guidelines, and standalone figure generation/verification scripts (`figures/`, `figures_guidelines.md`, `requirements-figures.txt`, `plot_architecture_figures.py`, `plot_evaluation_workflow.py`, `plot_integrated_kg.py`, `plot_integration_heatmap_palette.py`, `plot_kg_structure_analysis.py`, `plot_kg_type_layout.py`, `verify_kg_structure_figures.py`, `scripts/plot_chatbot_evaluation.py`); update chatbot architecture documentation across `docs/chatbot/` to reflect 53 allowlisted ArcadeDB traversal endpoints (with 52 benchmark evaluation endpoints), deterministic graph answer construction without mandatory LLM dependency, and static in-process caching in `GraphRead.php` and `SemanticQuery.php`; update root documentation (`README.md`, `BACKEND_DOCUMENTATION.md`, `docs/manuscript/chatbot-evaluation-20261011/manuscript_results.md`).
Files/Modules: `figures/`, `figures_guidelines.md`, `requirements-figures.txt`, `plot_*.py`, `verify_kg_structure_figures.py`, `scripts/plot_chatbot_evaluation.py`, `docs/chatbot/README.md`, `docs/chatbot/CURRENT_SYSTEM_STATUS.md`, `docs/chatbot/ORCHESTRATION_LAYER.md`, `docs/chatbot/RETRIEVAL_CORE.md`, `docs/chatbot/CHATBOT_QUERY_COVERAGE_REPORT.md`, `docs/manuscript/chatbot-evaluation-20261011/manuscript_results.md`, `README.md`, `BACKEND_DOCUMENTATION.md`, `CHANGELOG.md`.
Impact: Cleaned repository of publication figure assets and plotting dependencies; synchronized all architecture and chatbot documentation with current production codebase realities.
Reason: Repository hygiene and architectural documentation alignment.

## 2026-10-18 — Production codebase hardening, performance optimization, and dead code cleanup
Prune unreferenced mock data arrays across graph data files; consolidate entity type mappings, domain classifications, labels, and colors into a single shared module (`src/data/entityTypes.ts`); deduplicate dictionaries across `ModeSelection.tsx` and `SearchResults.tsx`; optimize backend performance with static caching in `GraphRead.php` and `SemanticQuery.php`; bound relationship queries in `bridge-stats.php`; retire uncalled backend functions and example documentation script; resolve React hook dependency warnings; sync project documentation across README, and frontend/backend docs.
Files/Modules: `src/data/entityTypes.ts`, `src/data/*.ts`, `src/pages/ModeSelection.tsx`, `src/pages/SearchResults.tsx`, `src/pages/explorers/*.tsx`, `api/bridge-stats.php`, `api/core/GraphRead.php`, `api/core/SemanticQuery.php`, `api/graph.php`, `api/search.php`, `README.md`, `FRONTEND_DOCUMENTATION.md`, `BACKEND_DOCUMENTATION.md`, `CHANGELOG.md`.
Impact: Reduced frontend client bundle sizes; eliminated redundant schema/file reads per question; resolved lint warnings; synchronized and cleaned architecture documentation.
Reason: Production readiness and cleanup of dead/redundant code.

## 2026-08-14 — Update LLM timeouts, database defaults, and comprehensive User Guide
Update local ArcadeDB connection details for `IntegratedKG`; Increase local LLM timeout constraints to 120s for CPU generation stability; Integrate interactive 817-line User Guide (`HowToUse.tsx`) with hotspot features; Generate updated help screenshots via Playwright headless browser automation.
Files/Modules: `api/config.php`, `api/core/LLMClient.php`, `api/chatbot_response.php`, `src/pages/HowToUse.tsx`, `public/images/help/*.png`
Impact: Prevented local RAG generation timeouts; enabled chatbot availability; deployed interactive user guide with accurate up-to-date screenshots.
Reason: Improve local chatbot stability and align help documentation with the new premium interface.

## 2026-05-16 — Comprehensive frontend and backend documentation


Create full FRONTEND_DOCUMENTATION.md (42 files, component tree, state management, graph viz internals, styling, testing) and BACKEND_DOCUMENTATION.md (11 API endpoints, ArcadeDB schema, 3-tier connection, deployment). Update all existing docs (README, CHANGELOG, QUICK_START, GEMINI, AGENTS, DEPLOYMENT, DETAILED_IMPLEMENTATION_PLAN) for accuracy and cross-reference.
Files: `FRONTEND_DOCUMENTATION.md`, `BACKEND_DOCUMENTATION.md`, `README.md`, `CHANGELOG.md`, `QUICK_START.md`, `GEMINI.md`, `AGENTS.md`, `DEPLOYMENT.md`, `DETAILED_IMPLEMENTATION_PLAN.md`
Impact: Discoverable reference for all developers; README now links to new docs; GEMINI/AGENTS updated with endpoint listing and test info; CHANGELOG entry appended.
Reason: Project lacked structured documentation discoverable from the repo root.

Append-only. Most recent entries first.

Use short, telegraphic style:
- verb + object
- no articles
- concise lines

One entry per task.

## 2026-05-15 — PHP 5.6 Compatibility and Production Stabilization

System-wide refactor of all API endpoints to support legacy PHP 5.6.40 environments and hardened server configurations.
Files/Modules: `api/*.php`, `api/db.php`, `api/graph_service.php`
Impact: 500 errors resolved in production; API now works without `php-curl` or `mbstring` extensions; Integrated Explorer mode fully functional; SQL queries protected with `coalesce()` for stability; Field escaping fixed for `Description.4`.
Reason: Production server environment uses legacy PHP 5.6 and has restricted network/extension access.

## 2026-05-15 — Update database name to 'shodhana' and configure subpath deployment
 
Change database name from 'shodhanakg' to 'shodhana' and configure frontend/backend for subpath `/raghava/shodhanakg`
Files/Modules: `api/config.php`, `api/health.php`, `src/App.tsx`, `src/lib/api.ts`, `src/pages/Team.tsx`, `src/pages/Contact.tsx`, `vite.config.ts`, `public/.htaccess`, `.env.production.example`, `apache.conf.example`, `package.json`, `DEPLOYMENT.md`
Impact: Database name aligned; application served from subpath with zero-config API routing; image paths fixed for subpath deployment; `.htaccess` automatically bundled in `dist/`; Lovable artifacts removed.
Reason: Final deployment environment requirements provided by user
## 2026-05-15 — Update database name for deployment

Change central database name from 'IntegratedKG' to 'shodhanakg' across API config and docs
Files/Modules: `api/config.php`, `.env.production.example`, `README.md`, `DEPLOYMENT.md`
Impact: Unified database naming ready for production; updated setup instructions
Reason: Align with brand identity for final deployment

## 2026-05-14 — Implement Accessibility Colorblind Mode

Add persistent global colorblind mode with high-contrast Okabe-Ito palette and UI toggle
Files/Modules: `src/hooks/useColorblind.tsx`, `src/App.tsx`, `src/components/layout/TopNav.tsx`, `src/pages/explorers/*.tsx`, `src/components/explorer/GraphCanvas.tsx`, `src/index.css`
Impact: Accessibility-compliant high-contrast theme across all explorers; reactive graph visualization; persistent user settings; improved inclusivity
Reason: Enhance platform accessibility and ensure all users can differentiate complex graph nodes

## 2026-05-14 — Update Disease Graph node colors

Update color scheme for Disease Graph nodes to follow specific brand hex codes
Files/Modules: `src/index.css`, `src/data/diseaseGraph.ts`, `src/pages/explorers/DiseaseGraphView.tsx`
Impact: Disease, Symptom, Therapy, Formulation, Dosha, and Assessment Parameter nodes use a high-contrast yet soft color palette (#bd3f36, #f5bc47, #3b9b7e, #d18ab9, #914e2d, #3880bd); Compound and Integrated explorers synchronized with harmonious high-contrast soft tones across all 15+ categories; improved cross-domain visual distinction; filter chips and legends updated; CSS variables refined in :root
Reason: Achieve a unified, premium, and highly-differentiable visual design across the entire ShodhanaKG suite

## 2026-05-14 — Add FAQ and How to Use pages, update Footer

Create informational pages and enhance global footer with About section and navigation links
Files/Modules: `src/pages/FAQ.tsx`, `src/pages/HowToUse.tsx`, `src/App.tsx`, `src/components/layout/AppLayout.tsx`
Impact: New `/faq` and `/how-to-use` routes added; FAQ page uses interactive accordion; How to Use page provides step-by-step guidance; Footer now includes "About ShodhanaKG" description and resource links
Reason: Improve user onboarding and provide platform documentation as requested

## 2026-05-13 — Update Compound Explorer category cards and verify database stats

Rename category cards in Compound Explorer and verify database connection statistics
Files/Modules: `src/pages/explorers/CompoundExplorer.tsx`, `api/graph_service.php`, `api/category-stats.php`
Impact: Category cards renamed Disease→Indication (170 entries) and Formulation→Ayurvedic Formulation (54 entries); Verified database has 475 TREATS edges (Purified_Compound→Indication) and 73 USED_IN_FORMULATION edges (Purified_Compound→Ayurvedic_Formulation); Table views show purified compound connection counts for both categories
Reason: UI consistency with updated schema and user request to verify connection stats

## 2026-05-13 — Fix Formulations vertex type name (CRITICAL)

Fix Formulations→Formulation vertex type name mismatch causing search failures and missing category
Files/Modules: `api/graph_service.php`, `api/category-stats.php`
Impact: Changed vertex type from 'Formulations' (plural) to 'Formulation' (singular) across all configs; Formulation now appears in search results (661 formulations); Formulation category card displays correctly with count; Formulation table view shows therapy relationship statistics
Reason: Database schema uses 'Formulation' (singular) but code referenced 'Formulations' (plural) causing transaction commit errors

## 2026-05-13 — Fix search functionality (CRITICAL)

Fix search bar returning no results across all explorers
Files/Modules: `api/search.php`
Impact: Search now works in Disease, Compound, and Integrated explorers; returns case-insensitive partial matches (e.g., "arth" matches "Arthritis"); handles database errors gracefully by skipping problematic vertex types instead of failing entire search
Reason: Formulations vertex type was causing "Error on transaction commit (500)" which broke entire search; wrapped each vertex type query in individual try-catch to gracefully handle errors; reverted to toLowerCase() ArcadeDB syntax which works correctly

## 2026-05-13 — Fix search, stats, and graph filtering issues

Fix 7 reported issues: search not working, formulation stats showing 0, missing process stats, incorrect category names, integrated view showing 0 stats, wrong graph hop behavior, and 0-relationship compounds in table
Files/Modules: `api/search.php`, `api/category-stats.php`, `api/bridge-stats.php`, `src/pages/explorers/CompoundExplorer.tsx`, `src/pages/explorers/IntegratedGraphView.tsx`
Impact: Search now uses ILIKE for case-insensitive matching; Formulation stats correctly count USES_FORMULATION edges; Shodhana Method shows process counts via FOLLOWED_BY edge; Compound explorer renamed Disease→Indication and Formulation→Ayurvedic Formulation; Integrated table only shows purified compounds with bridge relationships; Integrated graph 1-hop filters to only bridge edges (USED_FOR_TREATMENT, USED_IN_FORMULATION), 2-hop shows all neighbors
Reason: ArcadeDB SQL syntax error (toLowerCase() vs ILIKE), incorrect edge direction configs, missing edge type mappings, UI label consistency with updated schema

## 2026-05-12 — Dedicated search results page (Task 6)

Create dedicated search results page at `/search` with tabbed interface
Files/Modules: `src/pages/SearchResults.tsx`, `src/App.tsx`, `src/pages/explorers/CompoundExplorer.tsx`, `src/pages/explorers/DiseaseExplorer.tsx`, `src/pages/explorers/IntegratedExplorer.tsx`
Impact: New `/search` route displays cross-domain search results with tabbed filtering (All | Disease | Compound | Integrated); results grouped by entity type with color-coded badges; direct links to graph views; URL-synced query and tab parameters; all explorer search boxes now navigate to `/search` instead of showing inline results
Reason: Move search results from inline on homepage to dedicated page for better UX

## 2026-05-12 — Graph view UI consistency (Task 8)

Make Compound and Integrated graph views match Disease view layout
Files/Modules: `src/pages/explorers/CompoundGraphView.tsx`, `src/pages/explorers/IntegratedGraphView.tsx`
Impact: Removed Legend component from graph area in both Compound and Integrated views; removed edge visibility section from Compound left sidebar; all three graph views now have consistent three-column layout with node visibility checklist, selected filters display, and properties panel
Reason: User requested consistent UI across all three graph views

## 2026-05-12 — Stats verification (Task 7)

Verify all table stats are correct across Disease and Compound explorers
Files/Modules: Verification only — no code changes
Impact: Confirmed all 7 disease tables and 8 compound tables display correct relationship counts; all PHP endpoints return expected data structures; all unit tests pass (8/8)
Reason: Ensure data integrity before proceeding to UI consistency work

## 2026-05-12 — Compound Table View with category-specific tables

Implement category-specific table views for Compound Explorer matching Disease Table View pattern
Files/Modules: `api/category-stats.php`, `src/lib/types.ts`, `src/pages/explorers/CompoundTableView.tsx`
Impact: Compound Table View now shows 8 category-specific tables (Compound, ShodhanaMethod, ToxicSubstance, PurifiedCompound, ShodhanaType, Media, ConfirmationStudy, Process) with alphabetical column ordering, outgoing relationship counts, sortable columns, pagination (25/page), and row navigation to graph view
Reason: User requested category-specific tables showing relevant entity columns per category like Disease Table View

## 2026-05-11 — Fix ASSESS edge direction for disease stats and graph view

Fix Assessment Parameters showing 0 in disease stats tables and hidden in disease graph
Files/Modules: `api/category-stats.php`, `api/stats.php`, `api/graph_service.php`
Impact: disease table Assessment Parameters column now shows correct backend counts; disease graph ASSESS edges are normalized to ASSESSED_BY type
Reason: ArcadeDB ASSESS edge has @out=AssessmentParameter, @in=Disease, but PHP code assumed @out=Disease. Also missing edge type normalization.

## 2026-05-11 — End-to-end Playwright regression suite

Add comprehensive Playwright smoke tests for all explorer routes, search flows, mobile viewports, and table interactions
Files/Modules: `tests/shodhanakg.spec.ts`
Impact: 43 new test cases for route smoke, search flows (Eczema/Croton/Abhyanga), mobile overflow (iPhone 14), table "Open graph" link interaction
Reason: make future UX/API drift visible with automated regression coverage

## 2026-05-11 — Table UX and data integrity

Add explicit "Open graph" links to all table views
Files/Modules: `src/pages/explorers/DiseaseTableView.tsx`, `src/pages/explorers/CompoundTableView.tsx`, `src/pages/explorers/IntegratedTableView.tsx`
Impact: each table row has a visible "Open graph" `<Link>` button (native link, supports new tab); row click navigation preserved for convenience; `e.stopPropagation()` prevents double-fire
Reason: make graph navigation discoverable and support power-user new-tab workflows

## 2026-05-11 — Graph page usability improvements

Improve dense graph page layout across all three explorer modes
Files/Modules: `src/pages/explorers/IntegratedGraphView.tsx`, `src/pages/explorers/DiseaseGraphView.tsx`, `src/pages/explorers/CompoundGraphView.tsx`, `src/components/layout/AppLayout.tsx`
Impact: IntegratedGraphView now lazy-loads GraphCanvas with Suspense; truncation indicator (AlertTriangle) shown when backend meta.truncated true; "Data: IntegratedKG" badge on all graph stat bars; chatbot hidden on graph routes; mobile graph-first ordering
Reason: match disease graph UX baseline, reduce distraction on graph routes, surface truncation

## 2026-05-11 — Consistent search UX across all explorers

Unify search behavior across Disease, Compound, Integrated home pages
Files/Modules: `src/pages/explorers/DiseaseExplorer.tsx`, `src/pages/explorers/CompoundExplorer.tsx`, `src/pages/explorers/IntegratedExplorer.tsx`
Impact: search no longer auto-navigates on single match; all results show cards with explicit "Open graph" buttons (native `<Link>` for new-tab); loading spinner on Search button; API-error retry state; actionable empty state
Reason: make search behavior consistent and discoverable per decision log

## 2026-05-11 — Integrated home and table backend migration

Migrate integrated home search/table to backend IntegratedKG data
Files/Modules: `src/pages/explorers/IntegratedExplorer.tsx`, `src/pages/explorers/IntegratedTableView.tsx`, `src/lib/api.ts`, `api/bridge-stats.php`
Impact: integrated home uses backend search, preset cards use backend starter IDs, bridge table uses live backend data
Reason: integrated home/table were still static-only with stale starter data

## 2026-05-11 — Compound explorer backend migration

Migrate compound home, graph, and table to live backend IntegratedKG data
Files/Modules: `src/pages/explorers/CompoundExplorer.tsx`, `src/pages/explorers/CompoundGraphView.tsx`, `src/pages/explorers/CompoundTableView.tsx`, `api/stats.php`
Impact: compound home shows real backend counts, compound graph uses backend graph traversal, compound table shows backend aggregate stats
Reason: compound surface was still static-only while backend already had IntegratedKG compound domain support

## 2026-05-11 — Route-state normalization for graph URLs

Normalize graph URL state across all explorer modes
Files/Modules: `src/lib/api.ts`, `src/pages/explorers/DiseaseGraphView.tsx`, `src/pages/explorers/CompoundGraphView.tsx`, `tests/shodhanakg.spec.ts`
Impact: direct graph URL reliability, chip+badge visibility on load
Reason: direct links and bookmarks showed empty state; graph pages now parse `filter`, `seeds`, `node`, `compound`, `depth` params; chip fallback to raw ID when static data unavailable

## 2026-05-11 — Integrated graph backend wiring

Wire integrated graph page to backend IntegratedKG APIs
Files/Modules: `src/pages/explorers/IntegratedGraphView.tsx`, `src/data/integratedGraph.ts`, `src/lib/types.ts`
Impact: Integrated graph page behavior
Reason: remove static k-hop data dependency for integrated graph rendering and use live backend graph/search responses

## 2026-05-11 — IntegratedKG backend migration

Migrate backend API source to IntegratedKG with domain-aware schema mapping
Files/Modules: `api/config.php`, `api/graph_service.php`, `api/search.php`, `api/counts.php`, `api/graph.php`, `api/node.php`, `api/stats.php`, `api/category-stats.php`
Impact: API behavior for disease/compound/integrated endpoints
Reason: Use IntegratedKG as single source of truth while preserving frontend-compatible node/edge names and response shape

## 2026-05-10 — Graph performance

Lazy-load graph pages and reduce disease graph traversal/render cost
Files/Modules: `src/App.tsx`, `src/pages/explorers/DiseaseGraphView.tsx`, `src/components/explorer/GraphCanvas.tsx`
Impact: Graph page loading behavior
Reason: Split Cytoscape from main bundle and replace depth-2 oversized first load with bounded depth-1 rendering

## 2026-05-10 — Graph dropdown and depth bounds

Fix offscreen filter dropdown placement and enforce graph display limits
Files/Modules: `src/components/explorer/MultiAutocomplete.tsx`, `src/pages/explorers/DiseaseGraphView.tsx`
Impact: Disease graph UI behavior
Reason: Keep filter results clickable near viewport bottom and stabilize depth visualization when backend returns oversized graph payloads

## 2026-05-10 — Graph interaction fixes

Fix graph filter dropdown and therapy formulation expansion
Files/Modules: `src/components/explorer/GraphCanvas.tsx`, `src/components/explorer/MultiAutocomplete.tsx`
Impact: Disease graph UI behavior
Reason: Keep formulation depth hidden until therapy node expansion and prevent detached/stale filter search results
