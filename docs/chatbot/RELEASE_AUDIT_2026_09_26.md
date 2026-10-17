# Audit implementation and release verification — 2026-09-26

## Outcome

The current engineering release gate passed all 20 stages with unchanged source hashes during the run. This completes the pre-benchmark audit implementation. It does **not** establish scientific/clinical validity or final paper readiness.

- [Release manifest](../../api/tests/fixtures/release_audit_2026_09_26_v3/release.json): `complete: true`, `passed: true`, `source_changed: false`, all-browser scope.
- [Fresh contract-7 benchmark](../../api/tests/fixtures/release_audit_2026_09_26_v3/benchmark/benchmark.json).
- [Browser log](../../api/tests/fixtures/release_audit_2026_09_26_v3/browser.log): 489 passed, command exit 0, no skipped cases or non-test errors.
- All per-suite logs, captured responses, independent comparisons and fingerprints are in `api/tests/fixtures/release_audit_2026_09_26_v3/`.

No KG facts, edge identities/directions, publication identities or historical scientific reference fixtures were changed. Snapshot comparison found no added, removed or changed records. No application server was stopped or restarted by the agent.

## Root causes and fixes

| Finding | Classification | Resolution |
|---|---|---|
| Vite bundling was mistaken for TypeScript validation; 34 diagnostics were hidden by the build command | Confirmed bug | Added explicit app/config type checks before both builds; repaired DTOs, examples, fixtures and ES2020 compatibility without relaxing compiler settings |
| Independent safety-path oracle discarded edge identity; evaluation tests used outdated response/schema contracts | Confirmed evaluation bug | Preserve normalized raw edge records; update test captures and the narrowly approved swatch exception, retaining adversarial checks and historical references |
| Study statistics imposed a 412 floor and damaged identifier distinctions | Confirmed bug | Page live edge records; count namespace-qualified identifiers without stripping significant zeros; label the result Distinct References in English and Hindi; remove fabricated UI fallbacks |
| Category counts conflated Process and Shodhana_Method and sorted/paginated incomplete aggregates | Confirmed bug | Filter the correct source types, scan all relevant records, sort globally before pagination, propagate failures |
| Malformed request shapes could reach routing and produce uncontrolled errors | Confirmed bug | Validate question, history, context, selection, stream and offset at the request boundary; controlled 400/405 responses |
| Documented model URL setting and implementation differed | Confirmed configuration bug | Shared OLLAMA_URL configuration, legacy alias support and deterministic override precedence |
| Fixed minimum widths and unwrapped suggestions overflowed mobile routes | Confirmed UI bug | Correct shrinking, wrapping and input widths; all mobile browser cases pass |
| Initial health failure persisted without recovery; requests lacked complete cancellation/deadline handling | Confirmed lifecycle bug | Recheck on focus/online/interval, bounded health/JSON/SSE waits, caller cancellation, cancellation on reset/unmount and rejection of late responses |
| SSE reconstruction dropped terminal metadata and could accept duplicate completion | Confirmed transport bug | Preserve complete terminal payload, reject duplicate/incomplete completion, test metadata parity and cancellation |
| Previous-query placeholder graphs could appear under a new selected entity | Confirmed integration bug | Suppress stale placeholder graph/evidence until the current query resolves; delayed-response browser regressions for Disease and Integrated views |
| Graph query failures could be silently returned as partial success | Confirmed backend bug | Filter absent legacy types through live schema; propagate real edge/neighbor query failures |
| Obsolete selectors/RIDs and skip/no-crash fallbacks weakened browser checks | Confirmed test defect | Use actual KG identities and current controls; require concrete graph, property, search and table results; replace external demo test with a local landing test |
| Main benchmark omitted newer capabilities and did not reproduce current structured-state/history behavior | Confirmed coverage gap | Contract 7 by default; UI-equivalent history/state; resolution, terminology, node properties, method results and system consistency in one gate; complete artifacts and source fingerprints |
| Unknown pronouns without a referent unnecessarily reached optional model parsing | Confirmed routing defect | Deterministic missing-context clarification before model fallback; regression added |
| Independent optional-model attempts produced differing availability/rejection diagnostics, falsely failing semantic parity | Confirmed evaluator defect | Compare semantic outputs/state strictly, retain raw diagnostics separately; adversarial tests still reject changed routing, facts, reasons or state |
| Flat supplemental report summaries lost their counts; missing supplemental artifacts were not all enforced | Confirmed reporting defect | Preserve both summary formats and require every supplemental artifact |
| Long Windows WebKit runs passed assertions but left inherited stdio pipes open after browser exit | Confirmed test-environment defect | Opt-in Windows browser-job preload releases pipes only after exit code 0 and a one-second drain period; seven boundary tests; no failed exit or live process is suppressed |
| Older documentation and CI did not describe the current release contract | Confirmed documentation/workflow gap | Current authoritative status guide, historical-report notices, hosted frontend checks and an explicit protected self-hosted live-KG release job |

The WebKit diagnosis used browser shutdown logs and a worker inspector snapshot: seven PipeWrap resources, pipe/socket handles, no active file requests, and browser processes already exited. Node documents why [process exit and closed stdio are different events](https://nodejs.org/api/child_process.html#event-close). The safeguard is local test tooling; it is not bundled into the application and adds no dependency. v3 logged two clean-exit pipe releases and then exited normally.

## Regression results

| Suite/dimension | Final result | Scope |
|---|---:|---|
| Lint | 0 errors, 19 existing warnings | Warnings remain visible |
| Type-check and production build | Pass | App and build/config TypeScript, then Vite |
| Frontend and harness unit tests | 95/95, 25 files | Includes seven Windows teardown boundary cases |
| Python evaluation unit tests | 73/73 | Includes independent-oracle and adversarial evaluator checks |
| Maintained PHP regression scripts | 14/14 scripts | Request, config, graph failures, routing, properties, references, validation and presentation |
| Factual retrieval | 168/168 | 52 endpoint contracts; direct retrieval, not 168 natural-language questions |
| Exact attribution | 121/121 | 40 legacy endpoints; edge/path IDs, direction and properties |
| Results-first presentation | 24/24 | Fresh live answers against independent references |
| Conversation executions | 108/108 | 34 scenarios, 54 turns repeated over JSON/SSE |
| Semantic JSON/SSE parity | 0 failures | Raw optional-model diagnostics retained separately |
| Routing/status | 82/82, 0 skipped | Does not replace factual checks |
| Structured context | 30/30 | Fresh retrieval with validated context |
| Entity resolution | 32/32 | Typo, broad-term, ambiguous and typed resolution cases |
| Resolution with conversation context | 23/23 | Follow-ups and topic changes |
| Generic node properties | 23/23 | Stored properties only; missing information remains missing |
| Terminology | 39/39 | Reviewed terms and KG semantics |
| Method result types | 45/45 | Requested methods versus compounds/products |
| Fragment method questions | 49/49 | Equivalent abbreviated natural-language requests |
| System consistency | 242/242 | Includes 10,123 stored edges and 1,155 treatment-phase edges, provenance and integrated paths |
| Browser regressions | 489/489 | 163 each in Chromium, Firefox and WebKit; command exits 0 |
| Refusals, per transport | 0/39 false refusals; 13/13 correct abstentions | Also 0 false acceptances and 0 infrastructure/generation errors in this sample |
| Known namespace errors | 0 occurrences | Scan against all 147 audited AYUSH values; not scientific validation |
| KG drift | 0 added / 0 removed / 0 changed | Compared with the independent raw snapshot |

Suites overlap. Do not sum their pass counts as independent scientific samples. A green finite regression suite is not proof that every possible question is covered.

## Before/after

| Dimension | Baseline | Final |
|---|---|---|
| TypeScript gate | 34 diagnostics; build did not check types | 0 diagnostics; builds require type-check success |
| Chromium browser suite | 144 passed / 18 failed | 163 passed / 0 failed |
| Mobile overflow | Nine failing routes in Chromium | All mobile cases pass in all three browsers |
| Frontend unit tests | 82 passed | 95 passed |
| Evaluation units | Three initially failing modules; another legacy fixture defect found during full discovery | 73 passed |
| Statistics | Artificial 412-study floor and identifier-count defects | 410 distinct namespace-qualified references, explicitly not a verified study count |
| Current benchmark integration | Saved earlier core suites; newer capabilities not consolidated | Fresh contract-7 gate with all supplemental suites and unchanged-source checks |
| Cross-browser release | No complete passing current gate | 489 assertions pass and browser command exits 0 |

Core factual counts already passed in the saved earlier contract-7 report; this work freshly reconfirms them rather than claiming an invented accuracy increase.

### Current local response time

| Capture | n | Median | P95 | Maximum |
|---|---:|---:|---:|---:|
| Direct endpoint execution, not HTTP | 168 | 15 ms | 227 ms | 1,397 ms |
| Results-first HTTP JSON | 24 | 594 ms | 1,016 ms | 1,046 ms |
| Conversation HTTP JSON | 54 | 719 ms | 1,234 ms | 10,454 ms |
| Conversation HTTP SSE | 54 | 718 ms | 1,203 ms | 2,531 ms |

These are nearest-rank, sequential localhost complete-response measurements. They include optional-model delays and are not first-token latency, an availability SLO, a concurrent-load test or a controlled performance before/after comparison. v2 and v3 showed machine/run variability despite unchanged application behavior; no speedup claim is made.

## New/expanded regression coverage in this audit

- `test_audit_boundaries.php`: malformed JSON/request types, length/offset boundaries, configuration precedence, namespace collisions, significant trailing zeros, real counts and propagated database failures.
- `test_statistics_http.py`: independent category-count expectations, global pagination and malformed requests in both transports.
- `HealthRecovery.test.tsx`: recovery after an initial backend outage.
- `ConversationContext.test.tsx`: reset cancellation and exclusion of late responses.
- `chatApi.stream.test.ts`: full terminal metadata, duplicate completion, deadlines and cancellation.
- `graph-placeholder.spec.ts`: stale graph exclusion under delayed Disease and Integrated requests, exercised in all browsers.
- `test_structured_context_v2.php`: unresolved referents clarify deterministically.
- `test_conversation_evaluation.py`: model diagnostics versus strict semantic parity, including adversarial changes.
- `test_current_benchmark.py`: flat and nested supplemental summaries.
- `webkit-pipe-cleanup.test.ts`: seven cases for clean exit/drain delay, nonzero/signal exits, normal closure, destroyed streams, other platforms and unrelated executables.
- Existing browser suites now assert current entity IDs, properties, search/filter results and namespace-specific actions instead of skipping or merely checking for absence of a crash.

## Files changed

Application/backend:

```text
api/category-stats.php
api/chatbot_response.php
api/config.php
api/graph.php
api/kg-stats.php
api/core/ChatRequest.php
api/core/GraphRead.php
api/core/KgStatistics.php
api/core/LLMClient.php
api/core/ModelConfig.php
api/core/QuestionRouter.php
src/components/chatbot/ChatbotWidget.tsx
src/components/chatbot/ChatMessage.tsx
src/components/chatbot/ChatSuggestions.tsx
src/components/explorer/GraphCanvas.tsx
src/components/explorer/ProvenancePanel.example.tsx
src/components/layout/TopNav.tsx
src/data/translations/uiTranslations.ts
src/lib/chatApi.ts
src/lib/types.ts
src/pages/ModeSelection.tsx
src/pages/SearchResults.tsx
src/pages/explorers/CompoundExplorer.tsx
src/pages/explorers/DiseaseExplorer.tsx
src/pages/explorers/DiseaseGraphView.tsx
src/pages/explorers/IntegratedGraphView.tsx
```

Tests, evaluation and build/CI:

```text
package.json
.github/workflows/playwright.yml
api/tests/build_expanded_ground_truth.py
api/tests/evaluate_conversations.py
api/tests/evaluate_fragment_methods.py
api/tests/run_current_benchmark.py
api/tests/run_release_checks.py
api/tests/test_audit_boundaries.php
api/tests/test_conversation_evaluation.py
api/tests/test_current_benchmark.py
api/tests/test_legacy_factual_ground_truth.py
api/tests/test_schema_reference.py
api/tests/test_statistics_http.py
api/tests/test_structured_context_v2.php
src/components/chatbot/Chatbot.test.tsx
src/components/chatbot/ConversationContext.test.tsx
src/components/chatbot/HealthRecovery.test.tsx
src/components/chatbot/ResultsMessage.test.tsx
src/lib/chatApi.stream.test.ts
src/test/webkit-pipe-cleanup.test.ts
tests/chatbot-evidence.spec.ts
tests/compound-interaction.spec.ts
tests/conversation-evaluation.spec.ts
tests/example.spec.ts
tests/graph-placeholder.spec.ts
tests/reference-classification.spec.ts
tests/shodhanakg.spec.ts
tests/support/webkit-pipe-cleanup.cjs
```

Documentation:

```text
AGENTS.md
BACKEND_DOCUMENTATION.md
FRONTEND_DOCUMENTATION.md
docs/chatbot/AUDIT_IMPLEMENTATION_2026_09_26.md
docs/chatbot/CURRENT_BENCHMARK_v1.md
docs/chatbot/CURRENT_SYSTEM_STATUS.md
docs/chatbot/README.md
docs/chatbot/REFERENCE_CLASSIFICATION.md
docs/chatbot/RELEASE_AUDIT_2026_09_26.md
```

Generated output is separate: `dist/`, runtime `debug.log`, browser test artifacts and the versioned `release_audit_2026_09_26_v1`, `v2`, `v3` directories. Release manifests and logs enumerate commands and source hashes; benchmark manifests also fingerprint output/reference files. The v1 and v2 failures remain preserved and are not presented as release approval.

## What remains incomplete or intentionally limited

1. **Scientific validation remains incomplete.** Publication/source-document support review is partial and zero claims have domain-expert approval in this benchmark. Correct retrieval and identifier namespaces do not establish claim support or efficacy. The previously excluded correction review remains out of scope.
2. **Language and query coverage are bounded.** Routing is English-oriented; the reviewed terminology catalogue enriches 416 of 2,534 audited nodes, with canonical/fuzzy matching for others. Traversal is limited to supported schema paths, at most five hops. Further held-out entities and natural-language questions would broaden evidence beyond the current tests.
3. **Integrated Explorer coverage remains intentionally restricted**, as requested: Indication and Ayurvedic_Formulation are excluded by its allow-list. The associated audited coverage excludes 637 TREATS and 84 USED_IN_FORMULATION edges. Node/edge/depth display caps also remain intentional.
4. **Performance validation is local and sequential.** Concurrent-load tests, first-token timings, repeated controlled measurements and production deployment performance are not established. Optional model fallback can add several seconds despite a correct abstention.
5. **Live CI needs infrastructure.** The workflow is configured, but a protected self-hosted runner with the database, model, services and browsers must be provisioned. This local pass is not evidence that hosted/live CI ran. Runtime metadata does not cryptographically attest a remote PHP deployment or model digest.
6. **Maintenance warnings remain:** 19 lint warnings, a production chunk above 500 kB and old Browserslist metadata. No new dependency was installed to hide these warnings. Older documents are retained as historical and point to the current status guide.
7. **Windows browser tooling has a documented safeguard.** The release runner enables it only for its browser subprocess. Direct Playwright commands without that opt-in may still encounter the observed WebKit shutdown behavior on this machine.

The implementation is ready for further controlled benchmark work under these stated limits. Do not describe it as scientifically validated or ready for final paper evaluation on the strength of this automated pass alone.
