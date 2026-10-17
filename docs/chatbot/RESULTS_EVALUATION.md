# Independent evaluation of results-first answers

This phase evaluates current structured answers against the existing independent
version-2 graph references. It changes evaluation tooling only, not chatbot code,
the database, or the reference labels. It does not use the captured Psoriasis
response as ground truth.

## Method

`api/tests/evaluate_results_presentation.py` captures all 24 reference questions
through the live JSON API, preserving the complete response, failures, timings,
and snapshot/fixture hashes. Existing output paths are refused. Its offline scorer
loads the canonical frozen references directly and reconstructs legacy source paths
using the independent Python joins over the frozen raw snapshot. Production PHP
rendering code and the production relationship-label catalog are never imported.

Live retrieval rows are matched to independent reference rows without assuming
their order. The scorer then checks:

- Exactly one result per expected target identity, with no missing or extra groups.
- Every evidence row appears once, and every citation is linked once to the right
  result, record index, and fact ID.
- Canonical citation edge IDs, stored direction, endpoint labels, publication IDs,
  and complete raw property values match the same independent edge.
- Legacy properties/references match the independently projected record. No exact
  edge identity may be fabricated for these flattened responses.
- Human-readable relationship chains agree with the independently joined paths.
- The heading/introduction retain the current neutral template, and plain text
  agrees with the structured list without extra generated claims.

This is explicit contract validation, not an automatic semantic judge of arbitrary
prose. The template check must be reviewed if the introduction contract changes.
The score does not establish publication validity or scientific truth. Legacy
properties already omitted by the endpoint contract remain outside the per-edge
score. Browser tests separately check visible list completeness and citation focus;
they do not upgrade record-level evidence to per-edge support.

## Initial capture and findings (before the relationship fix)

- `results_evaluation_answers_v1.json`: fresh live responses for all 24 questions.
- `results_evaluation_report_v2.json`: current report; v1 is retained as the initial
  report before adding independently derived entity expectations for browser checks
  and strict property-value type comparison.
- **20/24 answers pass every structured-answer check.** All 12 canonical cases pass;
  8 of 12 legacy cases pass.
- All 24 preserve the expected **374 result groups and 1,085 evidence records** in
  aggregate. These are totals across questions, not globally unique entities or
  publications. No omissions, invented result labels, reference substitutions, or
  property mismatches were found in these selected responses.
- Four legacy cases fail relationship-chain presentation checks. HTTP success alone
  would miss all four failures.

| Question/endpoint | Evidence records affected | Missing relationship |
| --- | ---: | --- |
| Copper complete shodhana process / `compound_shodhana_process` | 70 | Shodhana Method → Media |
| Compounds using Cow Milk / `media_compound` | 52 | Shodhana Method → Media |
| Formulations associated with Alopecia / `disease_formulation` | 36 | Disease → Therapy |
| Diseases associated with Laghu Sutshekhar / `formulation_disease` | 2 | Disease → Therapy |

The current renderer cannot resolve the legacy placeholders `Method/Process` and
`[PRE/POST]_TREATED_BY` into full row-specific relationship chains. It omits a hop,
then presents the remaining hop as a single scope relationship. The affected data
remain present; the visible relationship description is incomplete and can be
misleading. There are 160 affected cards and 320 diagnostic findings (two checks per
card), not 320 independent factual errors. Production fixes are a separate next step.

The evaluator's hand-authored positive controls include an inverse traversal,
several studies for the same entity, legacy record attribution, reordered rows, and
zero/false properties. Negative controls deliberately omit/duplicate/invent results,
change pointers and edge directions, swap studies/properties, add unsupported prose,
and alter both retrieval and the card together. The frozen reference still rejects
that self-consistent but wrong response.

## Reproduce

```powershell
python -m unittest discover -s api/tests -p test_results_evaluation.py
python api/tests/evaluate_results_presentation.py --answers api/tests/fixtures/results_evaluation_answers_v1.json --output NEW_REPORT.json
python api/tests/evaluate_results_presentation.py --capture --answers NEW_ANSWERS.json --output NEW_LIVE_REPORT.json
npx playwright test tests/results-evaluation.spec.ts --config playwright.external.config.ts --project chromium
```

The current scorer deliberately exits **1** for the four confirmed failures.
Do not change reference labels or weaken assertions to make it pass. A new capture
after a presentation fix should retain these artifacts and use new output paths.
Reports hash the evaluated capture and evaluator. The server does not report its
deployed source version; that remains an explicit reproducibility limitation.

Coverage remains 24 selected questions, not all seeds or all 52 endpoints. Historical
LLM claim-review reports remain historical; this result applies to deterministic
results-first answers only.

## Relationship-label fix and unchanged evaluation

The renderer now resolves union types through the connected schema path and expands
optional edge-prefix families using schema-known types and the record's treatment
phase. Labels retain the stored edge direction, including reverse traversal. A
multi-hop record no longer receives a single-edge scope label simply because only
one hop could be resolved. Unresolved ambiguity is still omitted rather than guessed.

The fresh `results_evaluation_answers_v2.json` capture passes **24/24** cases in
`results_evaluation_report_v3.json`, using the unchanged independent evaluator,
snapshot, and reference fixtures. All 374 result groups and 1,085 evidence records
are preserved. All 160 previously affected cards now have complete relationship
chains. This changes presentation only; legacy attribution remains record-level.
The earlier failing captures/reports remain intact. To reproduce the passing score,
run the scorer with `--answers api/tests/fixtures/results_evaluation_answers_v2.json`
and a new report path. Browser tests use the new capture and the original independent
entity expectations, with additional assertions for the four visible chains.
