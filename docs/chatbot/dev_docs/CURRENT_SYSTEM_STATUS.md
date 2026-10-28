# System and Verification Specification

## Answering and Context
The chatbot operates over 53 allowlisted ArcadeDB traversal endpoints (all 53 covered by benchmark evaluation cases in `webservice/api/tests/fixtures/chatbot_evaluation_cases.json`), validated semantic paths, a knowledge-graph-backed entity resolver, and static in-process caching in `GraphRead.php` and `SemanticQuery.php`. Enumerated results reflect deterministic graph retrieval. Ollama language assistance does not alter or generate factual ground truth or entity records. Node descriptions draw on stored node properties. Structured conversation context is revalidated before query execution. JSON and SSE responses deliver consistent terminal metadata and literature citations.

Intent routing supports English queries with transliterated Sanskrit and Ayurvedic terms across supported schema paths (bounded to five hops maximum). Unrecognized or ambiguous entities prompt disambiguation.

## Explorer and Source Scope
The Integrated Explorer maintains defined category boundaries: Indication and Ayurvedic_Formulation connections are queried through dedicated endpoints. Subgraph node, edge, and depth bounds protect system performance.

Statistics measure distinct namespace-qualified source identifiers. Identifier namespaces distinguish PubMed, DOI, and AYUSH Research Portal records.

## Configuration and Environment
`OLLAMA_URL` defines the model endpoint (`OLLAMA_BASE_URL` is retained as a compatibility alias). PHP environment variables configure the backend service (`ARCADE_URL`, `ARCADE_DB`, `ARCADE_USER`, `ARCADE_PASS`). Frontend builds configure `VITE_API_BASE` and `VITE_CHAT_ENABLED`.

Fonts and assets are bundled locally. Evidence links navigate to PubMed, DOI, or AYUSH Research Portal records; unclassified records display raw citations without fabricated external URLs.

## Release Verification
Verification runs across the frontend, API, database, and language model services:

```sh
python -B webservice/api/tests/run_release_checks.py
```

The runner executes linting, build typechecking, frontend unit tests, Python unit tests, PHP regression tests, and Playwright browser tests.
Evaluation suites test structured context, 20-turn conversation history, entity resolution, node properties, and relationship directions against frozen knowledge graph snapshots.
