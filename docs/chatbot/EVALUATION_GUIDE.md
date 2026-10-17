# Chatbot Evaluation Guide

**Fresh consolidated benchmark:** [Current benchmark v1](./CURRENT_BENCHMARK_v1.md)
(2026-09-24). Retrieval/completeness/edge checks pass, but the independent AYUSH
namespace check fails against current production outputs. Includes refusals and
response-time distributions; older reports below remain historical evidence.

Publication identity and source support: [Current publication reconciliation](./PUBLICATION_RECONCILIATION.md).
The KG represents 410 distinct study references; 412 is an API/UI floor.
Six AYUSH mappings correct the earlier PMID assumptions and withdraw both apparent
misattribution findings. Six preliminary source reviews remain; expert review is pending.

Updated 2026-09-23. Evaluate routing, retrieval, generation, and transport separately. A correctly shaped response or a high entity-mention score does not establish factual correctness.

Current conversation evaluation: [Realistic conversation results](./CONVERSATION_EVALUATION.md).
After the routing repair, the unchanged 34-scenario suite passes 54/54 turns over
both JSON and SSE (108/108 turn runs). The initial 41/54 baseline and its failures
remain preserved for regression history.

Current results-first answer evaluation: [Independent results evaluation](./RESULTS_EVALUATION.md).
Current legacy attribution: [Per-edge evidence coverage](./LEGACY_EVIDENCE.md).
All 40 legacy endpoints now retain exact supporting paths and complete raw edge
properties; 121 selected attribution cases pass independently against frozen records.
The initial 24-case run detected four incomplete legacy relationship presentations.
After the presentation fix, all 24 pass the unchanged independent evaluator. This
is separate from the routing/status and retrieval-reference suites.

Expanded endpoint facts: [52-endpoint factual coverage](./EXPANDED_FACTUAL_EVALUATION.md).
After rerunning the original 24 cases, the expansion adds multiple entities per
endpoint and graph-backed empty cases: 168 cases, all passing after the six endpoint fixes (`expanded_factual_report_v5.json`).
The older 24-of-52 coverage figures below describe the earlier reference phase.

## Tests and prerequisites

PHP 8.x and Python 3 are required for backend checks. Live integration uses an existing ArcadeDB database; HTTP tests additionally need the PHP API on port 8081. Ollama is only needed when generating successful natural-language responses. The offline tests below do not call Ollama or ArcadeDB.

```sh
php api/tests/test_traversal_expansion.php
php api/tests/test_evidence_correlator.php
php api/tests/test_evaluation_assertions.php
php api/tests/test_chat_transport.php
php api/tests/test_evaluation_export.php
python -m unittest discover -s api/tests -p test_evaluate_fidelity.py
```

The traversal tests use independently specified typed, direction-sensitive graph fixtures and assert complete paths, evidence identity, mismatches, missing identifiers, study separation, treatment phases, legacy projections, method-validation direction, and cycles. The emitter/client tests compare JSON and SSE status/context handling. They do not certify the semantic quality of generated prose.

## Live schema and coverage

```sh
php api/tests/build_traversal_inventory.php --verify-schema
php api/tests/run_evaluation_cases.php
php api/tests/test_disease_formulation.php
php api/tests/run_evaluation_cases.php --filter=eval_traversal_
php api/tests/run_evaluation_cases.php --filter=eval_coverage_
```

The checked-in inventory records 52 public retrieval endpoints: 40 retained and 12 additions. Four new provenance responses share traversal logic with legacy projections. Do not describe 52 public endpoints as 52 independent graph patterns. The inventory fails when a registered callable/routing fixture is missing, or a new typed edge pattern cannot be verified. Legacy paths also have explicit source-reviewed contracts in `fixtures/legacy_traversal_contracts.json`; these are not independently verified schema evidence.

The fixture now has 82 cases, preserving the original 55 IDs. Direct evaluation invokes the orchestrator and real retrieval. `--routing` tests the router only; use routing-specific expectations because routing success can legitimately precede retrieval no_data. `--fixture=PATH`, `--filter=PREFIX`, and `--dry-run` support focused diagnosis. Invalid fixtures and empty selections exit 2; assertion failures exit 1.

Cases may add `history`, `expected_entity_type`, `expected_entity`, `expected_reason`, `expected_results`, and `forbidden_results`. Result assertions are recursive subsets of individual returned rows: put the whole relationship/evidence tuple in one expected row so unrelated rows cannot satisfy different parts. Explicit null is an expectation; omission means no assertion. Keep expected facts independently reviewed rather than copying actual endpoint outputs.

## Independent factual reference suite

The first factual phase covers all 12 new traversal endpoints with **486 complete expected paths** and **12 selected forbidden cross-study paths**. It is separate from the 82-case routing/status suite; run both. All positive paths in the selected seed queries are asserted, including node identity/type/label, stored edge direction and raw properties, PMID/DOI/AYUSH identifiers, shared evidence and treatment phase. Exact result counts reject extra or duplicate paths. These references test fidelity to the captured graph, not whether its scientific claims are true.

```sh
python api/tests/build_factual_ground_truth.py
python -m unittest discover -s api/tests -p test_factual_ground_truth.py
php api/tests/test_evaluation_assertions.php
php api/tests/run_evaluation_cases.php --fixture=api/tests/fixtures/factual_evaluation_cases_v2.json
```

`factual_graph_snapshot.json` preserves raw records from 18 database tables. The Python reference builder uses separately specified directed joins and exact seed names; it never imports the production traversal catalog, entity resolver, evidence correlator, or endpoint outputs. Each factual case records the snapshot SHA-256, capture time, and `graph_verified_expert_review_pending` review status. Negative paths are actual connected paths whose references fail correlation, not fabricated entity names. Mutation tests verify that wrong relationships/references, duplicate rows and cross-study substitutions fail.

The default builder checks the committed reference fixture without modifying it. `--write` explicitly rebuilds candidate labels and must be followed by review; never regenerate references merely to silence a regression. Capture a prospective new database snapshot with `php api/tests/capture_factual_snapshot.php NEW_PATH.json` (existing files are refused), then inspect database changes before replacing the committed snapshot and labels. A live failure can represent database drift or a code regression; investigate the raw record IDs before changing expectations.

Full orchestration checks require retrieval for success/no_data. `--routing` deliberately omits retrieval assertions and is not a factual evaluation. No expert prose answers or entity labels are invented from these paths. The legacy extension below raises representative factual coverage to 24 of 52 entrypoints; 28 remain outside these frozen-reference suites. Expert scientific review remains pending. See the claim-review and answer-construction documents for subsequent answer-level work.

To export generated responses with these references intact, explicitly select the factual fixture and a separate output:

```sh
php api/tests/export_ragas_dataset.php --fixture=api/tests/fixtures/factual_evaluation_cases_v2.json --output=api/tests/fixtures/factual_answers.json
```

This invokes the live chatbot; current graph answers use deterministic evidence construction without a model call. Export is not needed for the retrieval factual suite. Export preserves reference paths and snapshot provenance even on HTTP/generation failures. The existing Python mention-recall scorer does not evaluate these relationship claims. Previously saved 55-row exports and CSV scores remain historical artifacts, not results from this phase.

## HTTP and browser validation

Run against existing services; do not automatically start, stop, or restart the user's npm server.

```sh
npx playwright test tests/traversal.spec.ts --config=playwright.external.config.ts --project=chromium
npx playwright test --config=playwright.external.config.ts --project chromium --grep "Route smoke|Mobile"
php api/tests/run_evaluation_cases.php --http --filter=eval_traversal_
```

`CHATBOT_TEST_API` overrides the Playwright API base, and `CHATBOT_EVAL_URL` overrides the PHP runner's full chat URL. HTTP runner mode compares orchestration expectations and final generation status separately; optional `expected_final_status` must be explicitly labeled for intentional failure tests. It must not hide a grounding_error behind a successful retrieval status.

The live HTTP traversal suite checks all 12 new entrypoints, validation errors, and JSON/SSE abstentions without requiring successful LLM generation. Generation-quality evaluation remains a separate step.

## Export and offline scoring

```sh
php api/tests/export_ragas_dataset.php
python api/tests/evaluate_fidelity.py
```

Exporting invokes the live chatbot for every case and overwrites the exported dataset. Run it deliberately when services are ready. Each row retains case ID, expected status/endpoint, actual final status, actual endpoint, raw retrieval, and orchestration status. Optional independently annotated `ground_truth_answer` and `expected_entities` are preserved.

The Python evaluator uses only the standard library. It counts every exported row, including failures. Older unlabeled exports remain unscored for abstention correctness. Grounding and infrastructure errors are failures, not correct refusals. Answerable questions refused by the system are false refusals. Context-derived entity mention recall includes method/media fields and parses each context separately; it is explicitly labeled a proxy. Expert-annotated expected entities are preferred.

Use `--input=PATH --output=PATH` to score a different export without overwriting saved results. Separate reliability totals from semantic scoring coverage.

## Limitations and interpretation

- The existing RAGAS script is optional legacy tooling; its dependencies are not installed by requirements-ragas.txt. It is not an acceptance gate for this expansion.
- Dataset labels and evidence snapshots must be versioned with the database. Historical reports and hard-coded entity counts may differ from the current graph.
- `test_disease_formulation.php` independently joins raw graph edges for two diseases and compares complete relationship/evidence tuples. Counts depend on the current database; it does not assume a historical no-data result.
- Current graph answers are constructed and checked before streaming begins; see [Answer construction](./ANSWER_CONSTRUCTION.md). Enriched legacy responses now retain per-edge paths; previously cached flat responses still have record-only attribution.
- The response validator remains heuristic. Neither identifier checks, schema validation, nor an LLM judge proves zero hallucinations.
- The mobile overflow checks currently fail on the existing navigation/tagline layout and existing controls. This is separate from traversal correctness.

## Answer-level claim review

See [Claim evaluation](./CLAIM_EVALUATION.md) for the independent relationship/citation/property scorer, review packet format, one-case live pilot, and limits on interpreting pending reviews.


## Legacy factual extension (2026-09-23)

`build_legacy_ground_truth.py` adds 12 representative legacy questions using the same unchanged frozen snapshot. It independently joins raw records, projects the documented flat response fields, and retains all supporting edge-ID paths in `expected_result_source_paths`. Labels cover 599 distinct rows and 18 forbidden projected rows. A rejected path that projects to an independently valid flat row is excluded from negative assertions, since the response cannot distinguish those paths.

```sh
python api/tests/build_legacy_ground_truth.py
python -m unittest discover -s api/tests -p "test_*ground_truth.py"
php api/tests/test_evaluation_assertions.php
php api/tests/run_evaluation_cases.php --fixture=api/tests/fixtures/legacy_factual_evaluation_cases_v2.json --http
```

Before the fix, nine cases passed and three failed: `formulation_therapy`, `process_purified_compound`, and `toxic_substance_method`. Their SQL selected only some citation fields and omitted `ref`, losing references and collapsing distinct evidence rows. They now read full raw edges and normalize identifier aliases before deduplication. The two inverse traversal labels now indicate reverse direction. Afterward all 12 cases passed through HTTP, without changing the snapshot or the new expected labels. Mutation checks reject changed citations, replacement with duplicate rows, and cross-study substitutions.

These tests assert complete expected legacy row sets and counts for their declared fields; the assertion engine permits additional object fields. They do not certify all seeds, scientific validity, source-document accuracy, or every rendered answer claim. Existing `ref` values include mixed identifier formats, including DOI-shaped values normalized into the historical `pubmed_id` field. That was the pre-migration contract; identifier version 2 below corrects classification for the chatbot. Flat projections also cannot prove per-edge attribution that the API does not expose. No question-specific answers or scientific facts were added to production code.


## Citation identifier version 2 (2026-09-24)

The shared classifier now places DOI-shaped `ref` values in `doi`, and numeric values in `pubmed_id`. It recognizes DOI resolver URLs, `doi:` prefixes, PMID URLs and `PMID:` prefixes, trims surrounding whitespace, and normalizes DOI case. DOI-shaped values in historical PubMed fields are also corrected. Unrecognized untyped values are retained as `unclassified_reference` and cannot establish a shared-study match. Explicit DOI fields retain their declared type (including historical unusual formats); this is classification, not online publication validation. Explicit fields take precedence over generic `ref` for the same identifier type. Conflicting PMIDs between two edges still reject a match even if their DOIs agree.

DOI shape recognition follows the prefix structure discussed by [Crossref](https://www.crossref.org/blog/dois-and-matching-regular-expressions/), with a non-whitespace suffix to avoid excluding older DOI punctuation. No resolver calls are made, and numeric shape alone does not verify a PubMed record exists.

The twelve flat endpoints that previously manually copied citation fields now read complete raw edges and call the shared classifier before deduplication. Canonical paths retain original raw properties; normalized evidence and generated answer attribution use the corrected identifier type. The evidence panel already renders these field names, so no frontend change is needed. Explorer-specific graph citation adapters outside the chatbot are not migrated in this phase.

Current builders check the new `factual_evaluation_cases_v2.json` and `legacy_factual_evaluation_cases_v2.json`. The original fixtures, raw snapshot, captured answers, and claim-review reports remain unchanged historical artifacts. New live exports should explicitly select a v2 fixture and a new output path. Migration checks verify identical source edge paths and counts: 486 canonical paths and 599 legacy rows, with 12 and 18 forbidden assertions respectively. In the canonical expected paths, 39 edge-evidence occurrences changed classification; source properties and topology did not change. The frozen snapshot contains 109 DOI-shaped `ref` records across all captured tables, not 109 unique publications.

Run `php api/tests/test_evidence_correlator.php` for classifier/matching regressions and the two builder checks plus `python -m unittest discover -s api/tests -p "test_*ground_truth.py"` for independent versioned-label checks. Historical claim reports must be interpreted against their original fixture, not silently rescored against v2 labels.

Validation for identifier v2: both 12-case factual HTTP suites and all 82 existing chatbot HTTP cases passed. Classifier, traversal (195 assertions), answer-construction, evaluation-assertion, and six independent Python tests passed. Lint reported zero errors and 19 existing warnings; build and 27 frontend tests passed. Chromium reported 11 passes and the nine existing 470px-versus-390px mobile overflow failures. The old `test_retrieval_core_integration.php` runner returned no usable JSON for eight cases: its `php -r` include does not satisfy the endpoints' direct-script guard, so it never invokes their response handlers. It remains an outdated harness, not a passing validation result; the HTTP suites exercise the running API.
