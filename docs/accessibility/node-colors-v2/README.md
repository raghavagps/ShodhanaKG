> Historical palette: reverted to baseline-source.zip colors on 2026-09-26, except Ayurvedic Diagnosis remains #004488. Local resources and original node geometry remain unchanged.

# Color-only node revision ? 2026-09-26

This supersedes the v1 shape/border design. The archived pre-palette GraphCanvas.tsx and graphFocus.ts are restored byte-for-byte. Original circle shapes, borders, sizing, label styling, selection/highlight behavior, layouts and interactions are retained. Original circular markers and details layout are restored. No KG/backend changes.

## Mapping

The shared registry supplies all Explorer node fills, legends, filters, badges, table accents and help swatches. Color Blind Mode switches the palette and persists the preference. Selected and hovered nodes retain category fill and the original interaction appearance.

| Category | Normal | Color Blind Mode |
|---|---|---|
| Disease | #BB0000 | #772200 |
| Symptom | #FF1177 | #AA8877 |
| AyurvedicDiagnosis | #004488 | #004488 |
| Dosha | #BB6600 | #BB6600 |
| Therapy | #006600 | #007788 |
| Formulation | #EE00FF | #FF00BB |
| AssessmentParameter | #5588FF | #0099DD |
| Compound | #009900 | #666655 |
| ShodhanaMethod | #553322 | #994400 |
| ToxicSubstance | #990066 | #CC2288 |
| PurifiedCompound | #339988 | #8866FF |
| ShodhanaType | #7700DD | #8800EE |
| Media | #0000FF | #113344 |
| ConfirmationStudy | #666666 | #887755 |
| Process | #AA66AA | #664455 |
| Indication | #BB00BB | #9922BB |
| AyurvedicFormulation | #550077 | #441166 |

## Accessibility evidence

Both palettes have 17 distinct fills. Ayurvedic Diagnosis is blue and Dosha amber in both modes. The normal palette prioritizes separation in normal vision; the accessible palette prioritizes the worst pair across normal vision and moderate/full protanopia, deuteranopia and tritanopia simulations. All 136 category pairs are measured in each condition.

Normal palette minimum OKLab distance in normal vision: 0.13663. Accessible palette minimum across the seven normal/CVD conditions: 0.06455; no pairs fall below the 0.05 review threshold. These are screening metrics, not a WCAG certification or proof that every user can distinguish all 17 categories by color. Grayscale still contains close pairs. Existing node labels, legends and tooltips remain essential; no additional shapes or borders were added.

See [numeric results](palette-audit.json), [simulation sheet](palette-comparison.html), and [image](palette-comparison.png). Captured 24 real-graph screenshots (three Explorers ? two modes ? normal/protanopia/deuteranopia/tritanopia); inspected the simulation sheet and representative Disease/Compound views. Existing crowded graphs remain crowded: positioning and layouts were deliberately preserved.

## Local resources

Fraunces, Inter and the animation page's Manrope are bundled locally with their licenses in public/fonts. Existing families and weight support are retained. Removed Google font requests, remote translation calls and hosted social preview imagery. Hindi uses the existing local glossary and retains original English when no local translation exists. Existing informational and publication navigation links remain as explicitly agreed. The local backend still supplies KG data; this is not an offline database implementation.

## Validation

- Lint: 0 errors, 19 existing warnings.
- Production build: passed (existing chunk-size/Browserslist notices).
- Vitest: 82/82 passed, including the new local-translation test.
- Palette/local-resource Chromium checks: 6/6 passed. Verify category fills, persisted switching, unchanged positions/edges/selection/geometry, search/table markers, and local fonts with external requests blocked.
- Route smoke: 10/10 passed.
- Compound dragging and stable graph elements: 3/3 passed (Copper, Lead, filtered Copper).
- Mobile overflow: 9/9 fail with the previously documented horizontal overflow; left unchanged to preserve layout scope.
- Archived original renderer/focus source comparison: both exact matches.

Integrated Explorer retains its existing 15-category allow-list, excluding Indication and AyurvedicFormulation, as requested. The shared registry still covers those categories where supported elsewhere.

## Files

Restored original design: src/components/explorer/{GraphCanvas,DetailsPanel,Legend,MultiAutocomplete}.tsx; src/lib/graphFocus.ts; removed CategoryMarker.tsx. Color wiring remains in src/data/{nodeStyles.json,diseaseGraph.ts,compoundGraph.ts,integratedGraph.ts}, src/lib/nodeStyles.ts, src/hooks/useColorblind.tsx, src/index.css, src/pages/{SearchResults,HowToUse}.tsx and the Disease/Compound/Integrated graph/table pages. Explorer landing pages restored from baseline.

Local resources: package.json/package-lock.json; src/fonts.css; public/fonts/*; public/graph_animation.html; index.html; src/lib/translationService.ts.

Tests: src/lib/{nodeStyles,graphFocus,translationService}.test.ts; tests/node-colors.spec.ts; tests/local-ui-resources.spec.ts. Audit scripts: scripts/audit_node_colors_v2.py; scripts/audit_node_colors.py now dispatches to v2; scripts/capture_node_colors.cjs accepts an output directory. Historical v1 report marked superseded; all v2 artifacts are in this directory.

Reproduce: python scripts/audit_node_colors_v2.py; node scripts/capture_node_colors.cjs docs/accessibility/node-colors-v2. Browser checks use playwright.external.config.ts against user-managed servers. No server was restarted by the agent.
