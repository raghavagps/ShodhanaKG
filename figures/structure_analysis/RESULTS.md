# Structural analysis results

## Methods

The frozen 28 September 2026 ArcadeDB export was analysed as a directed multigraph. Identical inherited-table copies were reconciled by RID, while distinct edges, predicates and node identities were retained. Explicit entity types defined Compound and Disease domains. Graph counts, directed category matrices, integration connectivity, raw-property coverage, reference concentration and exact three-hop paths were computed. Disease similarity used distinct Therapy-neighbour Jaccard scores. Exploratory Louvain communities were evaluated on three networks under two undirected projections across 20 seeds each (120 fits); selected partitions maximized mean adjusted Rand agreement. Projection details and limitations are in README.md.

## Results

The snapshot contained 2,534 nodes and 10,123 directed edge records, with 17 entity categories and 21 predicates distributed across 25 source-category/predicate/target-category combinations. Disease contained 1,832 nodes and 5,925 internal edges; Compound contained 702 nodes and 4,123 internal edges. A further 75 integration records connected 29/71 purified compounds (40.85%) to 17/134 diseases (12.69%) through 56 distinct endpoint pairs. The largest weak component contained 2,532/2,534 nodes (99.92%); two nodes were isolated. All 134 diseases had Therapy neighbours for the similarity analysis. The specified three-hop path yielded 1,033 structural node sequences, of which 52/1,033 (5.03%) shared an exact nonempty reference across all hops. These counts describe stored associations, not independent clinical experiments.

| Metric | Observed value |
|---|---:|
| Complete graph nodes | 2,534 |
| Complete graph edges | 10,123 |
| Disease domain nodes | 1,832 |
| Disease internal edges | 5,925 |
| Compound domain nodes | 702 |
| Compound internal edges | 4,123 |
| Integration edge records | 75 |
| Integration endpoint pairs | 56 |
| Entity categories | 17 |
| Distinct predicates | 21 |
| Typed predicate connections | 25 |
| Distinct directed endpoint pairs | 6,368 |
| Distinct directed predicate triples | 6,420 |
| Endpoint pairs with parallel records | 1,385 |
| Largest parallel-record count | 56 |
| Instance self-loops | 0 |
| Largest weak component nodes | 2,532 |
| Isolated nodes | 2 |
| Distinct raw reference identifiers | 410 |
| Diseases included in similarity matrix | 134 |
| Structural node-sequence paths | 1,033 |
| Paths with common exact reference | 52 |
| Exploratory community fits | 120 |

## Community sensitivity

The selected unique-pair partitions contained 12 complete-graph, 12 compound and 14 disease communities. Agreement with the respective record-weighted selected partitions was ARI 0.609, 0.396 and 0.529. This sensitivity limits interpretation as fixed biological modules. Seed-level counts and agreement values are exported in CSV.

## Quality checks and limits

All 21 figures passed SVG XML/vector/text/external-asset checks, PDF raster-substitute checks and RGB 300 dpi preview checks. Five network exports were reconciled by exact node and edge RIDs and raw record content, including edge endpoints and properties. All 75 integration edges are retained in every complete-graph export. Core and larger supplementary views preserve full graph membership; zero instance self-loops were present. CSV category counts were independently recomputed from the source records. Known contingency-table examples checked the adjusted Rand implementation.

Visual inspection covered the contact sheet and enlarged chord, integration heatmap, flow diagrams, category-coloured graph and community previews. Label collisions in flow diagrams were separated using leaders; community IDs were added; thousands separators were applied. Dense graph marks still overlap at page scale, and 17 colours cannot guarantee distinction for every viewer. The unlabelled overview keeps editable record metadata; CSVs and zoomable SVGs support detail inspection.

These analyses describe one stored snapshot. They do not establish publication validity, clinical efficacy, safety, or biological validity of inferred communities. Identifier counts are not asserted to equal unique studies. The source snapshot remained unchanged. No application, KG or production modifications were made.
