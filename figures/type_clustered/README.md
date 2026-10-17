# Complete ShodhanaKG: node-type layout

Layout/styling-only alternative for the frozen 28 September 2026 snapshot. The original figures, saved positions, community assignments and analysis outputs are unchanged.

- [Core SVG](core/C03_category_coloured_graph.svg) · [Core PDF](core/C03_category_coloured_graph.pdf) · [Preview](previews/C03_category_coloured_graph.png)
- [Large SVG](supplementary/S00_complete_graph_large.svg) · [Large PDF](supplementary/S00_complete_graph_large.pdf) · [Preview](previews/S00_complete_graph_large.png)

## Design

Compound and Disease occupy separate regions. Each of the 17 node types has a separate spatial group and retains its existing category colour. Labels give the type abbreviation and node count; a full category legend is included. All markers have constant size within an export. Lattice packing separates the markers. Type anchors are drawing choices, not detected communities or scientific similarity coordinates.

The layout initially assigns nodes to slots using their incident connections, then accepts within-type slot swaps only when they reduce a straight-segment crossing diagnostic. This moves drawing positions only; it never changes node identity, domain/type membership, edges, properties, references or community assignments. Normal edges are grey and quieter; the 75 integration records remain orange. All arrows and parallel records remain separate SVG elements with their original record metadata. Isolates remain present inside their type groups.

## Verification and limitations

- Both SVGs match all **2,534 node records and 10,123 edge records**, including IDs, raw properties and directed endpoints.
- Hash checks confirmed **123 existing source/analysis files** unchanged, including the source snapshot, original positions, scientific results, community figures and assignments.
- At the 180 mm core export size, circular node-marker overlap fell from **2,222 overlapping pairs to zero**. This check includes marker outlines and excludes text/edges. Minimum centre spacing is 2.716 pt versus a 1.664 pt marker diameter including outline.
- The within-type refinement reduced the new layout's unique-pair straight-segment crossing proxy from **1,556,055 to 1,458,367** (97,688 fewer). However, the previous mixed-type layout scored **811,023**. **An overall crossing reduction relative to the previous layout was not achieved on this proxy.** The new design improves category separation and eliminates marker overlap, with this measured crossing trade-off. The proxy excludes repeated edges and is not an exact count of intersections between the rendered curved paths. Dense edge bundles remain; no edges were suppressed to improve the numbers.
- Both previews were visually inspected for category separation, label clearance, legend readability and clipping. The figures remain native vector SVG/PDF with editable text; PNG/JPEG files are RGB 300 dpi previews. Submit PDF where SVG is not accepted.

Checks are saved in `validation.json` and `geometry_checks.json`. `layout_positions.csv` contains only the new drawing coordinates; it does not replace the original positions or any KG data.

## Reproduce locally on D:

From the repository root, using the existing isolated environment and `requirements-figures.txt`:

```powershell
& .audit-temp/figure-env/Scripts/python.exe -B plot_kg_type_layout.py
```

The new script reuses the existing loader, palette and vector exporter. It does not call the community-detection or scientific-analysis functions. All caches and temporary files remain under the project's D:-located `.audit-temp` directory. No application code or production server changes are required.
