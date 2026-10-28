# Change Log and Engineering Milestones

This log documents architectural enhancements, module migrations, and system capabilities across the ShodhanaKG platform.

---

## Documentation Architecture

- Consolidated repository documentation across `docs/webservice/`, `docs/chatbot/`, `docs/evaluation/`, and `docs/general/`.
- Replaced fragmented guides with unified architectural references: `FRONTEND_DOCUMENTATION.md`, `BACKEND_DOCUMENTATION.md`, `CHATBOT.md`, `DEPLOYMENT.md`, `QUICK_START.md`, and `EVALUATION_GUIDE.md`.
- Synchronized code links, relative file paths, and environment variable references with repository structure.
- Documented database population and migration procedures across `Create_graph.Cypher`, `Export.Cypher`, and `Migrate_to_ArcadeDB.sql` using Neo4j latest (Desktop or Server) and ArcadeDB.

---

## Frontend Architecture and Accessibility

- Implemented persistent colorblind accessibility mode with high-contrast Okabe-Ito palette and user interface toggle.
- Synchronized high-contrast, soft-tone palette mappings across Disease, Compound, and Integrated explorers.
- Built interactive graph exploration canvas utilizing Cytoscape.js with multiple layout options and node/edge filtering.
- Implemented cross-domain search interface with tabbed category filtering, property search, and pagination.
- Added informative guidance pages and resource links across the application frame.

---

## Backend API and Database Migration

- Refactored API endpoints under `webservice/api/` for backwards runtime compatibility with PHP 5.6.40+ environments.
- Added bundled mbstring polyfill and socket-level HTTP fallback transports for hardened server configurations without curl or mbstring extensions.
- Configured centralized environment loading via `.env` and `webservice/api/config.php` without overriding system environment variables.
- Standardized database traversal endpoints for ArcadeDB graph querying across 17 vertex types and 21 edge types.
- Bounded relationship queries and joins in `bridge-stats.php` to prevent resource exhaustion during cross-domain traversals.

---

## Graph Visualization and Explorer Modes

- Established three dedicated exploration modes: Disease Explorer, Compound Explorer, and Integrated Explorer.
- Implemented category card mappings aligning Disease to Indication and Formulation to Ayurvedic Formulation in compound views.
- Aligned vertex type names to canonical singular definitions across search indices, category stats, and graph views.
- Implemented multi-hop provenance tracing linking raw compounds, Shodhana purification methods, and processing media.

---

## Chatbot and Semantic Retrieval

- Built deterministic graph-grounded question answering over 53 allowlisted ArcadeDB traversal endpoints.
- Implemented evidence correlation in `EvidenceCorrelator.php` matching literature reference identifiers across multi-hop edges to prevent cross-join hallucinations.
- Added structured evidence presentation via `ResultsPresentation.php` with preview windows, count bars, and expandable evidence cards.
- Integrated literature reference verification linking to PubMed, DOI, and AYUSH Research Portal records.
- Implemented conversational phrasing layer via Ollama with schema-constrained JSON contracts and verification checks.

---

## Test and Verification Infrastructure

- Established frontend unit testing suite under Vitest and end-to-end browser automation suites under Playwright.
- Maintained Python unit testing for backend verification and evaluation runners for offline and live HTTP contract checks; expanded benchmark evaluation coverage to all 53 allowlisted endpoints.
- Implemented automated release verification script (`run_release_checks.py`) validating linting, typechecking, tests, and build packaging.
