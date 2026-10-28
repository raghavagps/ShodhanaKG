# Quick Start Guide

## Table of Contents

1. [Overview](#1-overview)
2. [Prerequisites](#2-prerequisites)
3. [Setup Steps](#3-setup-steps)
4. [Exploring the Platform](#4-exploring-the-platform)
5. [Running Tests](#5-running-tests)
6. [Troubleshooting](#6-troubleshooting)

---

## 1. Overview

This guide provides the steps to run the ShodhanaKG application locally. The setup includes ArcadeDB, the PHP backend API, the React frontend, and the local Ollama chatbot service.

---

## 2. Prerequisites

Verify that the following tools are installed:
- Node.js: Engine requirement `^22.12.0 || ^24.0.0 || >=26.0.0` with npm.
- PHP: Version 5.6.40+.
- ArcadeDB: ArcadeDB installed locally.
- Neo4j: Neo4j latest (Desktop or Server) (for data population and export).
- Ollama with the `llama3.2` model.

---

## 3. Setup Steps

### Step 1: Database Population and Startup

The graph is rebuilt from `data/graph_export/neo4j-export.json`. To populate or export from source data and start ArcadeDB:

1. **Load CSVs into Neo4j**:
   The source CSV files are provided in `data/csv/nodes/` and `data/csv/relations/`. Copy node CSV files and relationship CSV files into the Neo4j latest (Desktop or Server) `import` directory and execute [`Create_graph.Cypher`](arcadedb/Create_graph.Cypher):
   ```bash
   cat arcadedb/Create_graph.Cypher | cypher-shell -u username -p password -d neo4j
   ```
2. **Export to JSON**:
   Ensure `apoc.export.file.enabled=true` is set in `conf/apoc.conf` and execute [`Export.Cypher`](arcadedb/Export.Cypher):
   ```cypher
   CALL apoc.export.json.all("neo4j-export.json", {});
   ```
3. **Start ArcadeDB Server**:
   From your ArcadeDB installation directory:
   - Linux or macOS:
     ```bash
     bin/server.sh
     ```
   - Windows:
     ```bash
     bin/server.bat
     ```
4. **Import JSON into ArcadeDB and Run Sanity Checks**:
   Run [`Migrate_to_ArcadeDB.sql`](arcadedb/Migrate_to_ArcadeDB.sql) using the ArcadeDB console:
   ```bash
   bin/console.sh arcadedb/Migrate_to_ArcadeDB.sql
   ```
   Or execute the commands interactively:
   ```sql
   CONNECT remote:localhost username password;
   CREATE DATABASE shodhana;
   DISCONNECT;
   CONNECT remote:localhost/shodhana username password;
   IMPORT DATABASE file:///data/graph_export/neo4j-export.json;
   ```
   The ArcadeDB server reads this path. Adjust it if the repository is at a different location on the server.

   Verify 2,532 total nodes, 10,123 total relationships, 17 vertex types, 21 edge types, 0 edges missing `ref`, and 0 vertices missing `name`.
5. **Verify Database Service**:
   ```bash
   curl http://localhost:2480/api/v1/ready
   ```

### Step 2: Configure Environment Variables

Create your local `.env` file from the provided example:

```bash
cp .env.production.example .env
```

Review the values in `.env`:
- `ARCADE_URL`: `http://localhost:2480`
- `ARCADE_DB`: `shodhana`
- `ARCADE_USER`: `username`
- `ARCADE_PASS`: `password`
- `API_PORT`: `8081`
- `FRONTEND_PORT`: `8080`

### Step 3: Start the Backend API

Start the PHP API service:

```bash
php webservice/api/serve.php
```

Alternatively, use the built-in PHP web server directly:

```bash
php -S localhost:8081 -t webservice
```

Verify backend connectivity:

```bash
curl http://localhost:8081/api/health.php
```

A healthy response returns `{"ok":true,"service":"shodhana-api",...}`.

### Step 4: Start the Frontend Application

Open a separate terminal session and navigate to the frontend directory:

```bash
cd webservice/frontend
npm install
npm run dev
```

Open your browser at `http://localhost:8080`.

### Step 5: Chatbot LLM Setup

If you wish to enable conversational LLM assistance:

1. Start the Ollama daemon:
   ```bash
   ollama serve
   ```
2. Pull the default model:
   ```bash
   ollama pull llama3.2
   ```
3. Verify chatbot service health:
   ```bash
   curl http://localhost:8081/api/chatbot_health.php
   ```

Deterministic graph retrieval works without Ollama.

---

## 4. Exploring the Platform

### Disease Explorer

Navigate to `/explorer/disease`.
- Search for a disease name in the search bar.
- Switch to the Graph View to see connections across symptoms, doshas, and therapies.
- Select a Disease node and open the Provenance tab in the right sidebar to view verified co-cited citations.

### Compound Explorer

Navigate to `/explorer/compound`.
- Explore raw medicinal compounds, toxic constituents, Shodhana purification methods, processing media, and purified products.
- View multi-hop processing paths in the Compound Provenance panel.

### Integrated Explorer

Navigate to `/explorer/integrated`.
- Explore cross-domain relationships linking purified compounds to therapeutic formulations and disease indications.
- Toggle between bridge view and single-hop graph expansion modes.

### Chatbot

Click the chatbot launcher in the bottom-right corner of the application to open the conversational interface.
- Ask questions regarding disease treatments, formulations, toxic substances, or purification procedures.
- Inspect structured evidence cards and matched literature citations.

---

## 5. Running Tests

Run the test suites to verify system functionality:

- Frontend unit tests:
  ```bash
  npm --prefix webservice/frontend test
  ```
- Backend compatibility tests:
  ```bash
  php webservice/api/tests/test_php_compatibility.php
  ```
- Release verification suite:
  ```bash
  php webservice/api/tests/test_release_remediation.php
  ```
- Python unit tests:
  ```bash
  python -m unittest discover -s webservice/api/tests -p "test_*.py"
  ```

---

## 6. Troubleshooting

- ArcadeDB Connection Failed:
  Verify that ArcadeDB is running and accessible on port 2480. Confirm that `ARCADE_USER` and `ARCADE_PASS` in `.env` match database user credentials.
- Backend API Returns Errors:
  Confirm that `webservice/api/storage` and `webservice/api/storage/cache` exist and have write permissions.
- Frontend Cannot Reach API:
  Confirm that the PHP server is running on the port configured in `API_PORT`. In development, Vite forwards `/api` requests to `API_PROXY_TARGET`.
- Chatbot Reports Offline:
  Check that Ollama is running on port 11434 and that `llama3.2` has finished downloading. Check `webservice/api/chatbot_health.php` for details.
