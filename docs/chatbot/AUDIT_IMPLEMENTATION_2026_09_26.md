# Pre-benchmark audit implementation ? 2026-09-26

Active objective: implement the complete audit in order; do not claim final readiness until the consolidated regression gate passes. Preserve KG records, independent references and the explicitly retained Integrated Explorer exclusions.

## Baseline
- TypeScript: 34 diagnostics; build previously did not type-check.
- Chromium: 144 passed / 18 failed (9 mobile overflow; 9 obsolete selectors/fixtures).
- Frontend unit tests: 82 passed; lint: 0 errors / 19 warnings.
- Python evaluation modules: expanded ground truth, conversation evaluation and schema preservation failed.
- Existing saved contract-7 benchmark: 82 routing, 168 factual across 52 endpoints, 121 attribution, 24 presentation and 108 conversation transport executions passed. Not a fresh final benchmark.

## Implemented so far
- Added explicit app/config type-check to both build commands, without excluding examples/tests or relaxing the compiler.
- Aligned graph DTOs with optional node descriptions and extensible stored edge properties.
- Replaced ES2021-only string calls with equivalent ES2020 expressions.
- Validated search-tab URL input; unknown/legacy integrated tabs fall back to all.
- Made provenance examples accept real typed selection props instead of undefined variables/hardcoded disease IDs.
- Corrected frontend test DTOs and unsupported Testing Library options.
- Fixed independent oracle safety projection to preserve raw edge identity through the normalized edge record.
- Updated conversation evaluator unit-test response capture to the saved current contract and v4 independent reference fixture; adversarial mutations remain checked.
- Scoped schema preservation allowance to the previously user-approved diagnosis swatch/label; historical baseline archive unchanged.
- Corrected AGENTS build-workflow description.

## Remaining, in order
1. Finish verification of gate repairs and save results.
2. Fix statistics identifier handling/count semantics, category counting/sorting, malformed request handling, mobile overflow, chat recovery/timeouts, model configuration, client metadata parity and stale graph placeholder risk. Add regressions.
3. Repair obsolete browser tests without weakening assertions; make CI gates reproducible.
4. Consolidate structured context, terminology, node property, result contract, provenance and JSON/SSE tests; update benchmark defaults/manifests/runtime metadata. Preserve independent expected facts.
5. Reconcile current documentation and clearly state language/traversal/Explorer/publication-validation limitations.
6. Run fresh versioned contract-7 benchmark and complete frontend/backend/evaluation regression checks. Separate scientific validation from KG fidelity; no expert approval is implied.

No KG data or historical evaluation fixtures have been rewritten. User-managed server restart is required after the current edits/build under AGENTS.md; do not restart the server automatically.

## Second implementation pass
- Validated JSON request shape, question string/length, transport flag, context/selection containers and page offsets before routing. Invalid requests return HTTP 400 for both transports; invalid HTTP methods return 405.
- Added shared model URL configuration: documented OLLAMA_URL, legacy OLLAMA_BASE_URL alias, in-process test overrides. No model dependency added to factual retrieval.
- Replaced fixed study-count floor with paged live edge scanning and existing namespace classifier. Live result: 2,534 nodes / 10,123 edges / 410 distinct references. UI now labels references honestly and removes fabricated loading/error fallback counts.
- Separated Process and Shodhana_Method source types in four category count configurations; sort complete aggregates before pagination. Count failures propagate instead of displaying zero.
- Fixed mobile overflow at its causes (header shrinking/wrapping, search input minimum widths and long suggestion wrapping). All 19 Chromium route/mobile checks passed, including the nine previously failing routes.
- Added chatbot health recovery on focus/online events and periodic checks.
- Added test_audit_boundaries.php, test_statistics_http.py and HealthRecovery.test.tsx. Independent frozen raw edges verify category counts; tests cover namespace collisions/trailing zeros and HTTP validation.

Still pending: client timeout/cancellation and complete metadata parity, placeholder graph checks, graph partial-error handling, obsolete browser tests, CI/consolidated benchmark integration, documentation reconciliation and fresh final regression/benchmark.

## Third implementation pass
- JSON/SSE requests now carry abort signals and a 125-second end-to-end deadline; health requests have a five-second deadline. The client exposes caller cancellation. Terminal SSE payload metadata is retained, duplicate terminal events rejected, and incomplete reader streams cancelled.
- Disease/Integrated Explorer discard previous-query placeholder graph data, including evidence panels, while the next selection resolves.
- GraphRead filters absent legacy relationships through the live DB schema and propagates real edge query errors. Neighbor-fetch failures now also propagate to the graph error response.
- Added transport tests for full terminal metadata, duplicate completion, JSON/SSE/health timeout and cancellation; all ten transport tests pass. Added graph query failure assertions to test_audit_boundaries.php.
- Updated nine obsolete browser tests: actual entity RIDs, current node visibility controls, current reference actions, qualified ambiguous method, bridge-table entry point and real node/edge property inspection. Targeted reruns passed after comparing equivalent final property rows (which intentionally omit bottom borders).
- Historical unclassified citation captures are tested as unclassified, without fabricating PMID links. Live namespace links remain tested separately.

Remaining: connect cancellation to widget lifecycle/session robustness; explicit placeholder regression; eliminate vacuous browser skips/checks; consolidate CI and benchmark suites/current structured-context transport; reconcile documentation; run the complete fresh regression and contract-7 benchmark. No final-readiness claim yet.

## Fourth implementation pass
- Main conversation evaluator now sends the current 20-turn history policy and latest structured server context, records sent state, uses v4 factual references and compares context/clarification metadata across transports.
- All 108 conversation executions passed factual/status checks. A diagnostic parity mismatch exposed an unnecessary model call on an unresolved pronoun; missing referents now deterministically request clarification, with a PHP regression.
- Benchmark now defaults to contract 7, fingerprints all frontend/core/backend/build and test sources, records local runtime scope, and includes seven modern evaluation suites plus all-edge/system/Explorer consistency.
- Added run_release_checks.py combining lint/build/frontend/Python/maintained PHP tests, benchmark and all-browser regressions using existing servers only. Hosted CI checks frontend; explicitly dispatched protected self-hosted CI runs the KG gate.
- Chatbot reset/unmount cancels in-flight requests; late answers cannot contaminate a new conversation. Session-storage failures no longer crash the widget. Added cancellation/reset regression.
- Removed vacuous browser skip/no-crash fallbacks and replaced external Playwright demo tests with a local landing test. Strict search/filter/table checks now assert current real results.
- Added delayed-response browser tests proving Disease and Integrated graphs clear previous-query nodes while loading (2/2 passed).
- Full Python discovery exposed one additional legacy unit fixture missing its synthetic edge ID; fixed that fixture without changing historical KG data or expected scientific answers.
- Added CURRENT_SYSTEM_STATUS.md as authoritative architecture, scope, configuration and gate documentation; dated reports stay historical.

Next: execute the full release gate, repair any failures, reconcile remaining stale AGENTS/docs wording, then produce a requirement-by-requirement final report. Keep the goal active until fresh consolidated checks pass.

## First consolidated run (diagnostic, not release approval)
Artifact directory: api/tests/fixtures/release_audit_2026_09_26_v1.
Confirmed passes so far: lint/build/type-check, 88 frontend unit tests, 71 Python evaluation tests, all 14 maintained PHP scripts, 168 factual, 121 attribution, 24 presentation, 82 routing and 30 structured-context checks. All 108 conversation factual/status runs passed, but the original parity check failed because two independent optional-model attempts had different availability/rejection diagnostics on the unknown-entity case. Answers, evidence and state were identical.

Fixed evaluator parity scope: retain complete raw responses and separately record optional-model attempt diagnostics; semantic parity still compares answers, routing, retrieval, evidence/citations and structured context. Added an adversarial unit test ensuring changed facts/routing/reasons/state still fail. Nine conversation-evaluator unit tests now pass. This edit changes the recorded source fingerprint, so v1 is explicitly diagnostic and a fresh v2 full gate is required after remaining suites finish. The live v1 run is still collecting supplemental/browser results; do not interrupt/restart it based only on an observation timeout.

Diagnostic run disposition: Chromium completed all 163 cases. Firefox failed at page creation inside the execution sandbox; the same blank-page check passed with elevated execution. WebKit reached case 458/489 overall, then browser processes exited and worker processes stopped advancing for over an hour. Both application services still returned HTTP 200. Only the verified Playwright runner/workers were terminated; no application server was managed. v1 ended with browser exit 1 and remains a failed diagnostic artifact. This was not treated as a passed cross-browser suite.

Before v2: benchmark summaries now retain supplemental counts from flat reports, missing supplemental files fail the gate, source hashes include the underlying Playwright/Vitest/lint/CI configuration, and Hindi statistics wording also distinguishes references from studies. v2 will run outside the restrictive sandbox using unchanged sources. Scientific validation and the documented traversal/Explorer limitations remain open.

## Second consolidated run and Windows test teardown
v2 passed the complete backend benchmark, 73 Python tests, 88 frontend unit tests, all maintained PHP scripts, lint/type-check/build, and all 489 browser assertions. However, its browser command exited 1 because WebKit workers did not exit within 300 seconds. Therefore v2 is **not** a successful release gate. Sources were unchanged during that run; KG drift remained empty.

An isolated WebKit schema test exited cleanly. A full diagnostic WebKit run reproduced the shutdown wait. Browser diagnostics stopped at graceful-close start after the browser processes had exited; Node inspector showed seven PipeWrap resources, only pipe/socket handles and no active requests/file operations. Added an opt-in Windows browser-job preload to release inherited stdio only after a confirmed clean WebKit exit and a one-second drain period. It leaves failed exits, live processes and other executables untouched. Seven unit tests passed; lint/build passed. A fresh v3 gate is required for this final harness source revision. No application assertions or production behavior were altered by this safeguard.

## Completion
`release_audit_2026_09_26_v3/release.json` records all 20 stages passing, `complete: true`, `passed: true`, unchanged source hashes and all-browser scope. All 489 browser cases passed, both WebKit clean-exit pipe releases were logged, and the command exited 0 without non-test errors. Frontend/harness unit tests: 95/95; Python: 73/73; maintained PHP scripts: 14/14; complete contract-7 benchmark passed with no KG drift. See [RELEASE_AUDIT_2026_09_26.md](RELEASE_AUDIT_2026_09_26.md) for the final report and remaining scientific, coverage, performance and deployment limitations. Earlier pending statements in this chronological record are superseded by this completion entry.
