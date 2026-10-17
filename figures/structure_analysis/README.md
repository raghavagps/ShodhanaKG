# ShodhanaKG structural-analysis figures

This collection uses actual instance records, not invented data or a schema-only example. Core and supplementary figures are separated. Start with [the figure index](INDEX.md) and [captions](CAPTIONS.md). Quantitative outputs are in `data/` and `analysis_results.json`; export checks are in `validation.json`.

## Input and reproduction

The input is the existing **28 September 2026** ArcadeDB export, `.audit-temp/production-20260928/production-records.json`. This is a frozen snapshot, not a new live-database export. Exact duplicates from inherited table enumeration are reconciled by RID; conflicting copies, missing endpoints or unknown entity types fail loading. Different RIDs are never merged by label or reference. Stored spelling, including `DERIEVED_FROM`, is preserved.

Saved positions from `figures/data/S01_complete_instance_graph_positions.csv` are reused for the category-coloured complete instance graph. The new script reuses the loader and explicit domain mapping in `plot_integrated_kg.py`. Input, positions, loader, generator and figure-guideline hashes are recorded in `manifest.json`. No source data, application code or production server is changed.

Run from the repository root on D: in the existing isolated environment:

```powershell
& .audit-temp/figure-env/Scripts/python.exe -B plot_kg_structure_analysis.py --input .audit-temp/production-20260928/production-records.json --positions figures/data/S01_complete_instance_graph_positions.csv --output figures/structure_analysis --seed 42 --label-mode auto
```

Dependencies are pinned in `requirements-figures.txt`. This pipeline uses Matplotlib, NetworkX, NumPy, SciPy and Pillow; it does not use Graphviz or external assets. `plot_integrated_kg.py` is required alongside the new script for loading only. All temporary/cache directories are local under `.audit-temp/structure-*` on D:. The script has `--help` and optional `--only` stages; selective rebuilds merge into an existing manifest only when inputs, seed and label mode match. Use the default complete run to reproduce the entire collection.

Verify the exports against raw records independently of their plotted appearance:

```powershell
& .audit-temp/figure-env/Scripts/python.exe -B verify_kg_structure_figures.py --output figures/structure_analysis
```

## Interpretation and analysis design

- The KG has **two semantic domains**, Compound and Disease. Integrated Explorer is a third view of these domains, not a third disjoint biological domain. Counts must not sum Complete + Disease + Compound.
- Counts refer to stored node/edge RIDs. The complete graph includes 75 integration edges; domain-only counts exclude those edges. Edge count is not a count of independent experiments or publications.
- Category chord ribbons count each source-category/predicate/target-category combination separately. The three Disease-to-Therapy treatment phases remain distinct. Sector size counts incoming plus outgoing incidence, so sector totals equal twice the edge count. Arrow tips mark the receiving category. Exact predicate metadata is retained in SVG ribbon tooltips and the count table.
- The three-column Sankey is an edge-composition cross-tabulation: source category → predicate → target category. Each adjacent stage totals the same 10,123 records. Categories with zero flow keep their labels but have zero bar area. It does not represent patients, causal flow or a tree of instance relationships.
- Directed category heatmaps use source rows and target columns. Raw counts and row-normalized percentages are separate figures; rows without outgoing records remain zero. The integration heatmap counts actual `USED_FOR_TREATMENT` edges. Colour intensity is record multiplicity, not efficacy.
- Community analysis is explicitly exploratory. Unique undirected endpoint pairs form the primary projection, with self-loops excluded only from this projection. A second projection weights each pair by original edge-record multiplicity. For each of Complete, Compound and Disease, Louvain resolution 1 is run with seeds 0–19 under both projections: **120 fits**. The selected partition maximizes mean adjusted Rand agreement with the other 19 fits; ties select the lowest seed. Node order and community labels are deterministic. Communities are numbered by descending size, with stable-ID tie breaking. Domain communities are computed independently; C1 does not mean the same community across networks. The rendered community maps retain all original directed edge records; assignments and stability are exported.
- Disease similarity is Jaccard similarity of distinct Therapy neighbours. PRE_TREATED_BY, TREATED_BY and POST_TREATED_BY are pooled **only in this declared projection**. Diseases without such neighbours are excluded and reported. Average linkage on 1 − Jaccard orders the matrix. Indices map to complete labels and RIDs in CSV. Shared neighbours are not clinical similarity.
- The path analysis requires Compound → Shodhana Method → Purified Compound → Disease with exact predicates UNDERGOES → PRODUCES → USED_FOR_TREATMENT. Structural node-sequence paths are counted separately from paths with a nonempty exact raw-reference intersection across all three hops. A shared reference does not establish a shared experimental context, correctness of the paper, safety or clinical efficacy.
- Edge-property coverage uses nonempty stored values, with all edges of each predicate as denominator. Missing values are not assumed to be required or clinically applicable. Reference concentration uses raw identifier strings without changing their namespaces or merging records.

## Figure guideline compliance

The controlling reference is the repository's `figures_guidelines.md`.

- White backgrounds, solid fills, no hatching, gradients or decorative effects.
- Arial text; body/tick labels 8 pt at the exported dimensions, with slightly larger titles. Core widths are 180 mm. Larger supplementary figures preserve readable labels; do not shrink them to single-column width without reformatting.
- Vector line widths are at least 0.25 pt. SVG masters retain editable text, explicit node/edge IDs, record tooltips and a viewBox. Nodes, links, arrowheads, matrices and legends remain vectors.
- **Submit PDF for vector figures.** The supplied guidelines do not accept SVG for submission; SVG is the editable master. PDFs contain embedded text fonts and no raster image substitutes. RGB 300 dpi PNG previews and JPEG submission previews are also included.
- Category colours are fixed across all category figures; the complete mapping and abbreviation key are in `data/palette.csv`. Blues is used for sequential intensity. Community colours have different semantics and are accompanied by C labels and assignments, not conflated with category colours.
- Colour is not a sufficient identifier for 17 categories. Severe-condition protan/deutan simulations and an approximate tritan transform are supplied as a stress test. Some colours converge; labels, domain placement, numeric matrices and exported IDs remain necessary. No universal colour-blind accessibility claim is made. Method reference: [Machado model documentation](https://colour.readthedocs.io/en/develop/_modules/colour/blindness/machado2009.html), [original model paper](https://pubmed.ncbi.nlm.nih.gov/19834201/).

## Limits

The complete graph is deliberately unsampled. Dense regions contain overlapping marks and many parallel edges; a static page cannot make every one of 10,123 records individually readable. Use the large SVG, metadata and exact CSVs for inspection. `--label-mode all` and `bridge` are available, but labels can overlap at this density; the default overview suppresses per-node text while preserving record metadata.

Communities depend on graph projection, resolution and seed. They are not experimentally validated biological modules. This collection describes the stored dataset's structure and coverage; it does not verify publications, scientific assertions, treatment effectiveness or clinical safety. The new figures do not replace independent factual evaluation or domain-expert review.
