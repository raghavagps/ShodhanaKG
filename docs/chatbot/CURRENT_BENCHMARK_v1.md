> Current implementation and release requirements: [CURRENT_SYSTEM_STATUS.md](CURRENT_SYSTEM_STATUS.md). Older counts, LLM requirements and link behavior below describe historical versions.

# Current chatbot benchmark — version 1

Captured **2026-09-24, 12:44:19–12:46:10 UTC** against the running local chatbot
and ArcadeDB. These are fresh captures, not rescored historical answers.
Production code and KG data were not changed. The benchmark's overall gate is
**not passed**, because current outputs still misclassify confirmed AYUSH IDs.

## Results

| Dimension | Fresh result | Meaning and limit |
|---|---|---|
| Factual accuracy against independent KG references | 168/168 cases pass across 52 endpoints | 132 positive and 36 empty cases; 2,108 expected rows/paths. Direct endpoint calls, not 168 natural-language answers |
| Results-first factual content and completeness | 24/24 live answers pass the existing reference contract | All 374 expected entity groups and 1,085 evidence records preserved; no missing, extra or duplicate results under these contracts |
| Exact legacy edge attribution | 121/121 cases across 40 legacy endpoints pass | Checks complete supporting paths, edge identities, direction and raw properties; includes 26 empty cases |
| Answer-to-citation mapping | 24/24 pass existing structural checks | 1,085 citations and 1,492 edge scopes; result/fact IDs, references, labels and exact supporting edges checked |
| Corrected publication namespaces | **Fail** | In the 24-answer capture, 12 answers contain 63 incorrectly PMID-labeled citation scopes covering 20 distinct edges |
| Realistic conversations | 108/108 turn runs pass | 34 scenarios, 54 turns repeated over JSON and SSE; no transport parity differences |
| Refusals and clarification | 13/13 expected abstentions correct per transport | Unsupported, ambiguous and unknown-entity cases; not all are medical refusals |
| False refusals | 0/39 answerable turns per transport | Also zero non-success responses for these answerable turns |
| Graph-backed empty answers | 2/2 turns per transport pass | Kept separate from unsupported-request refusals |
| Routing/status regression | 82/82 live HTTP cases pass | Does not substitute for factual checks |
| Scientific accuracy | **Not established** | Zero expert-approved claims; registry matches and faithful KG retrieval are not proof of scientific support |

Suites overlap. Do not sum their pass counts or treat the JSON/SSE repetitions as
108 independent questions. Passing a frozen graph contract does not imply that
every detail in the contract is scientifically or bibliographically correct.

## Remaining citation defect

The publication reconciliation corrected six identifiers in an **audit-only**
overlay. Production still classifies numeric `ref` values as PMIDs. The fresh
captures expose five confirmed AYUSH identifiers under PMID fields:
`13110`, `216736`, `2201`, `239250`, and `76448`.

The full scan finds 1,078 nested field occurrences: 183 in direct retrieval
outputs, 151 in the 24 answer captures, and 744 in conversation captures. These
include repeated copies in context, results and citations; **they are not 1,078
unique false claims**. The narrower citation-scope count above measures the
24-answer presentation sample. `216742` was not observed by this scan, which
does not establish that production classifies it correctly.

Existing reference fixtures preserve the older numeric-to-PMID assumption. They
were not rewritten to hide this discrepancy. The separate namespace check uses
the user's confirmed mappings, and causes the benchmark runner to exit 1 even
when all older regression suites pass. Correct edge mapping and correct source
namespace are reported separately. No unrelated-PubMed-paper mismatch is asserted.

## Response time

| Capture | Requests | Median | P95 | Maximum |
|---|---:|---:|---:|---:|
| Results-first HTTP JSON | 24 | 265 ms | 453 ms | 782 ms |
| Conversation HTTP JSON | 54 | 188 ms | 422 ms | 438 ms |
| Conversation HTTP SSE | 54 | 172 ms | 422 ms | 438 ms |
| Direct endpoint execution (not HTTP) | 168 | 16 ms | 225 ms | 1,123 ms |

Nearest-rank percentiles, one sequential client on localhost, no warmup exclusion.
All recorded cases contribute, including failures if present; no missing timing
values. Conversation timings mix successful, clarification, unsupported and empty
responses. Results-first HTTP timings measure request through full response receipt;
conversation timing also includes local scoring, so those rows are not a pure
network/server latency comparison. SSE timing is complete-answer time, **not time
to first token**. The 24 successful presentation responses report deterministic
generation; these numbers do not benchmark Llama generation. This is a single run,
not a production-load test, repeated-run latency estimate, or availability SLO.

## Reproducibility and scope

Artifacts: `api/tests/fixtures/benchmark_2026_09_24_v1/`.

- `benchmark.json`: consolidated metrics, commands, exit codes, timestamps, source,
  evaluator, fixture and artifact hashes; exact namespace findings with JSON paths.
- `snapshot.json`: fresh read-only capture of the 38 reference tables. No added,
  removed or changed records relative to the independent reference snapshot.
- `factual.json`, `attribution.json`: current direct retrieval and exact-edge checks.
- `answers.json`, `presentation.json`: current full HTTP answers and independent
  results-first evaluation, including per-answer latency and all issues.
- `conversation.json`: full current JSON/SSE responses, histories, expectations,
  issues, timings and parity checks. `routing.log` preserves all 82 routing results.

Production source fingerprints remained unchanged across the run. The manifest
fingerprints the local source files; it does not independently attest a remote
deployment version. No model judge, RAGAS score, production-generated expected
answer, or entity-mention proxy is used as factual ground truth. References remain
the independent frozen graph joins. Exhaustiveness is relative to each endpoint's
documented contract (including selected, rather than exhaustive, safety evidence).

```powershell
python api/tests/test_current_benchmark.py
python api/tests/run_current_benchmark.py --php C:\xampp\php\php.exe --output-dir api/tests/fixtures/benchmark_2026_09_24_v2
```

Use existing running services. The runner never starts/restarts them and refuses
an existing output directory. It reports failures rather than modifying reference
labels. Historical claim-review reports remain unchanged and should not be used
as current answer-quality scores.

Priority follow-up: apply validated namespace metadata to production citation
classification, then rerun the unchanged benchmark. Source-document and expert
review remain separate requirements for scientific accuracy. Browser rendering,
all entities per endpoint, concurrent load and generalization to unseen questions
remain outside this benchmark's coverage.
