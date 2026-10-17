# Visual inspection and interpretation

The generated PNG previews were inspected, including a contact sheet of all 15 figures and enlarged schema, integration, purification, treatment, full-graph, conversation and publication-status views.

Corrections made during inspection:

1. Separated overlapping schema treatment-phase labels by enabling directed rank constraints.
2. Added invisible, explicitly nonsemantic ordering constraints to keep schema domains side by side; the amber integration arrow is horizontal. These constraints do not count as KG edges.
3. Encoded Graphviz node names separately from raw RIDs. Colons in RIDs otherwise become Graphviz port separators and create incorrect extra nodes. Original RIDs remain in visible case-study labels, SVG metadata and raw data.
4. Labelled every integration endpoint; the complete 75-edge bridge detail no longer relies on unlabeled dots.
5. Replaced the initial raster heatmap with native vector mesh cells and a vector color scale.
6. Used actual structured context fields in the conversation panel instead of the nullable query-plan field.
7. Bounded curvature for very large parallel-edge bundles while retaining a distinct SVG element for every edge.

The schema contains 17 semantic nodes and 25 semantic edges; the complete instance SVG contains 2,534 semantic nodes and 10,123 semantic edges. No instance self-loops occur in the source; the Process-to-Process schema relation remains visible. All 1,385 endpoint groups containing parallel edges remain represented in the full graph. Cross-domain arrows retain actual endpoint RIDs and the stored USED_FOR_TREATMENT direction.

Remaining visual limits: the complete instance graph is dense and some nodes/edges overlap at overview scale; it is a zoomable supplement, not a claim that 10,123 arrows can each be read on a printed page. The full schema and pathway diagrams have large native canvases so their labels remain editable and readable when enlarged. Before journal submission, select the needed core panels and reflow/split them at the journal's final dimensions rather than shrinking every master to a single column. The property matrix reports observed occupancy; unknown applicability is not silently converted into a clinical completeness score.

No live scientific or clinical verification was performed. The benchmark charts reproduce the named historical verified run, with its scope and denominators preserved. Namespace-audit categories do not validate scientific associations.
