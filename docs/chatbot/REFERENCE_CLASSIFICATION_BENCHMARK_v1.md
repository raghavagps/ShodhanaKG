# Reference classification — release benchmark v1

Captured 2026-09-24, 14:04:10–14:07:28 UTC. Runtime source hashes were stable during
the benchmark; the fresh graph snapshot had no added, removed or changed records.

## Outcome

| Check | Result |
|---|---:|
| Independently audited reference mappings | 410/410 |
| PMID / AYUSH / DOI | 251 / 147 / 12 |
| Exact audited edge bindings | 10,123/10,123 |
| Fresh snapshot review-queue items | 0 |
| Factual cases across all 52 endpoints | 168/168 |
| Exact legacy attribution cases, 40 endpoints | 121/121 |
| Results-first presentation cases | 24/24 |
| Conversation runs, JSON and SSE | 108/108 |
| Routing/status cases | 82/82 |
| AYUSH identifiers incorrectly returned as PMID | 0 |
| JSON/SSE semantic mismatches | 0 |
| False refusals | 0/39 answerable turns per transport |
| Correct expected abstentions | 13/13 per transport |
| Frontend tests | 34/34 |
| Chromium reference-specific checks | 4/4 |
| Chromium route smoke checks | 10/10 |

The four live reference checks cover Explorer AYUSH, PMID and DOI responses,
plus opening a chatbot citation and checking its displayed namespace and link.
Namespace-aware filtering and graph exports use the same backend classifications.
The classifiers perform no registry calls at request time.

The 24 new answer result/grouping objects exactly match the previous benchmark:
entity labels, fact IDs, citation IDs and record indices are unchanged. Additional
PHP checks preserve 2,961 exact edge scopes across 95 positive legacy answers and
reject corrupted attribution. Fourteen Python evaluation/mutation tests pass.
Historical v2 fixture generators still reproduce their original expected records;
current evaluators use a separate reviewed-reference oracle and v3 fixtures.

## Local response time

| Measurement | Median | p95 |
|---|---:|---:|
| Results-first HTTP JSON | 360 ms | 688 ms |
| Conversation JSON | 250 ms | 766 ms |
| Conversation SSE | 265 ms | 765 ms |

Single-client local measurements, including complete response receipt. Conversation
timers also include local scoring. These are not first-token or load-test results.

## Remaining limitations

- Namespace verification is not scientific verification of KG claims. No expert
  approval is asserted by this benchmark.
- One DOI has source-backed namespace evidence but unresolved registry resolution.
- Build and lint pass; lint retains 19 warnings. Standalone TypeScript checking
  still reports unrelated existing type errors, including example components and
  graph DTO/Cytoscape typing. Vite build itself does not perform a full type check.
- The nine previously identified mobile horizontal-overflow checks still fail.
  All ten route smoke and all four reference checks pass.
- Untyped new edges, changed references and conflicting declarations require
  review. The empty current queue does not guarantee future imports are classified.

## Artifacts and reproducibility

- [Full benchmark](../../api/tests/fixtures/benchmark_reference_classification_v3/benchmark.json)
- [Reference audit](audits/identifier_rules_2026_09_24_v1/REPORT.md)
- [Classification contract and review workflow](REFERENCE_CLASSIFICATION.md)

The full benchmark is stored at
`api/tests/fixtures/benchmark_reference_classification_v3/benchmark.json` (runner
schema version 2). Earlier unsuccessful development captures remain separate.
After separating historical and current Python oracles, offline replays again
passed 24/24 presentation and 121/121 attribution cases; reports are
`reference_presentation_oracle_v3_replay.json` and
`reference_attribution_oracle_v3_replay.json` under `api/tests/fixtures`.

Reproduce with a new destination, using the user's already-running services:

```text
python api/tests/run_current_benchmark.py --php PATH_TO_PHP --output-dir NEW_DIRECTORY
npx playwright test --config playwright.external.config.ts --project chromium --grep "Reference classification|Route smoke|Mobile"
```

No KG records or historical audit fixtures were rewritten.
