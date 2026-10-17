# Figure captions

## core/C01_scale

Census counts of unique RIDs. The complete graph includes 75 cross-domain edges; Disease and Compound bars count internal edges only. The three bars are nested views, not disjoint domains. No sampling uncertainty or error bars.

## core/C02_category_chord

Directed category chord; sectors ordered compound then disease. Ribbon widths count edge records, not clinical strength. Arrow tips identify target sectors. All 25 typed predicate combinations are separate ribbons; the three therapy phases are explicitly listed. Sector arc totals count incoming plus outgoing incidence (twice the edge total overall). Full counts in schema_counts.csv.

## core/C03_category_coloured_graph

Complete instance graph: category-coloured nodes, quiet internal edges and amber integration edges. All RIDs, directions and properties preserved. Original saved positions reused; node size is constant, not a centrality measure. Dense overview uses metadata instead of full visible labels.

## core/C04_integration_heatmap

29 Purified Compounds × 17 Diseases; 75 stored treatment records occupy 56 distinct pairs. Zero cells are retained; colour is record count, not efficacy. The displayed participants are a subset of all 71 Purified Compounds and 134 Diseases.

## core/C05_connectivity

Weak connectivity ignores edge direction only for component membership. Total multidegree counts incoming plus outgoing records; distinct neighbours ignore record multiplicity. Bridge coverage is direct USED_FOR_TREATMENT participation, not efficacy or absence of real-world associations.

## supplementary/S00_complete_graph_large

Larger zoomable companion of C03 with identical positions, categories and complete graph membership. Isolates and parallel edges retained; zooming is required to inspect dense regions.

## supplementary/S01_semantic_sankey

Each KG edge contributes one unit through source category, predicate and target category. Both adjacent-column totals equal 10,123; do not add them. This is edge composition, not a causal sequence, patient flow or literal acyclic KG. Predicates keep exact stored spelling.

## supplementary/S02_domain_chords

Internal edges only: Compound 4,123; Disease 5,925. Same category palette and directed predicate ribbon definition as C02; integration edges are excluded from these internal panels.

## supplementary/S03a_category_counts

Directed category matrix; rows source, columns target; logarithmic colour scale with zero white. Diagonal Process cell represents same-type edges, not instance self-loops.

## supplementary/S03b_category_percent

Percentage of outgoing edges per source category. Rows with no outgoing records remain zero, not missing; values are compositional, not probabilities of clinical outcomes.

## supplementary/S04_property_coverage

Percentage of records containing a nonempty raw property. Denominator is all edges of each predicate, not known clinical eligibility. Zero means unpopulated in this snapshot; applicability is unknown. Exact numerators and denominators accompany the figure.

## supplementary/S05_reference_concentration

410 distinct raw identifiers. Edges sharing a reference are repeated records, not independent publications or independent experiments. Concentration measures dataset composition, not publication quality.

## supplementary/S06_complete_communities

Exploratory complete communities from the undirected unique-endpoint projection; Louvain resolution 1, medoid partition across seeds 0–19. Original directed multigraph is rendered, not collapsed. Weighted sensitivity and assignments supplied. Singleton isolates remain separate. Colours are community labels, not validated biological classes.

## supplementary/S06_compound_communities

Exploratory compound communities from the undirected unique-endpoint projection; Louvain resolution 1, medoid partition across seeds 0–19. Original directed multigraph is rendered, not collapsed. Weighted sensitivity and assignments supplied. Singleton isolates remain separate. Colours are community labels, not validated biological classes.

## supplementary/S06_disease_communities

Exploratory disease communities from the undirected unique-endpoint projection; Louvain resolution 1, medoid partition across seeds 0–19. Original directed multigraph is rendered, not collapsed. Weighted sensitivity and assignments supplied. Singleton isolates remain separate. Colours are community labels, not validated biological classes.

## supplementary/S07a_community_stability

20 seeds per network per projection. Selected partition maximizes mean agreement; ties use lowest seed. Comparison with multiplicity-weighted projection is exploratory sensitivity, not evidence that communities are clinically valid. ARI is adjusted Rand index.

## supplementary/S07b_community_composition

Node counts by exploratory complete-graph community and semantic category. Communities ordered by size, tie by stable RID; colours encode counts, not category identity.

## supplementary/S07c_category_community_alluvial

Every node contributes once from its semantic entity category to an algorithm-derived community (2,534 total). This is a membership cross-tabulation, not a temporal or biological transition.

## supplementary/S08_disease_therapy_similarity

Jaccard similarity of distinct Therapy neighbours for 134 Diseases; 0 empty-neighbour diseases excluded and listed. PRE_TREATED_BY, TREATED_BY and POST_TREATED_BY are pooled only for this declared similarity projection. Average linkage orders rows; indices map to full labels/RIDs in CSV. Neighbourhood similarity is not clinical similarity.

## supplementary/S09_supported_paths

Exact path UNDERGOES → PRODUCES → USED_FOR_TREATMENT with Compound → Shodhana Method → Purified Compound → Disease node types. Structural paths may join different sources. A nonempty exact-reference intersection is reported separately; it still does not prove a single experimental context or clinical efficacy. No unsupported alternate path is inferred.

## supplementary/S10_palette_assessment

Severe-condition colour-vision stress test using Machado matrices in linear sRGB, clipping and re-encoding for display. Tritan is an approximation, not a validated simulation of individual tritanopia. Some category colours converge: labels, abbreviations and domain placement are mandatory. This is not a claim of universal 17-colour accessibility. Source: https://colour.readthedocs.io/en/develop/_modules/colour/blindness/machado2009.html