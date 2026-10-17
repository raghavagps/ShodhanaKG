# Compound dragging and minimalist edge details — 2026-09-26

The prior connectivity change introduced a render loop. `useGraphFilters` reconstructs its filter object on every render; memoizing selected IDs against that object did not stabilize the array. The connectivity projection consequently produced new graph arrays, the translation hook updated state again, and GraphCanvas repeatedly removed/recreated elements. The pre-fix real-mouse regression recorded 300 node removals during one drag attempt.

Compound Explorer now memoizes IDs against their serialized values. The graph remains stable during ordinary renders and dragging. Connectivity filtering, explicit isolated seeds, selection changes, actual IDs and edge directions are preserved.

Node and edge property rows already shared the minimalist renderer, but the edge panel retained a separate decorated relationship card and reference container. Source and Target now use the same compact row renderer. Edge panel spacing matches the node panel; the extra animation/card was removed. Its reference section uses compact styling while retaining the green Open ID action and namespace-specific links. No property values, references, backend behavior or KG records were changed.

Files changed:

- `src/pages/explorers/CompoundGraphView.tsx`
- `src/components/explorer/DetailsPanel.tsx`
- `src/components/explorer/ReferenceLinks.tsx`
- `tests/compound-interaction.spec.ts` (new)

Validation:

- New browser regressions: 4/4 passed. Real mouse dragging on Copper, Lead and filtered Copper; zero node removals during dragging; unchanged element IDs; measurable persistent node movement. Computed node/edge label and value fonts, font sizes, row padding, column layout and borders match.
- Frontend: 77/77 tests passed. Lint passed with zero errors and 19 existing warnings. Production build passed.
- Browser regression group: 55/64 passed, including interaction, typed navigation, filtering, stale-response handling, properties, schema, greeting, routes and chatbot method requests. The same nine existing mobile horizontal-overflow tests failed on Explorer landing/graph/table routes.
- Backend was not changed in this follow-up, so the preceding factual benchmark was not unnecessarily repeated. Live browser chatbot regression requests passed.

A user-managed dev-server restart and post-restart smoke check are pending at report creation. No automatic server restart was performed.
