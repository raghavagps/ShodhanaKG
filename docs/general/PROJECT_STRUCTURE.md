# Project Structure

## 1. Directory Structure

- webservice/ - Application web services containing frontend user interface and backend API layer.
  - webservice/frontend/ - React, TypeScript, and Vite single-page application and presentation layer.
    - webservice/frontend/public/ - Static assets, typography licenses, and animation HTML pages.
      - webservice/frontend/public/fonts/ - Embedded font files and open-source font licenses.
      - webservice/frontend/public/images/ - Static illustrations, team photographs, and interface diagrams.
        - webservice/frontend/public/images/help/ - User interface guide and workflow help images.
    - webservice/frontend/src/ - Application source code.
      - webservice/frontend/src/components/ - User interface React components.
        - webservice/frontend/src/components/chatbot/ - Chatbot dialog, message rendering, suggestion cards, and evidence viewers.
        - webservice/frontend/src/components/explorer/ - Cytoscape graph canvas, property panels, filters, and provenance cards.
        - webservice/frontend/src/components/layout/ - Application frame, navigation bar, language selector, and footer.
        - webservice/frontend/src/components/ui/ - Radix UI and Shadcn design system primitive components.
      - webservice/frontend/src/context/ - React context providers including accessibility and colorblind palette modes.
      - webservice/frontend/src/data/ - Static node descriptions and domain terminology definitions.
      - webservice/frontend/src/hooks/ - Custom React hooks for progressive data loading, entity resolution, and viewport sizing.
      - webservice/frontend/src/lib/ - Client utility functions, Cytoscape graph styling configurations, and HTTP API wrappers.
      - webservice/frontend/src/pages/ - Route-level page components for landing, search, guide, team, and contact views.
        - webservice/frontend/src/pages/explorers/ - Dedicated explorer views for Disease, Compound, and Integrated knowledge graphs.
      - webservice/frontend/src/test/ - Frontend unit and component testing utilities and mock environments.
    - webservice/frontend/tests/ - Playwright browser automation test specifications and end-to-end suites.
  - webservice/api/ - PHP backend API endpoints, ArcadeDB drivers, and traversal routines.
    - webservice/api/core/ - Backend modular classes for routing, query execution, evidence correlation, and LLM transport.
    - webservice/api/tests/ - Backend test suites, benchmark harnesses, regression runners, and evaluation scripts.
      - webservice/api/tests/contracts/ - JSON schema contracts defining expected API response envelopes.
      - webservice/api/tests/fixtures/ - Frozen ground truth cases, evaluation snapshots, and baseline validation datasets.
- arcadedb/ - Root directory for ArcadeDB schema definitions, data dumps, and import scripts (TBD: folder pending creation/migration; see RESTRUCTURE.md).
- evaluation/ - Root directory for Python benchmark scripts and standalone evaluation fixtures (TBD: folder pending creation/migration; see RESTRUCTURE.md).
- docs/ - Project documentation, technical specifications, guides, and audit reports.
  - docs/webservice/ - Technical documentation for frontend architecture, PHP API, chatbot, deployment, and server setup.
  - docs/arcadedb/ - Technical documentation for ArcadeDB schema, queries, data structure, and imports (TBD: currently empty, pending files).
  - docs/evaluation/ - Benchmark results, query coverage audits, and evaluation framework guides.
  - docs/general/ - Project-wide general documentation, changelogs, and structural specifications.
  - docs/chatbot/ - Historical audit datasets and dual-scope validation matrices left in place pending migration confirmation.
    - docs/chatbot/audits/ - Archived audit runs for schema validation and identifier rules.
      - docs/chatbot/audits/identifier_rules_2026_09_24_v1/ - Machine-readable reference audit datasets referenced by test suites.

## 2. Component Specifications

### Frontend
- Entry Point: webservice/frontend/src/main.tsx (loaded by webservice/frontend/index.html)
- Config File: webservice/frontend/vite.config.ts, webservice/frontend/tsconfig.json, webservice/frontend/tailwind.config.ts, webservice/frontend/components.json
- Environment Variable Names: VITE_API_BASE, VITE_CHAT_ENABLED, BASE_URL, MODE
- Dependency File: webservice/frontend/package.json, webservice/frontend/package-lock.json
- Versions: Node.js engine requirement ^22.12.0 || ^24.0.0 || >=26.0.0; React 18.3.1; Vite 7.3.7; Vitest 5.0.3; TypeScript 5.8.3; Tailwind CSS 4.3.3; Cytoscape 3.33.2; Playwright 1.60.0
- Run Command:
  - Development server: npm --prefix webservice/frontend run dev
  - Production build: npm --prefix webservice/frontend run build
  - Typecheck: npm --prefix webservice/frontend run typecheck
  - Unit tests: npm --prefix webservice/frontend test
  - End-to-end tests: npm --prefix webservice/frontend run test:e2e

### API (PHP Backend)
- Entry Point: webservice/api/chatbot_response.php, webservice/api/search.php, webservice/api/graph.php, webservice/api/node.php, webservice/api/health.php (see docs/webservice/api_endpoints_table.md for complete 70-endpoint inventory)
- Config File: webservice/api/config.php
- Environment Variable Names: ARCADE_URL, ARCADE_DB, ARCADE_USER, ARCADE_PASS, OLLAMA_URL, OLLAMA_MODEL, OLLAMA_KEEP_ALIVE, KG_NLU_MODEL_ENABLED, CHATBOT_ANSWER_MODE, ALLOWED_ORIGINS, API_STORAGE_DIR, API_CACHE_DIR, KG_READ_CACHE_DIR, API_VERSION, SHODHANA_TEST_API_BASE, SHODHANA_TEST_PYTHON, CHATBOT_EVAL_URL, OLLAMA_BASE_URL
- Dependency File: Self-contained PHP scripts; bundled polyfill in webservice/api/vendor/polyfill-mbstring (no composer.json)
- Versions: Target legacy compatibility runtime PHP 5.6.40+; modern testing and evaluation runtime PHP 8.1+ / PHP 8.2+
- Run Command:
  - Local development server: php -S localhost:8081 -t webservice
  - Production server: Apache HTTP Server with mod_php or PHP-FPM (see apache.conf.example and docs/webservice/DEPLOYMENT.md)

### ArcadeDB
- Entry Point: Server binary in external ArcadeDB installation (bin/server.sh or bin/server.bat; port 2480 for HTTP REST, port 2424 for binary protocol)
- Config File: config/arcadedb-server-config.json (server installation); webservice/api/config.php (application client parameters)
- Environment Variable Names: ARCADE_URL, ARCADE_DB, ARCADE_USER, ARCADE_PASS
- Dependency File: Java JRE (Java 11+ or Java 17+); ArcadeDB distribution package (TBD)
- Versions: ArcadeDB server version: TBD (database server installed externally; schema and data snapshots in webservice/api/tests/fixtures/)
- Run Command:
  - Start server: bin/server.sh (Linux/macOS) or bin/server.bat (Windows)
  - Connectivity check: php webservice/api/test_db.php or curl http://localhost:2480/api/v1/ready

### Evaluation
- Entry Point: webservice/api/tests/run_release_checks.py, webservice/api/tests/run_current_benchmark.py, webservice/api/tests/run_evaluation_cases.php, webservice/api/tests/evaluate_fidelity.py, webservice/api/tests/evaluate_results_presentation.py, webservice/api/tests/evaluate_conversations.py
- Config File: Command-line arguments (--fixture, --output, --url, --http); test fixtures in webservice/api/tests/fixtures/
- Environment Variable Names: SHODHANA_TEST_API_BASE, SHODHANA_TEST_PYTHON, CHATBOT_EVAL_URL, ARCADE_URL, ARCADE_DB, ARCADE_USER, ARCADE_PASS, OLLAMA_URL
- Dependency File: Python standard library; optional webservice/api/tests/requirements-ragas.txt (pandas>=2.0.0)
- Versions: Python 3.8+ (Python 3.10+ / 3.12 verified in environment); modern PHP 8.x for evaluation scripts
- Run Command:
  - Python release checks: python -B webservice/api/tests/run_release_checks.py
  - Python unit tests: python -m unittest discover -s webservice/api/tests -p "test_*.py"
  - Offline evaluation suite: php webservice/api/tests/run_evaluation_cases.php
  - Live HTTP evaluation suite: php webservice/api/tests/run_evaluation_cases.php --http

### Chatbot / Ollama
- Entry Point: Ollama service daemon (port 11434); invoked via webservice/api/core/LLMClient.php from webservice/api/chatbot_response.php
- Config File: webservice/api/config.php, webservice/api/core/ModelConfig.php
- Environment Variable Names: OLLAMA_URL, OLLAMA_MODEL, OLLAMA_KEEP_ALIVE, KG_NLU_MODEL_ENABLED, CHATBOT_ANSWER_MODE, OLLAMA_BASE_URL
- Dependency File: Ollama binary and downloaded model weights
- Versions: Ollama 0.3+ / 0.4+; model llama3.2 (3B parameter model)
- Run Command:
  - Start Ollama server: ollama serve
  - Pull default model: ollama pull llama3.2
  - Chatbot health check: curl http://localhost:8081/api/chatbot_health.php

## 3. Steps to Reproduce from Scratch

1. Start ArcadeDB Server:
   bin/server.sh
   (or bin/server.bat on Windows)

2. Verify Database Connection:
   php webservice/api/test_db.php

3. Start Backend Web Service:
   php -S localhost:8081 -t webservice

4. Optional - Start Ollama Model Service:
   ollama serve
   ollama pull llama3.2
   curl http://localhost:8081/api/chatbot_health.php

5. Install Frontend Dependencies and Start Development Server:
   npm --prefix webservice/frontend install
   npm --prefix webservice/frontend run dev

6. Build Production Frontend Bundle:
   npm --prefix webservice/frontend run build

7. Execute Automated Verifications:
   npm --prefix webservice/frontend test
   python -m unittest discover -s webservice/api/tests -p "test_*.py"
   php webservice/api/tests/run_evaluation_cases.php
   python -B webservice/api/tests/run_release_checks.py

## 4. Scripts Producing Paper Tables or Figures

- webservice/api/tests/calculate_latency.php:
  - Input: results/latency_raw.csv
  - Output: Latency distribution metrics (p50, p90, p95, p99 for HTTP JSON, SSE, and turn-by-turn interactions).
- webservice/api/tests/run_current_benchmark.py:
  - Output: Consolidated retrieval, completeness, negative constraint, and response-time metrics across 52 evaluated traversal endpoints.
- webservice/api/tests/build_reference_catalog.py:
  - Input: docs/chatbot/audits/identifier_rules_2026_09_24_v1/audit.json
  - Output: webservice/api/core/reference_catalog_v1.json (reconciled literature reference catalog covering 410 distinct study references across PubMed, DOI, and AYUSH).
- webservice/api/tests/write_system_audit_report.py:
  - Output: docs/chatbot/audits/system_consistency_2026_09_24_v1/REPORT.md, file_inventory.json, and metrics.json (system consistency audit covering 38 raw graph tables and 10,123 stored edges).
- webservice/api/tests/write_explorer_context_report.py:
  - Output: docs/chatbot/audits/explorer_context_2026_09_25_v1/REPORT.md and manifest.json (schema entity/relationship tables, observed properties, and constraints).
- webservice/api/tests/write_ui_result_contract_report.py:
  - Output: docs/chatbot/audits/ui_result_contract_2026_09_25_v1/REPORT.md and files.json (UI result presentation contracts).
- webservice/api/tests/build_traversal_inventory.php:
  - Output: Markdown table inventory of allowlisted ArcadeDB traversal endpoints and callable mappings.
- Paper Figures and Manuscript Tables:
  - TBD: Standalone figure scripts (plot_architecture_figures.py, plot_evaluation_workflow.py, plot_integrated_kg.py, plot_integration_heatmap_palette.py, plot_kg_structure_analysis.py, plot_kg_type_layout.py, verify_kg_structure_figures.py, scripts/plot_chatbot_evaluation.py) and manuscript files were removed in prior repository maintenance commits (recorded in docs/general/CHANGELOG.md). Direct mapping of existing evaluation outputs to published manuscript table numbers or figures is TBD.

## 5. Needs Confirmation

- Root arcadedb/ Directory:
  - TBD: Identification and migration of schema files, data exports, and database import scripts into root arcadedb/ (noted in RESTRUCTURE.md line 17).
  - TBD: Documentation files to populate docs/arcadedb/ (currently empty).
  - TBD: ArcadeDB server version, distribution package, and initialization script.
- Root evaluation/ Directory:
  - TBD: Migration of standalone Python benchmark scripts and fixtures from webservice/api/tests/ to root evaluation/ (noted in RESTRUCTURE.md line 18).
- Audit Fixtures and Dual-Scope Documents:
  - TBD: Determination of destination for docs/chatbot/VALIDATION_MATRIX.md (fits both docs/evaluation/ and docs/arcadedb/).
  - TBD: Determination of destination for docs/chatbot/audits/identifier_rules_2026_09_24_v1/ (machine-readable fixtures referenced by 9 test scripts).
- Paper Tables and Figures:
  - TBD: Mapping between evaluation scripts and specific manuscript table and figure numbers.
- Untracked Documentation Files:
  - TBD: Whether docs/AGENTS.md and docs/GEMINI.md should remain ignored by .gitignore or be committed under docs/general/.
- Repository Restructure Tracking File:
  - TBD: Eventual removal or relocation of RESTRUCTURE.md in repository root.
