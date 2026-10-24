# ShodhanaKG: An Ayurvedic Knowledge Graph for Disease and Compound Shodhana

## Overview

ShodhanaKG stores the curated knowledge in an ArcadeDB graph and exposes it through a web portal and a graph-grounded chatbot. The graph has 2,534 nodes and 10,123 relationships, extracted from 410 articles from PubMed and the AYUSH Research Portal, with 17 vertex types and 22 edge types. Browse it at [https://webs.iiitd.edu.in/raghava/shodhanakg/](https://webs.iiitd.edu.in/raghava/shodhanakg/).

## Pipeline

The end-to-end data processing and application pipeline consists of the following stages:

CSVs (2 node files, 17 relation files) -> Neo4j (Cypher schema and load) -> JSON export -> ArcadeDB (create database, import JSON, sanity check, config) -> PHP API -> React frontend.

## Features

- Three exploration modes: Disease Explorer (clinical diagnostics, symptoms, therapies), Compound Explorer (compounds, Shodhana methods, media, processes), and Integrated Explorer (linking purified compounds to treated diseases).
- Interactive graph visualization using Cytoscape.js with multiple layout options (concentric, breadthfirst, cose, circle, grid) and progressive node/edge batching.
- Provenance and literature evidence tracing linking graph paths to PubMed, DOI, and AYUSH Research Portal publications.
- High-performance data tables with connection counts and direct graph view navigation.
- Global statistics dashboard displaying node, edge, and reference counts.
- Search and multi-hop query interfaces across vertices, properties, and constraints.
- Graph-grounded chatbot assistant for natural-language questions with deterministic factual retrieval.
- Interface language toggle between English and Hindi.
- Colorblind accessibility palette mode.

## Technology

### Frontend
- Framework: React 18 (`react` ^18.3.1, `react-dom` ^18.3.1) with Vite 7 (`vite` ^7.3.7)
- Language: TypeScript (`typescript` ^5.8.3)
- Styling: Tailwind CSS 4 (`tailwindcss` ^4.3.3) and Radix UI primitives (`@radix-ui/*`)
- Graph Visualization: Cytoscape.js (`cytoscape` ^3.33.2)
- State and Data Fetching: TanStack React Query (`@tanstack/react-query` ^5.83.0)
- Routing: React Router (`react-router-dom` ^7.18.4)
- Charts: Recharts (`recharts` ^2.15.4)
- Icons: Lucide React (`lucide-react` ^0.462.0)
- Forms and Validation: React Hook Form (`react-hook-form` ^7.61.1) and Zod (`zod` ^3.25.76)
- Unit Testing: Vitest (`vitest` ^5.0.3)
- Browser Testing: Playwright (`@playwright/test` ^1.60.0)
- Node.js Engine Requirement: ^22.12.0 || ^24.0.0 || >=26.0.0

### Backend
- Language: PHP (production compatibility target PHP 5.6.40+; testing and evaluation runtime PHP 8.1+ / PHP 8.2+)
- Database: ArcadeDB 26.4.2 (multi-model graph database)
- Optional LLM: Llama 3.2 3B (default model `llama3.2` via Ollama)
- Testing and Evaluation: Python 3.8+ (standard library with optional `pandas>=2.0.0`)

## Chatbot

The chatbot uses a deterministic graph-retrieval pipeline across 53 allowlisted ArcadeDB traversal endpoints to retrieve factual subgraphs and correlate citations (PubMed ID, DOI, AYUSH ID) across edges without cross-join hallucinations. Answers are constructed deterministically directly from retrieved graph facts. An optional local Ollama instance running Llama 3.2 (3B parameter model, default tag `llama3.2`) can be enabled for conversational phrasing and language assistance without generating ungrounded facts.

## Setup

### Prerequisites

- Node.js (version 22+ recommended; package.json requires ^22.12.0 || ^24.0.0 || >=26.0.0)
- PHP (PHP 5.6.40+ or PHP 8.x)
- ArcadeDB server (v26.4.2) running locally or accessible via network
- Optional: Ollama with Llama 3.2 3B (for conversational chatbot assistance)

### 1. Database Setup

Start the ArcadeDB server. Default connection settings can be configured in webservice/api/config.php or via environment variables:

- ARCADE_URL: http://localhost:2480
- ARCADE_DB: IntegratedKG
- ARCADE_USER: username
- ARCADE_PASS: password

Verify database connectivity:

```bash
php webservice/api/test_db.php
```

### 2. Backend Setup

Start the built-in PHP server pointing to the webservice directory so /api/ endpoints resolve:

```bash
php -S 127.0.0.1:8081 -t webservice
```

### 3. Frontend Setup

Install dependencies and start the Vite development server:

```bash
cd webservice/frontend
npm install
npm run dev
```

The application will be available at http://localhost:8080.

### 4. Optional Chatbot Setup

If using conversational narrative mode via Ollama:

```bash
ollama serve
ollama pull llama3.2
```

Verify chatbot health:

```bash
curl http://localhost:8081/api/chatbot_health.php
```

## Testing

Run frontend unit and component tests (Vitest):

```bash
npm --prefix webservice/frontend test
```

Run frontend typecheck:

```bash
npm --prefix webservice/frontend run typecheck
```

Run frontend linter:

```bash
npm --prefix webservice/frontend run lint
```

Run end-to-end browser tests (Playwright; requires running dev server):

```bash
npx --prefix webservice/frontend playwright test
```

Run route smoke tests:

```bash
npx --prefix webservice/frontend playwright test --grep "Route smoke"
```

Run backend Python unit tests:

```bash
python -m unittest discover -s webservice/api/tests -p "test_*.py"
```

Run backend evaluation cases:

```bash
php webservice/api/tests/run_evaluation_cases.php
```

Run complete release verification suite:

```bash
python -B webservice/api/tests/run_release_checks.py
```

## Reproducibility

### Reproduction Steps

1. Load CSVs in `data/csv/` (`data/csv/nodes/` and `data/csv/relations/`) into Neo4j using the loading scripts in `neo4j/`.
2. Export JSON from Neo4j to `data/graph_export/`.
3. Create the ArcadeDB database and import the JSON from `data/graph_export/`.
4. Run sanity checks to verify graph schema, vertex counts, and edge integrity in ArcadeDB.
5. Set database and server configuration in `webservice/api/config.php` or environment variables (`ARCADE_URL`, `ARCADE_DB`, `ARCADE_USER`, `ARCADE_PASS`).
6. Start the backend PHP API (`php -S 127.0.0.1:8081 -t webservice`).
7. Start the frontend application (`cd webservice/frontend && npm install && npm run dev`).

### Facts Confirmed in Code

The following facts are confirmed in repository code, tests, and frozen fixtures:

- The graph database contains 2,534 nodes across 17 vertex types and 10,123 relationships across 22 edge types, verified in webservice/api/tests/fixtures/system_consistency_baseline_v1/snapshot.json.
- The reference catalog (webservice/api/core/reference_catalog_v1.json) indexes 410 distinct curated literature publications across PubMed, DOI, and AYUSH Research Portal identifiers.
- Benchmark ground truth fixtures (factual_evaluation_cases_v2.json, legacy_factual_evaluation_cases_v2.json, expanded_factual_cases_v4.json) record exact SHA-256 hashes of database snapshots (factual_graph_snapshot.json).
- Traversal query coverage asserts 53 allowlisted ArcadeDB traversal endpoints (52 evaluated in chatbot_evaluation_cases.json).
- The frontend test suite contains 35 test files and 119 unit and component tests passing under Vitest.
- The backend test suite contains 84 unit tests passing under Python unittest.
- Production API code maintains backwards runtime compatibility with PHP 5.6.40+ syntax via webservice/api/core/PhpCompatibility.php and a bundled mbstring polyfill.

## Project Structure

```text
ShodhanaKG/
├── data/                 Raw datasets and graph exports
│   ├── csv/              Curated CSV tables
│   │   ├── nodes/        Node CSV files (2 node files)
│   │   └── relations/    Relationship CSV files (17 relation files)
│   └── graph_export/     JSON graph exports for database import
├── neo4j/                Neo4j Cypher schema definitions and load scripts
├── arcadedb/             ArcadeDB schema, database creation, and import scripts
├── webservice/           Application source code
│   ├── frontend/         React single-page application (Vite, TypeScript, Tailwind)
│   │   ├── src/          Application UI components, pages, hooks, and lib
│   │   ├── public/       Static web assets and fonts
│   │   └── tests/        Playwright end-to-end browser tests
│   └── api/              PHP backend web services
│       ├── core/         Modular query execution, router, and LLM transport
│       ├── tests/        PHP and Python unit and evaluation tests
│       │   ├── contracts/ JSON schema contracts
│       │   └── fixtures/  Frozen ground truth datasets and snapshots
│       └── storage/      Runtime cache and logs
├── docs/                 Documentation and technical reports
│   ├── webservice/       Frontend, backend API, chatbot, and deployment guides
│   ├── arcadedb/         ArcadeDB schema, query, and data documentation
│   ├── evaluation/       Benchmark and test evaluation reports
│   └── general/          Project structure specification and changelog
├── results/              Benchmark latency raw data
├── scripts/              Packaging and release utilities
├── LICENSE               MIT License
└── README.md             Project overview and setup guide
```

## Documentation

### Web Service Guides
- [Frontend Documentation](docs/webservice/FRONTEND_DOCUMENTATION.md)
- [Backend Documentation](docs/webservice/BACKEND_DOCUMENTATION.md)
- [API Endpoints Reference Specification](docs/webservice/api_endpoints_table.md)
- [Chatbot Documentation](docs/webservice/CHATBOT.md)
- [Deployment Guide](docs/webservice/DEPLOYMENT.md)
- [Quick Start Guide](docs/webservice/QUICK_START.md)
- [PHP 5.6 Compatibility](docs/webservice/PHP56_COMPATIBILITY.md)
- [Release Package](docs/webservice/RELEASE_PACKAGE.md)

### Evaluation & Audit Reports
- [Evaluation Guide](docs/evaluation/EVALUATION_GUIDE.md)
- [Chatbot Query Coverage Report](docs/evaluation/CHATBOT_QUERY_COVERAGE_REPORT.md)
- [Chatbot Validation Report](docs/evaluation/CHATBOT_VALIDATION_REPORT.md)

### General Documentation
- [Project Structure](docs/general/PROJECT_STRUCTURE.md)
- [Changelog](docs/general/CHANGELOG.md)

### Database Documentation
- [ArcadeDB Documentation Directory](docs/arcadedb/)

## Citation

Citation details will be added with the forthcoming manuscript.

## Team

[Raghava Lab](http://webs.iiitd.edu.in/raghava/), Department of Computational Biology, Indraprastha Institute of Information Technology (IIIT-Delhi), New Delhi, India.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
