# Current system and verification contract

This document supersedes architectural/status claims in older dated reports. Historical fixtures and reports remain immutable evidence of their own versions.

## Answering and context
The chatbot has 53 allowlisted ArcadeDB traversal endpoints (with 52 evaluation benchmark endpoints tested in `api/tests/fixtures/chatbot_evaluation_cases.json`, and `compound_methods` allowlisted), validated semantic paths, a KG-backed entity resolver, static in-process caching in `GraphRead.php` and `SemanticQuery.php`, a reviewed terminology catalogue and generic node-property answers. Enumerated results are deterministic KG retrieval. Optional Ollama parsing/introduction generation never supplies scientific ground truth or result entities. Node descriptions use stored node properties only. Structured conversation context is revalidated before fresh retrieval; prior assistant prose is not scientific evidence. JSON and SSE retain terminal response metadata and citations.

English-oriented intent routing, finite terminology coverage and bounded semantic paths remain limitations. The catalogue enriches 416 of 2,534 audited nodes; the remaining nodes still have canonical-name/fuzzy matching. Multi-hop planning is bounded to supported schema paths (maximum five hops), not arbitrary graph query execution. Unknown/ambiguous entities require clarification.

## Explorer and source scope
Integrated Explorer intentionally retains its existing category allow-list: Indication and Ayurvedic_Formulation are excluded. The audited graph contains 637 TREATS and 84 USED_IN_FORMULATION edges involving those categories. This previously requested coverage limit is preserved, not claimed as complete-KG display. Subgraph node/edge/depth caps remain intentional. Actual query errors must be surfaced, not silently converted into empty/partial success.

The statistics card measures distinct namespace-qualified source identifiers, not independently established studies. totalStudies remains a compatibility API key alongside totalReferences. The artificial 412 floor was removed. Identifier namespace verification is separate from publication existence, claim support and efficacy. Expert scientific validation remains unestablished; the explicitly excluded correction review remains out of scope.

## Configuration and local dependencies
OLLAMA_URL is the documented model URL; OLLAMA_BASE_URL remains a compatibility alias. Explicit in-process test overrides precede environment settings. Configure PHP through its process environment; Vite .env files do not configure a separately launched PHP server. Use VITE_API_BASE and VITE_CHAT_ENABLED for the frontend. Never commit credentials.

Fonts/assets are bundled locally. Evidence links navigate to namespace-specific PubMed, DOI or verified AYUSH record URLs; unclassified records do not receive invented publication links. Historical AYUSH search-only documentation is superseded by current verified record links and explicit fallback handling.

## Release gate
Run with the existing user-managed frontend/PHP/database/model services:

    python -B api/tests/run_release_checks.py --php <php executable> --output-dir <new directory>

The default covers all configured Playwright browsers. --browser chromium is explicitly limited scope, not full cross-browser completion. The runner never starts/stops servers. Build includes separate app/config TypeScript checks. It runs lint, build, all frontend unit tests, Python evaluation unit tests, the maintained PHP regression inventory, a new contract-8 benchmark and browser regressions. Nonzero suite exit, changed source hashes or missing benchmark artifacts prevent success. Older standalone scripts that manage their own servers are historical harnesses, not safe release commands; use the inventory in run_release_checks.py.

The benchmark defaults to contract 8 and exercises actual structured context plus 20-turn UI history. Supplemental suites cover resolution, terminology, node properties, result type, per-edge properties and Explorer/system consistency. Independent raw KG expectations remain authoritative; no LLM judges are substituted for them.

Source hashes cover frontend, core/backend and build configuration. Runtime details identify the local CLI environment; they do not cryptographically attest a remote PHP deployment or loaded model digest. Timings are sequential local end-to-end measurements, not concurrent-load or first-token performance. Suites overlap; their pass totals must not be added as independent samples.

Hosted CI runs frontend lint/type-check/build/unit tests. Complete live-KG CI is explicit workflow_dispatch on a protected preprovisioned self-hosted shodhanakg runner, with services and browsers already available. No hosted CI claim of KG verification is made. Source-document/domain-expert validation is outside an automated green release gate.

## Current progress
The [2026-10-09 pre-deployment audit](../audits/predeploy-20261009/summary.md) records the historical failing run. Subsequent fixes, current verification and remaining dependency findings are recorded separately in the [remediation report](../audits/predeploy-remediation-20261009/summary.md). The [September completion report](RELEASE_AUDIT_2026_09_26.md) describes an older test inventory, not the current release state. Scientific validation and the scope limits above remain open.

Sequential JSON/SSE requests may encounter different optional-model availability. Semantic parity compares all answers, routing/retrieval, evidence and structured state; model-attempt diagnostics are retained and reported separately. Unit transport tests independently require preservation of all terminal metadata.

On Windows, the release runner preloads `tests/support/webkit-pipe-cleanup.cjs` only for the browser job. Long WebKit runs left inherited stdio pipes open after the browser process exited, preventing worker shutdown. The safeguard waits for exit code 0 and a one-second drain period before closing remaining pipes; it never kills a running browser, ignores failed exits and other executables, and logs each intervention. Seven unit cases cover those boundaries. This uses Node's distinction between [process exit and closed stdio](https://nodejs.org/api/child_process.html#event-close). Direct Playwright commands do not enable this opt-in safeguard automatically.

Contract 8 retains all frozen KG references. Versioned software expectations in `api/tests/contracts/` recognize curated Shodhana FAQs and broad stored-node answers, checked against the raw snapshot. Earlier contract fixtures remain unchanged. The local build/test toolchain supports Node 22.12–22.x, 24.x, or 26+ (see package.json engines).
