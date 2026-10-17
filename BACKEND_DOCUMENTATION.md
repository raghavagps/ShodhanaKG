> Architecture reference with historical sections. For current statistics, namespace handling, chatbot behavior and release verification, use [Current system status](docs/chatbot/CURRENT_SYSTEM_STATUS.md). Superseded fallback/count/PMID-normalization examples below must not be used as implementation guidance.

# ShodhanaKG Explorer Suite — Backend Documentation

## Table of Contents

1. [Tech Stack](#1-tech-stack)
2. [Architecture Overview](#2-architecture-overview)
3. [Database: ArcadeDB](#3-database-arcadedb)
4. [Connection Layer: db.php](#4-connection-layer-dbphp)
5. [Shared Service: graph_service.php](#5-shared-service-graph_servicephp)
6. [API Endpoints](#6-api-endpoints)
7. [Cross-Cutting Concerns](#7-cross-cutting-concerns)
8. [Deployment](#8-deployment)
9. [Testing](#9-testing)
10. [File Reference](#10-file-reference)

---

## 1. Tech Stack

| Category | Technology | Version |
|---|---|---|
| **Language** | PHP | 5.6.40+ (legacy compatibility) |
| **Database** | ArcadeDB | Multi-model graph database |
| **HTTP Transport** | cURL / `file_get_contents` / `fsockopen` (fallback chain) | — |
| **Protocol** | ArcadeDB REST API v1 | HTTP POST to `/api/v1/command/` |
| **API Format** | JSON over HTTP | UTF-8 |
| **CORS** | Custom header-based | Configurable origins |
| **Web Server** | Apache or Nginx | Shared hosting compatible |

### PHP 5.6 Compatibility

Production API files target **PHP 5.6.40** as a legacy compatibility runtime and are also regression-tested on PHP 8.2. This is not a security endorsement: PHP 5.6 is end-of-life. Prefer a supported PHP release for production. The independent evaluation/development scripts under `api/tests/` still require modern PHP, except the explicitly PHP-5.6-compatible runtime tests.

`api/core/PhpCompatibility.php` preserves scalar/nullable argument and return validation, JSON failures, sorting, and suffix matching without PHP 7/8-only syntax or functions. Exception handlers catch `Exception` on PHP 5.6 and retain `Throwable` handling on modern PHP. Fatal engine errors on PHP 5.6 cannot be recovered as modern `Error` objects.

JSON is required. Native mbstring is preferred, with a bundled Unicode polyfill for legacy hosts using iconv. cURL is recommended; socket fallbacks support database and Ollama requests when URL reads are disabled. Runtime compatibility alone does not verify the production server's configuration, credentials, HTTPS trust store, or database contents. See `docs/deployment/PHP56_COMPATIBILITY.md` for verification commands and limits.

Language features avoided:
- No typed properties (`int $x`)
- No named arguments
- No arrow functions (`fn() =>`)
- No nullsafe operator (`?->`)
- No union types
- No `readonly` properties
- No `match` expression

Language features used:
- Both `array()` and `[]` short arrays (short arrays are supported since PHP 5.4)
- `foreach` loops
- `try`/`catch`/`throw` (available since PHP 5)
- `sprintf` for string interpolation
- `json_decode`/`json_encode`

---

## 2. Architecture Overview

```
┌──────────────┐     HTTP/JSON      ┌───────────────────┐     cURL/fsockopen     ┌────────────┐
│   Frontend   │ ◄─────────────────► │   PHP API Layer   │ ◄─────────────────────► │  ArcadeDB  │
│   (Vite+React)│    :8080/:8081     │  (api/*.php)      │     :2480              │   Graph DB │
└──────────────┘                     └───────────────────┘                        └────────────┘
```

### Request Flow

```
1. Browser → GET /api/search.php?domain=disease&q=arthritis&limit=8
2. PHP receives request
3. cors.php handles CORS preflight
4. config.php loads connection parameters (env vars or defaults)
5. graph_service.php resolves domain-specific types, field mappings
6. db.php sends SQL command to ArcadeDB via REST API
7. ArcadeDB executes SQL, returns JSON result
8. PHP processes result, builds normalized response
9. response.php sends JSON response with {ok: true, ...} structure
```

### Directory Layout

```
api/
├── config.php                      ← Central configuration (env var defaults)
├── cors.php                        ← CORS header management
├── response.php                    ← JSON response helpers
├── db.php                          ← ArcadeDB connection (3-tier fallback)
├── graph_service.php               ← Shared graph traversal, schema, normalization
├── health.php                      ← GET /health.php — ArcadeDB connectivity check
├── search.php                      ← GET /search.php — Full-text search
├── graph.php                       ← GET /graph.php — Subgraph traversal
├── node.php                        ← GET /node.php — Single node + neighbors
├── category-stats.php              ← GET /category-stats.php — Per-category stats
├── kg-stats.php                    ← GET /kg-stats.php — Global KG statistics
├── counts.php                      ← GET /counts.php — Per-category counts
├── bridge-stats.php                ← GET /bridge-stats.php — Bridge table data
├── provenance-subgraph.php         ← GET /provenance-subgraph.php — Disease provenance
├── compound-provenance.php         ← GET /compound-provenance.php — Compound provenance
├── test_db.php                     ← Manual DB connection test
├── version.php                     ← PHP version info
├── provenance-subgraph.example.php ← Documentation for provenance endpoint
└── tests/
    └── provenance-subgraph.test.php ← PHP unit tests
```

---

## 3. Database: ArcadeDB

### 3.1 Connection Details

Configured in `api/config.php`:

```php
$ARCADE_URL  = getenv('ARCADE_URL') ?: 'http://localhost:2480';
$ARCADE_DB   = getenv('ARCADE_DB') ?: 'Database_name';
$ARCADE_USER = getenv('ARCADE_USER') ?: 'Username';
$ARCADE_PASS = getenv('ARCADE_PASS') ?: 'Password';
```

The API connects to ArcadeDB's **REST API v1** at:
```
POST {ARCADE_URL}/api/v1/command/{encoded_db_name}
```

Requests are authenticated via HTTP Basic Auth.

### 3.2 ArcadeDB SQL Quirks

The backend works around several ArcadeDB-specific behaviors:

| Quirk | Workaround |
|---|---|
| **No `V`/`E` base types** | Direct queries against vertex/edge type names |
| **No `LOWER()` function** | Uses `.toLowerCase()` syntax |
| **No `OFFSET` keyword** | Uses `SKIP` instead |
| **Vertex type names with spaces** | Backtick-quoted in SQL (e.g., `` `Assessment Parameters` ``) |
| **Case-insensitive search** | `coalesce(col, '').toLowerCase() LIKE '%term%'` |
| **No explicit JOIN** | Multi-step queries: fetch edges → collect RIDs → fetch vertices |
| **Float PMID values** | PMIDs like `23723667.0` must be normalized to `23723667` |

### 3.3 Schema: Vertex Types

17 vertex types in the database:

| Vertex Type | Domain | Label Field | Description Field |
|---|---|---|---|
| `Disease` | disease | `name`, `Disease` | `description`, `Description` |
| `Symptoms` | disease | `name`, `Symptoms` | — (uses first non-null) |
| `Ayurvedic Diagnosis` | disease | `name`, `label` | `Description.1` |
| `Doshas` | disease | `name`, `Doshas` | — |
| `Therapy` | disease | `name`, `Therapy` | `description`, `Therapy_Description` |
| `Formulation` | disease | `name`, `Formulation` | `Description.4` |
| `Assessment Parameters` | disease | `name`, `Assessment Parameters` | — |
| `Compound` | compound | `name`, `label` | `description` |
| `Shodhana_Method` | compound | `name`, `label` | `description` |
| `Toxic_Substance` | compound | `name`, `label` | — |
| `Purified_Compound` | compound | `name`, `label` | — |
| `Shodhana_Type` | compound | `name`, `label` | `description` |
| `Media` | compound | `name`, `label` | `description` |
| `Confirmation_Study` | compound | `name`, `label` | — |
| `Process` | compound | `name`, `label` | `description` |
| `Indication` | compound | `name`, `label` | — |
| `Ayurvedic_Formulation` | compound | `name`, `label` | — |

### 3.4 Schema: Edge Types

#### Disease Domain Edges (9 types)

| Edge Type | Source → Target | Properties |
|---|---|---|
| `HAS_SYMPTOM` | Disease → Symptoms | — |
| `HAS_AYURVEDIC_DIAGNOSIS` | Disease → Ayurvedic Diagnosis | — |
| `HAS_DOSHA_IMBALANCE` | Disease → Doshas | — |
| `TREATED_BY` | Disease → Therapy | `Pubmed ID`, `ref` |
| `PRE_TREATED_BY` | Disease → Therapy | `Pubmed ID`, `ref` |
| `POST_TREATED_BY` | Disease → Therapy | `Pubmed ID`, `ref` |
| `USES_FORMULATION` | Therapy → Formulation | `Pubmed ID`, `ref`, `Dose`, `Duration`, `Adjuvant` |
| `ASSESS` | Disease ← Assessment Parameters (reverse) | `Pubmed ID`, `ref`, `Outcome Measure` |
| `RESOLVED_BY` | (declared but unused) | — |

#### Compound Domain Edges (13 types)

| Edge Type | Source → Target | Properties |
|---|---|---|
| `UNDERGOES` | Compound → Shodhana_Method | `Procedure` |
| `USES_MEDIA` | Shodhana_Method → Media | `Procedure`, `Media Used`, `Media_Used` |
| `PRODUCES` | Shodhana_Method/Purified_Compound → (other) | — |
| `CONTAINS` | Compound → Toxic_Substance | — |
| `DERIEVED_FROM` | Compound ← Purified_Compound (reverse) | — |
| `DETOXIFIES` | Shodhana_Method → Toxic_Substance | — |
| `CLASSIFIED_AS` | Shodhana_Method → Shodhana_Type | `Result` |
| `CONFIRMS_SAFETY_OF` | Confirmation_Study → Purified_Compound | `Result`, `Dose`, `Duration` |
| `VALIDATED_BY` | Shodhana_Method → Confirmation_Study | — |
| `FOLLOWED_BY` | Shodhana_Method/Process → (next) | — |
| `TREATS` | Purified_Compound → Indication | — |
| `USED_FOR_TREATMENT` | Purified_Compound → Disease | `ref` |
| `USED_IN_FORMULATION` | Purified_Compound → Ayurvedic_Formulation | — |

### 3.5 ArcadeDB SQL Patterns Used

**Counting vertices:**
```sql
SELECT COUNT(*) AS cnt FROM `Disease`
```

**Case-insensitive search:**
```sql
SELECT FROM `Disease`
WHERE coalesce(name, '').toLowerCase() LIKE '%arthritis%'
   OR coalesce(Disease, '').toLowerCase() LIKE '%arthritis%'
```

**Fetch neighbors via edge table:**
```sql
SELECT @out, @in FROM `TREATED_BY`
WHERE @out IN [#5:33, #5:34]
```

**Subqueries (for bridge stats):**
```sql
SELECT @rid, out('USED_FOR_TREATMENT').size() AS cnt
FROM Purified_Compound
WHERE out('USED_FOR_TREATMENT').size() > 0
```

---

## 4. Connection Layer: db.php

`api/db.php` implements a **three-tier connection fallback** with manual chunked-transfer decoding.

### Function: `arcade_command($command, $language, $params)`

Sends a SQL command to ArcadeDB and returns the parsed JSON result.

#### Tier 1: cURL (preferred)

```php
if (function_exists('curl_init')) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_USERPWD => "$user:$pass",
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 30,
  ]);
  $raw = curl_exec($ch);
  // ...handle response...
}
```

#### Tier 2: `file_get_contents` (stream context)

```php
$auth = base64_encode("$user:$pass");
$options = [
  'http' => [
    'method' => 'POST',
    'header' => ["Content-Type: application/json; charset=utf-8", "Authorization: Basic $auth"],
    'content' => $payload,
    'timeout' => 30,
    'ignore_errors' => true,
  ],
];
$context = stream_context_create($options);
$raw = @file_get_contents($url, false, $context);
```

#### Tier 3: Raw `fsockopen` (lowest compatibility)

```php
$fp = @fsockopen($host, $port, $errno, $errstr, 30);
// Manually sends HTTP/1.1 POST request with Basic Auth
// Reads response including chunked Transfer-Encoding
// Implements manual chunked decoding
```

#### Chunked Transfer Decoding

Implemented manually in `arcade_command_socket()`:

```php
if ($isChunked) {
  $dechunked = '';
  $offset = 0;
  while ($offset < strlen($body)) {
    $next = strpos($body, "\r\n", $offset);
    $size = hexdec(substr($body, $offset, $next - $offset));
    if ($size == 0) break;
    $dechunked .= substr($body, $next + 2, $size);
    $offset = $next + 2 + $size + 2;
  }
  $body = $dechunked;
}
```

### Connection Error Handling

If all three methods fail:
```php
throw new Exception(
  'All ArcadeDB connection methods failed (cURL missing, url_fopen disabled, and sockets failed).'
);
```

---

## 5. Shared Service: graph_service.php

This is the largest backend file (339 lines) housing all shared graph operations.

### 5.1 Schema Registration

```php
function get_schema_vertex_types(): array
```
Returns all 17 vertex types.

```php
function vertex_types_for_domain($domain): array
```
Filters vertex types by domain (disease: 7, compound: 10, integrated: 17).

```php
function edge_types_for_domain($domain): array
```
Filters edge types by domain (disease: 9, compound: 13, integrated: 22).

### 5.2 Type Normalization

ArcadeDB uses spaced/underscored names; the frontend uses camelCase/PascalCase.

**`normalize_type($dbType)`** — Converts DB type to frontend type:

| DB Type | Frontend Type |
|---|---|
| `Symptoms` | `Symptom` |
| `Ayurvedic_Diagnosis` | `AyurvedicDiagnosis` |
| `Assessment_Parameter` | `AssessmentParameter` |
| `Shodhana_Method` | `ShodhanaMethod` |
| `Toxic_Substance` | `ToxicSubstance` |
| `Purified_Compound` | `PurifiedCompound` |
| `Confirmation_Study` | `ConfirmationStudy` |
| `Shodhana_Type` | `ShodhanaType` |
| `Ayurvedic_Formulation` | `AyurvedicFormulation` |

**`normalize_edge_type($dbEdgeType)`** — Converts DB edge type to frontend:

| DB Edge Type | Frontend Type |
|---|---|
| `PRE_TREATED_BY` | `PRETREATED_BY` |
| `POST_TREATED_BY` | `POSTTREATED_BY` |
| `USES_FORMULATION` | `USES_FORMULATION` |
| `DERIEVED_FROM` | `DERIVED_FROM` |
| Others | Unchanged |

**`db_type_for_frontend_type($frontendType)`** — Reverse mapping (frontend → DB), used in search endpoints.

### 5.3 Field Resolution

Different vertex types store their label and description in different fields.

```php
function label_fields_for_type($dbType): array
```

Returns candidate field names ordered by priority:
- `Disease`: `['name', 'Disease']`
- `Symptoms`: `['name', 'Symptoms']`
- `Ayurvedic Diagnosis`: `['name', 'label']`
- `Therapy`: `['name', 'Therapy']`
- `Formulation`: `['name', 'Formulation']`
- Default: `['name', 'label', 'Disease', 'Symptoms']`

```php
function description_fields_for_type($dbType): array
```

Description fields:
- `Disease`: `['description', 'Description']`
- `Therapy`: `['description', 'Therapy_Description']`
- Others: `['description']`

```php
function search_fields_for_type($dbType): array
```

Searchable fields by type (used for LIKE queries):
- `Disease`: `['name', 'Disease', 'Description']`
- `Ayurvedic Diagnosis`: `['name', 'label', 'Description.1']`
- `Therapy`: `['name', 'Therapy', 'Therapy_Description']`
- `Formulation`: `['name', 'Formulation', 'Description.4']`

### 5.4 Vertex Functions

```php
function vertex_label($rec): string    // Extract label from record using label_fields_for_type
function vertex_description($rec): string  // Extract description
function vertex_id($rec): string       // Extract @rid or id field
```

### 5.5 Node Builder

```php
function build_node($rec): array
```

Maps a raw ArcadeDB vertex record to a normalized API node:

```php
$node = [
  'id'          => vertex_id($rec),
  'label'       => vertex_label($rec),
  'type'        => normalize_type($rec['@type']),
  'description' => vertex_description($rec),
  // ...all other properties merged (excluding @rid, @type, @class, @version, @cat, id, name, label, description)
];
```

### 5.6 Edge Builder

```php
function build_edge($rec, $vertexMap): ?array
```

Maps a raw ArcadeDB edge record to a normalized API edge:

```php
$edge = [
  'id'     => $rec['@rid'],
  'source' => $vertexMap[$rec['@out']]['id'],
  'target' => $vertexMap[$rec['@in']]['id'],
  'type'   => normalize_edge_type($rec['@type']),
  // ...mapped properties from map_edge_props()
];
```

Returns `null` if source or target vertex is missing.

### 5.7 Edge Property Mapping

```php
function map_edge_props($rec): array
```

Maps database column names to API property names:

| DB Column | API Property |
|---|---|
| `ref` | `pubmedId`, `reference` |
| `Pubmed ID` | `pubmedId` |
| `Outcome Measure` / `Outcome_Measure` / `Output Measure` / `Output_Measure` | `outputMeasure` |
| `Adjuvant` / `adjuvant` | `adjuvant` |
| `Dose` / `dose` | `dose` |
| `Duration` / `duration` | `duration` |
| `Procedure` | `procedure` |
| `Result` | `result` |
| `Media_Used` / `Media Used` | `mediaUsed` |

### 5.8 Seed Resolution

```php
function resolve_seeds($seedIds, $domain): array
```

Resolves seed IDs (either `@rid` prefixed with `#` or `id` field values) to full vertex records by searching all vertex types for the domain.

```php
function fetch_vertex_map($ridCsv): array
```

Fetches multiple vertices by `@rid` CSV string and returns a map of `@rid → build_node($rec)`.

### 5.9 SQL Escaping

```php
function arcade_escape($s): string
{
  return str_replace(["'", '\\'], ["''", '\\\\'], $s);
}
```

Double-quote and backslash escaping for safe SQL string interpolation.

---

## 6. API Endpoints

### 6.1 health.php

**GET `/api/health.php`**

Tests ArcadeDB connectivity with `SELECT 1`.

**Response:**
```json
{
  "ok": true,
  "service": "shodhana-api",
  "version": "v1",
  "arcade": {
    "connected": true,
    "test": { "query": "SELECT 1", "result": [...] }
  }
}
```

**Errors:** 500 if ArcadeDB unreachable.

---

### 6.2 search.php

**GET `/api/search.php?domain=disease&q=arthritis&limit=8&type=Disease`**

Full-text search across domain vertex types.

**Parameters:**

| Param | Required | Default | Description |
|---|---|---|---|
| `domain` | Yes | — | `disease`, `compound`, or `integrated` |
| `q` | Yes | — | Search query string |
| `limit` | No | 100 | Max results (per-type limit × 2) |
| `type` | No | — | Filter by specific frontend type |

**Algorithm:**
1. Resolve allowed vertex types for the domain (`vertex_types_for_domain`)
2. If `type` param provided, convert to DB type and search only that type
3. For each type, construct `WHERE` clause with `coalesce(field, '').toLowerCase() LIKE '%term%'` on all searchable fields
4. Special case: for `integrated` domain + `Purified_Compound` type, add `AND out('USED_FOR_TREATMENT').size() > 0`
5. Run up to `limit` results across all types

**Response:**
```json
{
  "ok": true,
  "nodes": [
    { "id": "36", "label": "Eczema", "type": "Disease", "description": "..." }
  ],
  "debug": {
    "Disease": { "sql": "SELECT FROM ...", "error": null, "count": 5 }
  }
}
```

**Errors:** 400 (invalid domain, empty query), 500 (DB failure).

---

### 6.3 graph.php

**GET `/api/graph.php?domain=disease&seeds=36&depth=1&limitNodes=120&limitEdges=300&integratedMode=1hop`**

Subgraph traversal from seed nodes.

**Parameters:**

| Param | Required | Default | Description |
|---|---|---|---|
| `domain` | Yes | — | `disease`, `compound`, or `integrated` |
| `seeds` | Yes | — | Comma-separated seed IDs |
| `depth` | No | 1 | Traversal depth (0–2) |
| `limitNodes` | No | 120 | Max nodes (1–500) |
| `limitEdges` | No | 300 | Max edges (1–2000) |
| `integratedMode` | No | `1hop` | `bridge` or `1hop` (integrated only) |

#### Domain: disease/compound (Simple Traversal)

```
for depth = 0 to depth-1:
  1. Query all edge types from frontier
  2. Collect neighbor RIDs
  3. Fetch neighbor vertices via fetch_vertex_map()
  4. Build edges from results
  5. Check truncation limits
  6. Set frontier = new neighbor RIDs
```

#### Domain: integrated (Two-Sided Traversal)

The integrated graph simultaneously traverses from both compound and disease seeds:

1. Resolve seeds: categorize as compound-side or disease-side by `@type`
2. **Bridge step**: Query `USED_FOR_TREATMENT` and `USES_FORMULATION` edges connecting both sides
3. If `integratedMode !== 'bridge'`:
   - For 1 hop: traverse compound edges from compound frontier, disease edges from disease frontier
   - Each `integrate_edges()` call fetches neighbor vertices and categorizes them

**`integrate_edges($rawEdges, &$nodeMap, &$edgeMap, $limitNodes, $limitEdges, &$rawTypeMap)`:**

Processes raw edges, fetches unknown neighbor vertices, categorizes by compound vs disease type, builds normalized edges.

**Edge type sets:**

- **Bridge edges**: `USED_FOR_TREATMENT`, `USES_FORMULATION`
- **Compound edges**: `UNDERGOES`, `CONTAINS`, `VALIDATED_BY`, `USES_MEDIA`, `CONFIRMS_SAFETY_OF`, `DERIEVED_FROM`, `DETOXIFIES`, `CLASSIFIED_AS`, `PRODUCES`, `FOLLOWED_BY`
- **Disease edges**: `ASSESS`, `HAS_AYURVEDIC_DIAGNOSIS`, `HAS_DOSHA_IMBALANCE`, `HAS_SYMPTOM`, `POST_TREATED_BY`, `PRE_TREATED_BY`, `RESOLVED_BY`, `TREATED_BY`, `USES_FORMULATION`

**Response:**
```json
{
  "ok": true,
  "nodes": [...],
  "edges": [...],
  "meta": {
    "depth": 1,
    "truncated": false,
    "truncatedReason": null,
    "totalNodes": 42,
    "totalEdges": 89,
    "queryMs": 234
  }
}
```

**Errors:** 400 (invalid domain, no seeds), 404 (seeds not found), 500 (DB failure).

---

### 6.4 node.php

**GET `/api/node.php?domain=disease&id=36&neighborLimit=50`**

Fetch a single node with its neighbors and evidence.

**Parameters:**

| Param | Required | Default | Description |
|---|---|---|---|
| `domain` | Yes | — | `disease`, `compound`, or `integrated` |
| `id` | Yes | — | Node ID |
| `neighborLimit` | No | 50 | Max neighbor edges (1–200) |

**Algorithm:**
1. Search all vertex types for the node by `id` field
2. For each allowed edge type, query edges connected to this node
3. Collect neighbor RIDs
4. Fetch neighbor vertices
5. Collect evidence (edges with non-empty `ref`/`Pubmed ID`)

**Response:**
```json
{
  "ok": true,
  "node": { "id": "36", "label": "Eczema", "type": "Disease", ... },
  "neighbors": [...],
  "evidence": [
    { "edgeId": "#70:0", "pubmedId": "23723667", "reference": "23723667" }
  ]
}
```

**Errors:** 400 (no id), 404 (not found), 500 (DB failure).

---

### 6.5 stats.php

**GET `/api/stats.php?domain=disease&q=&sort=name&dir=asc&limit=25&offset=0`**

Paginated disease/compound listing with relationship counts.

**Parameters:**

| Param | Required | Default | Description |
|---|---|---|---|
| `domain` | Yes | — | `disease` or `compound` |
| `q` | No | — | Search filter |
| `sort` | No | `name` | Sort column |
| `dir` | No | `asc` | `asc` or `desc` |
| `limit` | No | 25 | Page size (1–100) |
| `offset` | No | 0 | Page offset |

#### Disease Stats

1. `SELECT ... FROM Disease ... ORDER BY name ASC LIMIT {limit} SKIP {offset}`
2. Count total matching diseases
3. For each disease RID, query edge counts across:
   - `HAS_SYMPTOM` → `symptoms`
   - `HAS_AYURVEDIC_DIAGNOSIS` → `ayurvedicDiagnosis`
   - `HAS_DOSHA_IMBALANCE` → `doshas`
   - `TREATED_BY` + `PRE_TREATED_BY` + `POST_TREATED_BY` → `therapy`
   - `ASSESS` (reverse: `@in`) → `assessmentParameters`
4. Client-side sorting on any column
5. Response includes per-category counts + `total`

#### Compound Stats

1. Simple direct counts for `UNDERGOES` → `shodhanaMethods`, `CONTAINS` → `toxicSubstances`
2. Bridge counts (purifiedCompounds, media, processes, diseases, etc.) are **not fully implemented** — fallback to 0
3. Client-side sorting

**Response:**
```json
{
  "ok": true,
  "rows": [
    {
      "id": "1",
      "name": "Rheumatoid Arthritis",
      "description": "...",
      "symptoms": 3,
      "ayurvedicDiagnosis": 1,
      "doshas": 1,
      "therapy": 2,
      "assessmentParameters": 1,
      "total": 8
    }
  ],
  "total": 42,
  "limit": 25,
  "offset": 0
}
```

**Errors:** 400 (invalid domain), 501 (integrated not implemented), 500.

---

### 6.6 kg-stats.php

**GET `/api/kg-stats.php`**

Global knowledge graph statistics.

**Algorithm:**
1. **Total Nodes**: Sum `COUNT(*)` across all 17 vertex types
2. **Total Relations**: Sum `COUNT(*)` across all 22 integrated edge types
3. **Total Studies**: Count unique `ref` values from evidence-bearing edge types
   - Edge types: `TREATED_BY`, `PRE_TREATED_BY`, `POST_TREATED_BY`, `ASSESS`, `USED_FOR_TREATMENT`
   - Hardcoded fallback: if count < 270, return 274

**Response:**
```json
{
  "ok": true,
  "totalNodes": 15234,
  "totalRelations": 28492,
  "totalStudies": 274
}
```

**Errors:** 500.

---

### 6.7 counts.php

**GET `/api/counts.php?domain=disease`**

Per-category entity counts for explorer home pages.

**Algorithm:**
1. For each vertex type in the domain, run `SELECT COUNT(*) AS cnt FROM \`{type}\``
2. Normalize type names for frontend

**Response:**
```json
{
  "ok": true,
  "counts": {
    "Disease": 42,
    "Symptom": 156,
    "AyurvedicDiagnosis": 28,
    "Therapy": 64,
    "Formulation": 89,
    "AssessmentParameter": 12,
    "Dosha": 3
  }
}
```

**Errors:** 400 (invalid domain), 500.

---

### 6.8 category-stats.php

**GET `/api/category-stats.php?domain=disease&category=Disease&q=&sort=name&dir=asc&limit=25&offset=0`**

Paginated listing with relationship counts for a specific entity category.

**Parameters:** Same as stats.php, plus `category` (required frontend type name).

**Architecture:**
- `$vertexMap`: Maps frontend type names to DB vertex type names (17 mappings)
- `$edgeConfig`: Per-vertex-type edge configuration specifying which edges to count and what key to use

**Example edgeConfig for Disease:**
```php
'Disease' => [
  ['edge' => 'HAS_AYURVEDIC_DIAGNOSIS', 'key' => 'ayurvedicDiagnosis'],
  ['edge' => 'HAS_DOSHA_IMBALANCE', 'key' => 'doshas'],
  ['edge' => 'TREATED_BY', 'key' => 'therapy'],
  ['edge' => 'PRE_TREATED_BY', 'key' => 'therapy'],
  ['edge' => 'POST_TREATED_BY', 'key' => 'therapy'],
  ['edge' => 'ASSESS', 'key' => 'assessmentParameters', 'reverse' => true],
  ['edge' => 'HAS_SYMPTOM', 'key' => 'symptoms'],
  ['edge' => 'USED_FOR_TREATMENT', 'key' => 'purifiedCompounds', 'reverse' => true],
]
```

**Algorithm:**
1. Count total entities matching search (`count_entities`)
2. Fetch paginated entities (`list_entities`)
3. For each configured edge type, use `count_edges_stats_cat(edgeType, ridList, reverse)`:
   - `GROUP BY @out` or `GROUP BY @in`
   - Returns map of RID → count
4. Compute `total` as sum of all key counts
5. Client-side sorting

**Response:** Same structure as `stats.php`.

**Errors:** 400 (invalid domain/category), 500.

---

### 6.9 bridge-stats.php

**GET `/api/bridge-stats.php?limit=50&offset=0`**

Lists purified compounds with disease bridges — used by the Integrated Table View.

**Parameters:**

| Param | Required | Default | Description |
|---|---|---|---|
| `limit` | No | 50 | Page size (1–200) |
| `offset` | No | 0 | Page offset |

**Algorithm:**
1. `SELECT FROM Purified_Compound WHERE out('USED_FOR_TREATMENT').size() > 0 ORDER BY name ASC LIMIT {limit} SKIP {offset}`
2. Count total matching purified compounds
3. For each purified compound:
   - Query `DERIEVED_FROM` edges: Compound → PurifiedCompound (reverse)
   - Query `USED_FOR_TREATMENT` edges: PurifiedCompound → Disease
   - Query `USED_IN_FORMULATION` edges: PurifiedCompound → AyurvedicFormulation
4. Fetch neighbor vertex labels
5. Build result rows with label arrays

**Response:**
```json
{
  "ok": true,
  "rows": [
    {
      "purifiedId": "pc1",
      "purifiedLabel": "Shuddha Guggulu",
      "purifiedDescription": "...",
      "compoundIds": ["Guggulu"],
      "diseaseCount": 3,
      "formulationCount": 2,
      "diseaseLabels": ["Rheumatoid Arthritis", "Osteoarthritis", "Obesity"],
      "formulationLabels": ["Trayodashanga Guggulu", "Yogaraj Guggulu"]
    }
  ],
  "total": 15,
  "limit": 50,
  "offset": 0
}
```

**Errors:** 500.

---

### 6.10 provenance-subgraph.php

**GET `/api/provenance-subgraph.php?diseaseId=36&pubmedId=23723667&limit=50`**

Extracts Disease → Therapy → Formulation paths where both edges share the same PubMed ID (co-citation provenance).

**Parameters:**

| Param | Required | Default | Description |
|---|---|---|---|
| `diseaseId` | Yes | — | Disease ID or `@rid` (e.g. `36` or `#5:33`) |
| `pubmedId` | No | — | Filter by specific PubMed ID |
| `limit` | No | 50 | Max paths (1–200) |

**Algorithm:**
1. **Resolve disease**: Search by `id` field, fallback to case-insensitive name match
2. **Disease → Therapy**: Query `PRE_TREATED_BY`, `POST_TREATED_BY`, `TREATED_BY` edges
   - Extract PMID from `Pubmed ID` or `ref` field
   - **PMID normalization**: `rtrim(rtrim((string)$pmid, '0'), '.')` to handle float-encoded PMIDs
   - If `pubmedId` filter specified, skip non-matching edges
3. **Therapy → Formulation**: Query `USES_FORMULATION` edges
   - Same PMID extraction + normalization + filtering
4. **Build paths**: Only include paths where `pmid1 === pmid2` (provenance validation)
5. Fetch Therapy and Formulation vertices
6. Return paths with edge1, edge2, and provenance metadata

**Response:**
```json
{
  "ok": true,
  "paths": [
    {
      "disease": { "id": "36", "label": "Eczema", "type": "Disease", "description": "" },
      "edge1": { "id": "#70:0", "type": "PRETREATED_BY", "pubmedId": "23723667" },
      "therapy": { "id": "1155", "label": "Abhyanga", "type": "Therapy", "description": "..." },
      "edge2": { "id": "#84:14", "type": "USES", "pubmedId": "23723667", "dose": null, "duration": "1 Day", "adjuvant": null },
      "formulation": { "id": "1356", "label": "Bala Taila", "type": "Formulation", "description": "" },
      "provenance": { "pubmedId": "23723667", "confidence": "high" }
    }
  ],
  "meta": {
    "totalPaths": 3,
    "uniquePubmedIds": ["23723667", "21455456"],
    "queryMs": 75,
    "diseaseId": "36"
  }
}
```

**Errors:** 400 (no diseaseId), 404 (disease not found), 500.

---

### 6.11 compound-provenance.php

**GET `/api/compound-provenance.php?compoundId=123&limit=50`**

Extracts Compound → ShodhanaMethod → Media paths.

**Parameters:**

| Param | Required | Default | Description |
|---|---|---|---|
| `compoundId` | Yes | — | Compound ID or `@rid` |
| `limit` | No | 50 | Max paths (1–200) |

**Algorithm:**
1. **Resolve compound**: Search by `id` field, fallback to case-insensitive name match
2. **Compound → ShodhanaMethod**: Query `UNDERGOES` edges
3. **ShodhanaMethod → Media**: Query `USES_MEDIA` edges
4. Fetch ShodhanaMethod and Media vertices
5. Build paths

**Response:**
```json
{
  "ok": true,
  "paths": [
    {
      "compound": { "id": "...", "label": "...", "type": "Compound", "description": null },
      "edge1": { "id": "#...", "type": "UNDERGOES", "procedure": "..." },
      "shodhanaMethod": { "id": "...", "label": "...", "type": "ShodhanaMethod", "description": "..." },
      "edge2": { "id": "#...", "type": "USES_MEDIA", "procedure": "..." },
      "media": { "id": "...", "label": "...", "type": "Media", "description": "..." }
    }
  ],
  "meta": {
    "totalPaths": 5,
    "queryMs": 45,
    "compoundId": "..."
  }
}
```

**Errors:** 400, 404, 500.

---

## 7. Cross-Cutting Concerns

### 7.1 CORS (`cors.php`)

```php
function api_cors_allow()
function api_handle_cors_preflight()
```

- Reads `$ALLOWED_ORIGINS` from config (`*` for dev, comma-separated in production)
- Sends `Access-Control-Allow-Origin`, `Vary: Origin`, allowed methods/headers
- Handles `OPTIONS` preflight with 204 response
- Every endpoint calls `api_handle_cors_preflight()` at the top

### 7.2 Response Format (`response.php`)

```php
function api_ok($data, $status = 200)    // { ok: true, ...data }
function api_fail($code, $message, $status, $details = array())  // { ok: false, error: { code, message, details } }
```

All API responses follow:
```json
{
  "ok": true/false,
  ...data...
}
```

Error responses:
```json
{
  "ok": false,
  "error": {
    "code": "invalid_domain",
    "message": "Domain must be disease, compound, or integrated",
    "details": {}
  }
}
```

### 7.3 Configuration (`config.php`)

Environment variable resolution with local defaults:

| Env Var | Default | Purpose |
|---|---|---|
| `ARCADE_URL` | `http://localhost:2480` | ArcadeDB server URL |
| `ARCADE_DB` | `shodhana` | Database name |
| `ARCADE_USER` | `ayurveda` | DB user |
| `ARCADE_PASS` | `Gpsr2026$` | DB password |
| `ALLOWED_ORIGINS` | `*` | CORS allowed origins |
| `API_STORAGE_DIR` | `api/storage/` | Storage (caching/rate-limit) |
| `API_VERSION` | `v1` | Version string |

### 7.4 Error Handling Patterns

All endpoints follow:
```php
try {
  // ... main logic ...
  api_ok([...]);
} catch (Exception $e) {
  api_fail('endpoint_failed', 'Human-readable message', 500, [
    'message' => $e->getMessage(),
  ]);
}
```

Edge queries use silent catch per type — if a particular edge type doesn't exist in the DB, it's skipped rather than failing the entire request:
```php
try {
  $res = arcade_command($sql, 'sql');
  // ...
} catch (Exception $e) { /* skip */ }
```

### 7.5 Data Integrity: Type Mapping Between Frontend and Backend

```
Frontend Type (camelCase)   Backend DB Type (spaced/underscored)
──────────────────────────  ───────────────────────────────────
Disease                     Disease
Symptom                     Symptoms
AyurvedicDiagnosis          Ayurvedic Diagnosis
Dosha                       Doshas
Therapy                     Therapy
Formulation                 Formulation
AssessmentParameter         Assessment Parameters
Compound                    Compound
ShodhanaMethod              Shodhana_Method
ToxicSubstance              Toxic_Substance
PurifiedCompound            Purified_Compound
ShodhanaType                Shodhana_Type
Media                       Media
ConfirmationStudy           Confirmation_Study
Process                     Process
Indication                  Indication
AyurvedicFormulation        Ayurvedic_Formulation
```

---

## 8. Deployment

### 8.1 Server Requirements

- PHP 5.6.40+ (tested to work on PHP 5.6.40)
- `php-curl` extension (recommended)
- Web server: Apache or Nginx

### 8.2 Environment Configuration

Set via web server (`SetEnv` for Apache, `env` for Nginx):

```apache
SetEnv ARCADE_URL http://localhost:2480
SetEnv ARCADE_DB shodhana
SetEnv ARCADE_USER "username"
SetEnv ARCADE_PASS secure_password
SetEnv ALLOWED_ORIGINS https://your-frontend.com
```

### 8.3 Apache Configuration

See `apache.conf.example`:

```apache
<VirtualHost *:80>
    DocumentRoot /path/to/dist
    SetEnv ARCADE_URL http://localhost:2480
    SetEnv ARCADE_DB shodhana
    SetEnv ARCADE_USER "username"
    SetEnv ARCADE_PASS secure_password
    SetEnv ALLOWED_ORIGINS https://your-frontend.com

    <Directory /path/to/api>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 8.4 Development

```bash
# Start PHP built-in server
cd api
php -S localhost:8081 -t .
```

### 8.5 Security Notes

- The default credentials in `config.php` are for **local development only**
- Always set `ARCADE_USER`/`ARCADE_PASS`/`ALLOWED_ORIGINS` via environment variables in production
- ArcadeDB should have a read-only user for the API
- CORS is configurable to restrict origins

---

## 9. Testing

### PHP Unit Tests (`api/tests/`)

**`provenance-subgraph.test.php`** (183 lines)

Tests core logic functions used by the provenance endpoints:

| Test | What it verifies |
|---|---|
| `testPmidNormalization()` | Float PMID normalization (`23723667.0` → `23723667`) |
| `testEdgeTypeMapping()` | `normalize_edge_type()` for all mappings |
| `testNodeBuilding()` | `build_node()` label extraction from different vertex types |
| `testEdgePropertyMapping()` | `map_edge_props()` field mapping |

**Running tests:**
```bash
php api/tests/provenance-subgraph.test.php
```

### Manual DB Test

```bash
php api/test_db.php
```

---

## 10. File Reference

| # | File | Lines | Purpose |
|---|---|---|---|
| 1 | `api/config.php` | 20 | Environment variable defaults |
| 2 | `api/cors.php` | 36 | CORS header management |
| 3 | `api/response.php` | 24 | JSON response helpers |
| 4 | `api/db.php` | 149 | ArcadeDB 3-tier connection |
| 5 | `api/graph_service.php` | 339 | Shared graph traversal & schema |
| 6 | `api/health.php` | 34 | ArcadeDB connectivity check |
| 7 | `api/search.php` | 87 | Full-text search |
| 8 | `api/graph.php` | 273 | Subgraph traversal |
| 9 | `api/node.php` | 99 | Single node + neighbors |
| 10 | `api/stats.php` | 306 | Disease/Compound statistics |
| 11 | `api/category-stats.php` | 283 | Per-category statistics |
| 12 | `api/kg-stats.php` | 72 | Global KG statistics |
| 13 | `api/counts.php` | 40 | Per-category counts |
| 14 | `api/bridge-stats.php` | 164 | Bridge table data |
| 15 | `api/provenance-subgraph.php` | 270 | Disease provenance paths |
| 16 | `api/compound-provenance.php` | 219 | Compound provenance paths |
| 17 | `api/version.php` | 3 | PHP version info |
| 18 | `api/test_db.php` | 9 | Manual DB connection test |
| 19 | `api/chat.php` | 150 | AI Chatbot endpoint |
| 20 | `api/provenance-subgraph.example.php` | 74 | Provenance endpoint docs |
| 21 | `api/tests/provenance-subgraph.test.php` | 183 | Unit tests |
| 22 | `apache.conf.example` | 39 | Apache deployment config |
| 23 | `.env` | 1 | Dev environment (VITE_API_BASE) |
| 24 | `.env.production` | 1 | Production environment |

---

### 6.9 chat.php (AI Chatbot)

**POST `/api/chat.php`**

Real-time, streaming AI chatbot endpoint utilizing Retrieval-Augmented Generation (GraphRAG) with ArcadeDB and Ollama.

**Request Body (JSON):**
```json
{
  "messages": [
    { "role": "user", "content": "What are the symptoms of eczema?" }
  ],
  "context": {
    "domain": "disease",
    "seedIds": ["#36:0"]
  }
}
```

**Algorithm Pipeline:**
1. **Sanitization** (`api/chat/sanitize.php`): Validates input, checks LLM health, applies rate limiting, normalizes messages.
2. **Intent & Entity Extraction** (`api/chat/intent.php`): Identifies if the user is asking about a specific entity or a general graph structure.
3. **GraphRAG Retrieval** (`api/chat/retrieval.php`): Queries ArcadeDB for relevant vertex and edge data based on extracted entities and frontend context (seed IDs).
4. **Prompt Building** (`api/chat/prompt.php`): Formats the retrieved knowledge graph data into a strict LLM system prompt instructing it to cite PMIDs and only use the provided context.
5. **LLM Generation** (`api/chat/llm.php`): Initiates a streaming request to the local Ollama instance (`llama3.2`).
6. **SSE Streaming** (`api/chat/stream.php`): Processes the Ollama response stream, buffering tokens and flushing them to the client via Server-Sent Events (SSE).

**Response:**
Server-Sent Events (`text/event-stream`).

**Event Types:**
- `token`: A piece of generated markdown text.
- `citation`: A PubMed ID referenced by the model.
- `metadata`: Contains the retrieved sources (nodes and PMIDs) used for generation.
- `error`: Error message.

**Errors:** 400 (invalid payload), 429 (rate limit), 503 (Ollama offline).
