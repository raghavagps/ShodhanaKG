# Legacy per-edge attribution

The 40 legacy endpoints retain their existing flat results and now return
`evidence_paths`: one entry per `result_index`, with every distinct accepted
supporting path. Each path retains typed/labeled node IDs and ordered edge IDs,
stored source/target direction, complete raw edge properties, and normalized
citation identifiers. No evidence is reconstructed by matching answer text.

Legacy traversals record the exact edge variables after their existing correlation
checks, including paths grouped into one flat row. A request-local `LegacyEvidence`
collector loads full records by validated RID and caches each lookup. The four
legacy projection adapters and method-validation endpoint preserve the canonical
paths they already retrieve. Missing records, changed citation identifiers,
disconnected paths, invalid schema direction, conflicting identity, or a path that
does not support its mapped result are rejected instead of presented as verified.

Partial inverse paths remain partial; upstream evidence is not invented.
`compound_validation` attributes its selected safety record as the final edge,
without changing its existing one-safety-record selection contract.
`compound_therapeutic_use` explicitly marks its independently cited final TREATS
edge as a `cross_study_association`; it does not claim one study supports the
whole purification/indication chain. Other paths retain pairwise shared-reference
checks. These checks establish graph attribution, not clinical efficacy.

The results-first presenter keeps result, fact, and citation IDs stable. Each
legacy citation now has `per_edge` attribution and a scope for each distinct
supporting edge. Semantic labels use the actual stored source and target types.
The source card shows each edge's own identifiers and properties, rather than a
merged property bag. Existing frontend behavior hides empty values and technical
IDs while preserving Technical details and access to every result. Previously
cached flat responses still render with explicitly limited record attribution.

## Independent checks

The existing frozen snapshot and expected factual answers were not changed.
`evaluate_legacy_evidence.py` uses their independently generated source-edge paths
and raw records to verify complete per-row path sets, typed nodes, stored edge
direction, complete properties, citation identifiers and selected safety evidence.
It does not import the production collector or use live output as expected truth.
The presentation evaluator now also checks enriched legacy scopes against its
independently joined raw paths; historical record-only captures remain supported.

- Before this change, the baseline report detects missing attribution envelopes
  in all 121 selected legacy cases (95 positive, 26 empty-result contracts).
- All 121 cases across all 40 legacy endpoints pass the new attribution checks.
- All 168 factual cases across all 52 endpoints still pass.
- All 24 live results-first presentation cases pass, including their unchanged
  result grouping and fact/citation mappings.
- Offline presentation checks cover 95 positive legacy answers and 2,961 edge
  scopes. Mutation tests reject deleted paths/properties, reversed edges, changed
  IDs/types, duplicate paths, substituted support and missing selected safety edges.

Artifacts: `legacy_evidence_baseline_v1.json`, `legacy_evidence_answers_v3.json`,
`legacy_evidence_report_v3.json`, `results_legacy_evidence_answers_v1.json`, and
`results_legacy_evidence_report_v1.json` under `api/tests/fixtures/`. Earlier v1/v2
captures remain for audit.

```powershell
php api/tests/run_expanded_factual.php api/tests/fixtures/expanded_factual_cases_v2.json NEW_ANSWERS.json
python api/tests/evaluate_legacy_evidence.py --answers NEW_ANSWERS.json --output NEW_REPORT.json
python -m unittest discover -s api/tests -p test_legacy_evidence.py
php api/tests/test_legacy_evidence_presentation.php
npx playwright test tests/legacy-evidence.spec.ts --config playwright.external.config.ts --project chromium
```

Coverage remains selected seeds, not every possible entity or property combination.
The schema metadata must be reviewed when directed type pairs change. Record
hydration uses separate read queries, not a transactional snapshot; detected
reference drift fails closed. Local measured median endpoint time rose from 3 ms
to 8.5 ms (maximum 342 ms to 834 ms) in the saved pre-fix/v2 runs; these are local
measurements, not a production latency guarantee. No model or hardcoded scientific
answer is involved.
