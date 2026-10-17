> Historical v1 report. Superseded by [color-only revision v2](../node-colors-v2/README.md).

# Node-category accessibility ? 2026-09-26, v1

## Result and scope

A single 17-category style registry now drives normal and Color Blind Mode colors, category shapes/border patterns, and readable category badges. The existing toggle and localStorage preference remain unchanged. DOM markers use shared CSS variables; Cytoscape receives resolved hex colors. Aliases use the existing entity-type normalizer.

No KG data, retrieval endpoints, node/edge identities, relationship directions, or reference records were changed. Graph sizes, layouts, dragging, expansion, visibility filters, viewport and selection state are preserved. A dedicated active-node outline distinguishes the selected entity from its neighbors without replacing its category fill or border pattern. Disease Explorer remains undimmed.

**User-directed exception:** Integrated Explorer still displays its existing 15 allowed types. Its pre-existing `ALL_INTEGRATED_NODE_TYPES` list excludes **Indication** and **AyurvedicFormulation**, although its type definitions and palette include them. This is reported separately and deliberately not fixed. The renderer's 17 categories are covered across the existing 15-category Integrated and 10-category Compound views; test-only synthetic graph fixtures are explicitly labeled and are not KG ground truth.

## Audit findings

- Existing normal graph palettes used 15 unique fills for 17 categories: Disease/Indication and Formulation/AyurvedicFormulation were identical.
- Existing color-blind graph palette used only nine unique fills, including repeated black, green, yellow, blue, purple and vermillion mappings.
- Search used a separate palette; graph-selected chips, landing lists and tables bypassed the mode-aware palette.
- HSL string replacement and appending `18` to color strings were unsafe for shared color tokens. They now use explicit `color-mix` tints.
- Graph nodes previously shared circular shapes. They now have 17 unique shape/border combinations, with matching markers in legends, filter headings and visibility lists, property panels, table headers and landing entity lists. Search badges retain readable category text with category-specific tints. How to Use node markers follow the registry; obsolete fixed color names were removed from those labels only.
- The existing non-category brand colors and edge color mappings are unchanged. Graph category aliases in CSS resolve to the shared registry rather than maintaining a second palette.

The previous graph/search mappings are in [baseline-colors.json](baseline-colors.json). The original source is archived in `baseline-source.zip` for audit comparison.

## Final category mapping

| Category | Normal fill | Color Blind Mode fill | Non-color cue |
|---|---|---|---|
| Disease | `#BD3F36` | `#D55E00` | ellipse / solid |
| Symptom | `#D69B18` | `#E6C229` | triangle / solid |
| AyurvedicDiagnosis | `#604735` | `#595959` | diamond / solid |
| Dosha | `#914E2D` | `#8C6D31` | hexagon / solid |
| Therapy | `#258568` | `#009E73` | round-rectangle / solid |
| Formulation | `#AD5793` | `#CC79A7` | rectangle / solid |
| AssessmentParameter | `#337BAA` | `#0072B2` | pentagon / solid |
| Compound | `#567D36` | `#648F3D` | octagon / solid |
| ShodhanaMethod | `#C67A24` | `#E69F00` | round-diamond / solid |
| ToxicSubstance | `#912D5A` | `#882255` | triangle / dashed |
| PurifiedCompound | `#399FC0` | `#56B4E9` | octagon / dashed |
| ShodhanaType | `#7955B2` | `#7B61A8` | hexagon / dashed |
| Media | `#247B82` | `#2B7478` | ellipse / dashed |
| ConfirmationStudy | `#847121` | `#B39B00` | pentagon / dashed |
| Process | `#8A7160` | `#AA8866` | round-diamond / dashed |
| Indication | `#D47787` | `#DD8899` | diamond / dashed |
| AyurvedicFormulation | `#57578F` | `#555599` | rectangle / dashed |

The category border uses black or white, whichever contrasts best against its fill. An independent `#30251D` outer outline provides a visible silhouette against the light canvas. Selected nodes have a wider, offset outer outline; hovered nodes have a thinner offset outline. Existing labels, category text and tooltips remain available. Category borders and shapes do not change with selection or hover.

The authoritative values live in `src/data/nodeStyles.json`; `src/lib/nodeStyles.ts` resolves aliases, DOM tokens, border contrast and SVG geometry. The normal and accessible palettes have no exact duplicate fills. Some colors remain similar under simulated deficiencies, so these palettes are **not claimed to distinguish 17 categories by color alone**.

## Measured accessibility and visual review

Each palette was evaluated across all 136 category pairs under normal vision, protan/deutan/tritan simulation at 50% and 100% severity, and grayscale. Matrices are applied to linear sRGB and gamut-clipped before OKLab distance measurement. Distance below 0.05 flags a pair for visual review; it is not a WCAG pass/fail threshold.

| Palette | Unique colors | Close pairs: normal | Protanopia | Deuteranopia | Tritanopia |
|---|---:|---:|---:|---:|---:|
| normal | 17 | 0 | 3 | 8 | 5 |
| accessible | 17 | 0 | 5 | 3 | 5 |
| baseline-normal | 15 | 3 | 13 | 8 | 7 |
| baseline-accessible | 9 | 10 | 12 | 10 | 10 |

Minimum contrast across actual `background`, `card`, `secondary` and `muted` surface tokens:

| Palette | Badge text on 10% tinted surfaces | Outline against surfaces | Category border against fill |
|---|---:|---:|---:|
| Normal | 10.06:1 | 11.70:1 | 4.60:1 |
| Color Blind Mode | 9.96:1 | 11.70:1 | 4.83:1 |

These exceed the targeted 4.5:1 text and 3:1 essential graphical boundary thresholds. Historical UI contrast is not inferred from the baseline color-pair simulation.

- [All category simulations and baseline comparison](palette-comparison.html)
- [Full numeric results, including every flagged pair](palette-audit.json)
- [Normal Disease Explorer](after-disease-normal-none.png)
- [Disease Explorer, Color Blind Mode, simulated deuteranopia](after-disease-accessible-deuteranopia.png)
- [Normal Compound Explorer](after-compound-normal-none.png)
- [Accessible Integrated Explorer](after-integrated-accessible-none.png)

Twenty-four real-graph screenshots cover 3 Explorers ? 2 modes ? normal/protanopia/deuteranopia/tritanopia rendering. Palette sheets additionally show moderate deficiencies and grayscale. Normal and accessible category sheets and actual graph captures were visually inspected; border contrast was strengthened after that review. At small legend sizes, adjacent category text remains important. Dense graph label overlap, pre-existing zoom/dimming outside Disease Explorer, and human perception cannot be certified by palette simulation alone. Dark theme is not an exposed mode and was not redesigned or certified. Feedback from users with color-vision deficiencies remains recommended.

Method references: [Machado et al. 2009](https://doi.org/10.1109/TVCG.2009.113), [matrix transcription in colorspacious](https://github.com/njsmith/colorspacious/blob/master/colorspacious/cvd.py), [W3C use of color](https://www.w3.org/WAI/WCAG22/Understanding/use-of-color), [W3C non-text contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast).

## Verification

- Lint: zero errors; 19 existing warnings.
- Production build: passed; existing chunk-size/Browserslist notices remain.
- Unit tests: **81/81 passed** (22 files), including four new tests and strengthened focus assertions.
- Targeted Chromium regression: **19/19 passed**: five new category tests, three real mouse-drag tests, one Disease edge-visibility check and ten route smoke checks.
- Required mobile checks: **9/9 failed with the existing horizontal-overflow issue**, across Disease, Compound and Integrated landing/graph/table routes. These layouts were not changed. Broader run: 19 passed / 9 failed. The full browser suite is not claimed green.
- Mode-switch checks assert graph-element IDs, edge data, node positions, viewport, selection, shape and border pattern remain unchanged. Reload retains mode. Search badges/table markers follow the same preference. Real graph selections/hover retain category fills and cues.
- No backend suite was rerun: this change modifies presentation and UI tests only.

Reproduce:

```text
python scripts/audit_node_colors.py
node scripts/capture_node_colors.cjs
npm run lint
npm run build
npm test
npx playwright test --config playwright.external.config.ts --project=chromium --workers=2 --grep "Node category accessibility|Route smoke|Mobile|keeps elements stable|connected edges remain visible"
```

The Python audit requires numpy; capture/tests use the existing Playwright dependency and user-owned servers. Do not restart the user's dev server automatically.

## Files

Changed existing source/test files:

- `src/index.css`
- `src/data/compoundGraph.ts`
- `src/data/diseaseGraph.ts`
- `src/data/integratedGraph.ts`
- `src/hooks/useColorblind.tsx`
- `src/lib/graphFocus.test.ts`
- `src/lib/graphFocus.ts`
- `src/pages/HowToUse.tsx`
- `src/pages/SearchResults.tsx`
- `src/components/explorer/DetailsPanel.tsx`
- `src/components/explorer/GraphCanvas.tsx`
- `src/components/explorer/Legend.tsx`
- `src/components/explorer/MultiAutocomplete.tsx`
- `src/pages/explorers/CompoundExplorer.tsx`
- `src/pages/explorers/CompoundGraphView.tsx`
- `src/pages/explorers/CompoundTableView.tsx`
- `src/pages/explorers/DiseaseExplorer.tsx`
- `src/pages/explorers/DiseaseGraphView.tsx`
- `src/pages/explorers/DiseaseTableView.tsx`
- `src/pages/explorers/IntegratedGraphView.tsx`
- `src/pages/explorers/IntegratedTableView.tsx`

Added source/tests/scripts:

- `src/data/nodeStyles.json`
- `src/hooks/useColorblind.test.tsx`
- `src/lib/nodeStyles.test.ts`
- `src/lib/nodeStyles.ts`
- `src/components/explorer/CategoryMarker.tsx`
- `tests/node-colors.spec.ts`
- `scripts/audit_node_colors.py`
- `scripts/capture_node_colors.cjs`

Audit artifacts are contained in `docs/accessibility/node-colors/` (report, palette tables, numeric results, source archive and screenshots).
