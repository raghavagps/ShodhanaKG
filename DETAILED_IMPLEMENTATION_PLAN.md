# DETAILED_IMPLEMENTATION_PLAN - ShodhanaKG API

This document outlines the implementation details of the ShodhanaKG API, specifically focusing on the stabilization for legacy PHP 5.6 environments and ArcadeDB integration.

## 1. Environment Constraints
The production environment has the following constraints:
- **PHP Version**: 5.6.40-95 (Ubuntu).
- **Extensions**: Restricted access to `php-curl`, `php-mbstring`, and `allow_url_fopen`.
- **Database**: ArcadeDB (Graph Database) running on a separate port.

## 2. Backend Architecture (PHP)
The backend is a lightweight REST API built with functional PHP 5.6 to ensure maximum compatibility.

### Core Modules:
- **`db.php`**: Implements a three-tier connectivity fallback:
  1.  **cURL**: Primary method (if extension enabled).
  2.  **file_get_contents**: Secondary method (if `allow_url_fopen` enabled).
  3.  **fsockopen**: Manual HTTP request implementation (works in hardened environments).
- **`graph_service.php`**: Centralizes domain-aware schema mapping, edge-type normalization, and vertex property resolution. Replaces PHP 7.0+ constant arrays with functional getters.
- **`response.php`**: Standardizes JSON response envelopes (`api_ok`, `api_fail`).

## 3. Database Interaction (ArcadeDB)
Queries are executed using ArcadeDB's SQL dialect with specific robustness patterns:
- **Null Safety**: Every field access in SQL uses `coalesce(field, '')` to prevent ArcadeDB from crashing on null-valued records.
- **Case-Insensitivity**: Uses `.toLowerCase()` instead of `LOWER()` as per ArcadeDB SQL specifications.
- **Field Escaping**: Property names with dots (e.g., `Description.4`) or spaces are escaped with backticks (`` ` ``).

## 4. Explorer Modes
The suite supports three distinct explorer modes, each with dedicated logic:

### Disease Explorer
- **Traversal**: Navigates from `Disease` → `Symptoms`, `Ayurvedic_Diagnosis`, `Dosha`, and `Therapy`.
- **Edge Types**: `HAS_SYMPTOM`, `HAS_AYURVEDIC_DIAGNOSIS`, `HAS_DOSHA_IMBALANCE`, `TREATED_BY`.

### Compound Explorer
- **Traversal**: Navigates from `Compound` → `Shodhana_Method` → `Purified_Compound`.
- **Edge Types**: `UNDERGOES`, `PRODUCES`.

### Integrated Explorer (The "Bridge")
- **Traversal**: Connects the Clinical and Pharmacological domains.
- **Bridge Edges**: `USED_FOR_TREATMENT` (Purified_Compound → Disease), `USED_IN_FORMULATION` (Purified_Compound → Ayurvedic_Formulation).

## 5. Performance & Optimization
- **Pagination**: All table views use `LIMIT` and `SKIP` for high-performance server-side pagination.
- **Truncation**: Large graph payloads are truncated at the backend level to maintain frontend responsiveness.
- **CORS**: Implemented manual `cors.php` handler for secure cross-origin requests.

## Documentation
For comprehensive developer reference:
- **[Frontend Documentation](./FRONTEND_DOCUMENTATION.md)** — Complete React component reference, GraphCanvas internals, page-by-page breakdown, styling system, testing.
- **[Backend Documentation](./BACKEND_DOCUMENTATION.md)** — All 11 API endpoints with parameters and response schemas, ArcadeDB schema (17 vertex types, 22 edge types), 3-tier connection fallback, field mappings, deployment guide.

---
*Last Updated: 2026-05-16*
