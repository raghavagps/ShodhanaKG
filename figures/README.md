# ShodhanaKG manuscript figure collection

Seven core figures and eight supplementary figures. Each has an editable SVG master and vector PDF. PNGs in `previews/` are inspection aids, not embedded in vector outputs. Captions are in `CAPTIONS.md`; numerical source tables and selected raw records are in `data/`. `manifest.json` records input hashes, parameters and selection policies; `validation.json` records XML, vector/text and semantic-count checks.

## Reproduce

Run from the repository root with Python 3.12. Install `requirements-figures.txt` in an isolated environment. Graphviz's **external dot executable** is also required; the Python package alone does not include it. The executed Windows command is:

```powershell
$env:TEMP="$PWD\.audit-temp"
$env:TMP=$env:TEMP
$env:MPLCONFIGDIR="$PWD\.audit-temp\figure-mpl"
$env:XDG_CACHE_HOME="$PWD\.audit-temp\figure-cache"
& .audit-temp/figure-env/Scripts/python.exe -B plot_integrated_kg.py
```

The full generator was executed and its previews inspected. Final metadata/count validation was also executed successfully with `python plot_integrated_kg.py --validate-existing` (using the same environment). That option rechecks the already generated vectors without running the expensive force layout again. A Windows PNG overwrite error during the last render pass was addressed with atomic preview writes; the create/overwrite export smoke check passed. The final vectors passed all checks; see `run_validation.log` and `export_checks.json`.

The default executable is `.audit-temp/graphviz-runtime/Library/bin/dot.exe`. On another machine pass `--dot /path/to/dot`. Use `--input`, `--benchmark`, `--catalog`, `--output`, `--seed`, `--spacing`, `--font-size` and `--label-mode auto|all|bridge` to configure the run. The graph input is an ArcadeDB snapshot with a `tables` mapping. Unknown memberships, conflicting duplicate RIDs and missing endpoints stop generation; no fabricated schema fallback is used.

## Inputs and interpretation

- Actual graph: `.audit-temp/production-20260928/production-records.json`, captured during deployment; not a new live export. Inherited table rows are deduplicated by identical RID/record, never label. 2,534 vertices, 10,123 edges, 17 types, 21 predicates, 25 type-level connections and 75 cross-domain treatment edges.
- Verified benchmark: `docs/audits/final-release-20260928/`. These local evaluation results predate later PHP/deployment changes; no new benchmark was run to make these figures. Metrics from overlapping suites are not pooled.
- Namespace catalog: `api/core/reference_catalog_v1.json`, joined by exact raw reference value, not by local/production edge RID. The different RID namespaces do not affect that join.
- Identifier verification categories: `docs/chatbot/audits/identifier_rules_2026_09_24_v1/reference_classification.csv`. Historical registry access and namespace verification are not scientific/clinical validation.
- Explicit domain membership matches `api/graph_service.php`. Ayurvedic Formulation and Formulation remain separate. Stored `DERIEVED_FROM` is retained.

## Figure allocation

Core: 01 schema; 02 composition; 03 complete cross-domain integration; 04 retrieval workflow; 05 software evaluation; 06 evidence-linked purification example; 07 evidence coverage.

Supplementary: S01 complete instance graph; S02 treatment-phase/formulation example; S03 edge-property coverage; S04 connectivity; S05 language robustness; S06 recorded conversation traces; S07 failure/limitation profile; S08 identifier-verification status.

The schema and workflow are explicitly labelled abstractions, not substitutes for the populated graph. The complete graph uses separate seeded force layouts inside known domain regions, all edges retained. Parallel predicates/records are individually curved. Auto mode suppresses dense full-graph text; SVG node/edge titles retain exact raw records. The integration panel preserves all 75 bridge edges; detail selections are defined in captions, with full selected records supplied.

## Publication preparation and limits

The supplied journal guideline does **not accept SVG**: submit PDF, retain SVG as the editable source. All text remains text; no external fonts/images/scripts are fetched. Arial is used from the local system. White module interiors replace the previously proposed background shading. Printed strokes are at least 0.25 pt at the exported size, except Graphviz rendering must be inspected after any journal resizing.

These are editable figure masters at their exported dimensions. Dense schema and case graphs should not simply be shrunk to a single column: reflow/split panels or use supplementary full-size versions to retain approximately 8 pt text. The full 10,123-edge graph is a zoomable supplement and cannot make every edge visually separable at page size. Identical endpoints retain distinct SVG edge elements; very dense parallel bundles can still visually overlap.

Property dashes mean no populated values observed, not proven non-applicability or missing clinically required data. Registry matches do not establish intended namespace or claim support by themselves. No numeric scientific-review completion rate is fabricated. Software pass rates are finite test-suite outcomes, not clinical accuracy estimates. Latency plots show recorded median and p95 summaries, not reconstructed raw distributions or confidence intervals.

No application code, KG records, fixtures, expected results or existing tests are modified by this pipeline.

## Additional core figure 08

The evaluation-loop diagram is generated separately with `.audit-temp/figure-env/Scripts/python.exe -B plot_evaluation_workflow.py`. Its files are `core/08_evaluation_loop.svg`, `.pdf`, and `.json`, with a PNG preview. This adds an eighth core figure; the original 15-figure manifest/archive remain unchanged. The diagram is conceptual, not a report of new benchmark results.
