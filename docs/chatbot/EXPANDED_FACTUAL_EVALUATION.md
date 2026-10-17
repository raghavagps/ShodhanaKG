# Expanded factual endpoint coverage

## Baseline rerun before expansion

Both original version-2 independent fixtures were rerun through the live HTTP
chatbot: **12/12 canonical and 12/12 legacy cases passed**. Their independent
builders and six reference unit tests passed. A fresh results-first capture also
passed **24/24**, saved as `results_evaluation_answers_pre_expansion.json` and
`results_evaluation_report_pre_expansion.json`. Existing reference labels were
not regenerated or replaced.

## Expanded reference and method

`factual_graph_snapshot_expanded.json` adds the tables needed for the remaining
28 endpoints: 38 raw tables in total. Capture issues read-only database queries,
checks row counts before and after each table read, and refuses to overwrite an
existing snapshot. All 18 original tables are byte-value equivalent after sorting
by record ID. The capture is not a cross-table transactional snapshot; a changing
database still requires review.

`build_expanded_ground_truth.py` specifies typed, directed joins and flat response
projections independently in Python. It imports only the existing independent
reference helpers, never production retrieval/catalog/normalization code or live
endpoint output. Canonical paths retain node/edge identities, direction, properties
and citation identifiers. Flat reference rows retain supporting raw edge-ID paths
for audit. Negatives are connected but uncorrelated paths, excluding projections
that also have a valid independent source.

Seed selection is deterministic from the raw snapshot: retain the earlier seed
where applicable, select two additional positive entities, add seeds needed to
exercise remaining observed edge-type sequences, and select an existing entity
with no valid results where available. No case is chosen because it passes the
implementation. Branch coverage refers to observed edge-type sequences, not every
possible combination of node types, properties, or references.

There are explicit contract qualifications:

- Legacy inverse paths may stop at a purified compound or a producing method/process
  when no upstream edge exists. A Process → Purified Compound link is valid raw
  graph evidence. It must not be dropped because a legacy field is named
  `shodhana_method`.
- `compound_therapeutic_use` associates a separately cited indication with a
  correlated purification path. Its final edge is not asserted to share the
  purification study. This matches its declared cross-study association contract;
  it does not establish treatment efficacy or one-study support for the full chain.
- Compound validation can return one selected safety record. The evaluator checks
  that it is one of the independently matched safety candidates, or absent when
  none exists. It does not assert exhaustive safety-record enumeration.
- Legacy row projections assert their documented fields, not every raw database
  property. Additional returned fields are allowed, but rows are matched one-to-one
  and complete counts reject extra, missing, and duplicate records.

## Current coverage and results

The authoritative expanded fixtures are `expanded_factual_cases_v2.json` and
`expanded_factual_coverage_v2.json`. The first candidate artifacts remain for audit.
Version 2 includes schema-verified Process producer branches and additional seeds
for edge-type sequences missed by the initial selection. These adjustments came
from raw record review, not relabeling a wrong answer as correct.

- **52/52 endpoints** have independent factual cases; each has at least two positive
  entities. These are entrypoints, not 52 independent graph patterns.
- **168 cases:** 132 positive cases and 36 graph-backed empty cases.
- **2,108 complete expected rows/paths** and **175 selected forbidden rows/paths**.
- **168 passed; 0 failed**, in `expanded_factual_report_v5.json`, after the six endpoint fixes. The unchanged version-2 references still define expected answers.
- **52/52 endpoints** pass all their selected cases. Coverage is not a pass rate.

The expanded runner invokes each registered endpoint directly against the live
database. It verifies actual seed identity/type, status, complete expected rows,
counts, forbidden rows and selected safety evidence. It records actual responses,
failures, fixture hashes, endpoint source hashes and timings. It bypasses the
question router deliberately; these 168 cases are not a natural-language routing,
HTTP transport, or answer-presentation benchmark. The preceding 24-case HTTP rerun
is separate evidence.

The pre-fix report `expanded_factual_report_v2.json` had 158 passes and these 10 failures:

| Endpoint | Failing cases | Confirmed gap |
| --- | ---: | --- |
| `formulation_compound` | 2 | Ayurvedic_Formulation seeds are reported as Formulation. Returned compounds match. |
| `integrated_disease_compound` | 1 | Bronchial Asthma paths lose the recorded Process label Marana for two purified compounds. |
| `process_compound` | 1 | Bharjana rows omit stored procedures for Chalcopyrite and Semecarpus anacardium. |
| `purified_compound_disease` | 2 | Indication nodes become Unknown Disease/Indication; names collapse during deduplication. Abhraka Bhasma returns 6 rather than 15 rows; Badrashma Pishti returns 1 rather than 3. |
| `purified_compound_formulation` | 2 | Ayurvedic formulation names become Unknown Formulation for Abhraka Bhasma and Lauha Bhasma. |
| `purified_compound_validation` | 2 | Stored study outcomes are returned as empty strings: 23 Abhraka Bhasma and 7 Badrashma Pishti records. |

Source inspection confirms type-restricted name lookups omit Indication,
Ayurvedic_Formulation, or Process nodes; a missing inferred type causes the seed
type fallback; limited/case-sensitive property selection omits Procedure/Result.
The raw snapshot contains the names and properties asserted by these references.
These gaps are now fixed: polymorphic targets resolve by actual record ID,
formulation seed types come from the resolved record, UNDERGOES retrieval keeps
the full edge before normalization, and safety outcomes use the existing
case-aware property normalizer. Distinct safety outcomes participate in deduplication.

The first repair run (`expanded_factual_report_v3.json`) passed 167/168. Restoring
names exposed identical disease/evidence rows across TREATS and USED_FOR_TREATMENT.
Those rows now group according to the flat response contract while retaining all
supporting edge IDs, types, sources, and targets in top-level `supporting_edges`, mapped by `result_index` and kept outside display properties.
The final version-5 report passes all 168 cases. No reference answers were changed,
and no entity-specific responses were introduced. This establishes agreement with
the frozen KG reference for selected cases, not independent clinical validity.

## Reproduction

```powershell
python -m unittest discover -s api/tests -p test_expanded_ground_truth.py
python api/tests/build_expanded_ground_truth.py
php api/tests/run_expanded_factual.php api/tests/fixtures/expanded_factual_cases_v2.json NEW_REPORT.json
```

The builder's default mode verifies committed labels; it never refreshes them to
silence failures. Initial creation with `--write` refuses existing files. The live
runner also refuses an existing report and checks snapshot hashes. Its current exit
code is **1**, reflecting the ten confirmed failures; do not report this as a fully
passing suite. The unit tests check wrong direction/type, cross-study references,
changed properties, Process partial paths, all endpoint contracts, original
snapshot preservation, and regeneration of every selected expanded reference row.

This remains graph-fidelity evidence, not scientific or publication verification.
More entities, ambiguous-name handling, adversarial conversations, and independent
evaluation of expanded answer presentation remain separate coverage dimensions.
