# UI and stored-node refinements ? 2026-10-07

Implemented locally. No production, KG, publication identity or independent reference-fixture changes. No full benchmark was run. Raw logs, API captures and browser screenshots are in `.audit-temp/ui-refinements/` on D:; build output is the existing local `dist/` directory.

## Changes and root causes

1. Graph limits: 100/150/200/300/All, default 300; automatic progressive paging completes eligible edges as well as nodes. Selector sits above the relevant category legend, outside the canvas. Existing in-graph boxes show displayed/eligible counts. Pagination-in-progress is not treated as truncation. All retains the existing 100,000-node request ceiling and backend safety boundaries, not an unlimited browser guarantee. Seed/path and category-selection algorithms are unchanged.
2. Limit changes previously discarded placeholder data in the views; now same-query budget transitions retain graph data. Placeholder retention cannot cross entity/filter/domain query boundaries. Selection and positions passed browser checks.
3. Advanced Search was outside primary navigation; moved after About Us and added a mobile menu entry. Filters previously collected all types before domain filtering; facet metadata now intersects stored types with existing domain/schema definitions and validates incompatible submissions. Existing cross-domain edge search semantics remain intact.
4. Search form state was component-local. Versioned session storage now preserves draft/submitted filters, current cursor and page size; stale cursors restart pagination with a message. Sizes 25/50/100 are selectable; changes reset pagination. API accepts validated integers 1?100 to preserve existing clients. ID number replaces Exact identifier. Visible timestamps removed; result property typography is 12/14 px without changing other property panels.
5. Restored the five original KG starter cards, plus Shodhana and Panchakarma introductions. Other curated FAQs remain available when explicitly typed, as requested. FAQ sources remain separate from KG evidence.
6. Only Shodhana Method statistics row descriptions are hidden; stored descriptions remain available elsewhere.
7. An operator-precedence error made distinct statistics resolve to the wrong label key. Explicit metric mappings now distinguish Total Nodes, Total Relations and Distinct References. The legacy API totalStudies value counts namespace-qualified identifiers, not independently established studies; calculations were not changed.
8. Diagnosis normal and accessible colours were identical, with duplicate overrides in the provider and views. The provider now uses the shared registry; diagnosis is #56B4E9 in colourblind mode and retains its exact original purple in normal mode. Relevant graph controls, table swatches and help swatch follow the same token. Existing node shape/size/selection/hover behaviour and relationship colours are unchanged.
9. How to Use documents stored-reference searching and supported filters/navigation without claiming external publication discovery/full-text search.
10. Acorus calamus Linn. resolves correctly to #5:6. Its broad stored properties were available already; Describe was restricted to description-only. Broad Describe/Explain/Define/About/What-is requests now return public stored properties. Explicit named-field requests stay field-specific. Live Acorus data surfaced no non-empty description: an explicit description request correctly remains no_data. Its stored pharmacological activity, common/Ayurvedic names, type and source are returned without relabelling these as a description. No question/entity-specific answer code was added.

## Verified checks

| Check | Result |
|---|---|
| Baseline frontend | 99/99 tests |
| Baseline planned-update / node-property checks | 1,993 / 218 assertions passed |
| Final lint | 0 errors; 9 warnings (8 existing UI fast-refresh warnings and one unused directive in the local plotting environment) |
| Typecheck and production build | Passed |
| Final frontend, reduced concurrency | 101/101 tests, 30 files |
| New UI refinement PHP checks | 1,437 assertions passed on current PHP and PHP 5.6 |
| Reference search | 11/11 on current PHP and PHP 5.6 |
| Stored-node unit checks | 218/218; approved broad-description expectation updated |
| Existing graph pagination / FAQ checks | 1,993/1,993 |
| Provenance routing | 33/33 |
| Existing presentation checks | 160 passed |
| Traversal / provenance contracts | 195/195 |
| Conversation routing | 45/45 |
| Structured context | 8 assertions plus missing-referent clarification passed |
| Conversation memory smoke | Passed |
| Live node/FAQ HTTP checks | 10 scenarios ? JSON/SSE; 20 responses, parity checks passed |
| Live provenance / independent attribution | 2 unittest methods passed; 12 JSON/SSE responses across Copper, Lead and Sulphur |
| Frozen-reference presentation evaluation | 24/24 cases |
| Chromium updated-feature checks | 7/7 distinct tests |
| Chromium existing colour/identity/node-property checks | 5/5 |
| Chromium existing route/mobile smoke | 19/19; smoke command also repeated the new mobile test successfully |
| WebKit updated-feature checks | All 7 test bodies reported passed; combined runner did not exit cleanly (see limitation) |

Compound-with-description behaviour is covered by explicit synthetic unit nodes across all 17 types; the inspected independent Compound snapshot did not provide a non-empty description example. Live stored descriptions were checked using disease/therapy records. Ambiguity and missing-referent behaviour are covered by the existing routing/context tests. These are software/data-fidelity checks, not scientific or clinical validation.

## Attempts and limitations

- Initial browser checks exposed two new-test locator issues, corrected without weakening assertions. Search controls also received explicit accessible labels.
- One concurrent frontend run timed out in an unchanged results-message test. The complete suite passed with two workers and the same assertions/timeouts; the failing log is retained.
- One combined WebKit run reported all six test passes but did not exit. Only that test process was interrupted; no development services were stopped. The fresh seven-test run also reported all test-body passes but did not terminate, so it too was interrupted after waiting. Combined WebKit runs are not counted as clean suite passes. The standalone statistics/pagination WebKit check finished successfully. This runner-shutdown limitation remains unresolved; no application failure was reported by those seven test bodies.
- Search state lasts for the browser tab/session and depends on session storage being available. Disabling storage leaves normal searching functional.
- All graph loading remains subject to current backend/browser capacity. This work does not establish performance at the safety ceiling.
- Historical frozen routing/conversation fixtures contain previously documented FAQ/broad-property contract differences. They were not rewritten or used to claim a new full release gate.
- The complete release benchmark and production deployment remain outside this change set.

## Files changed

- `src/hooks/useProgressiveGraph.tsx`
- `src/components/explorer/GraphStatus.tsx`
- `src/pages/explorers/DiseaseGraphView.tsx`
- `src/pages/explorers/CompoundGraphView.tsx`
- `src/pages/explorers/IntegratedGraphView.tsx`
- `src/pages/AdvancedSearch.tsx`
- `api/core/ReferenceSearch.php`
- `src/components/layout/TopNav.tsx`
- `src/components/chatbot/ChatSuggestions.tsx`
- `api/core/NodePropertyAnswer.php`
- `src/pages/explorers/CompoundTableView.tsx`
- `src/pages/explorers/DiseaseTableView.tsx`
- `src/pages/ModeSelection.tsx`
- `src/data/nodeStyles.json`
- `src/context/ColorblindProvider.tsx`
- `src/pages/HowToUse.tsx`
- `src/lib/nodeStyles.test.ts`
- `src/components/chatbot/ChatSuggestions.test.tsx`
- `src/components/explorer/GraphStatus.test.tsx`
- `src/hooks/useProgressiveGraph.test.tsx`
- `api/tests/test_node_property_answer.php`
- `api/tests/test_ui_refinements.php`
- `api/tests/test_node_properties_http.py`
- `tests/planned-updates.spec.ts`
- `tests/ui-refinements.spec.ts`
- `docs/chatbot/ui_refinements_2026_10_07.md` (this report)
- Generated `dist/` build artifacts and D:-located `.audit-temp/ui-refinements/` verification outputs.

## Reproduction

Run commands from the repository with TEMP, TMP and npm_config_cache directed to `.audit-temp/ui-refinements/` on D:. Keep Python bytecode disabled (`python -B`).

```text
npm.cmd run lint
npm.cmd run build
node node_modules/vitest/vitest.mjs run --maxWorkers=2
php api/tests/test_ui_refinements.php
php api/tests/test_reference_search.php
php api/tests/test_node_property_answer.php
python -B api/tests/test_node_properties_http.py
python -B api/tests/test_provenance_routing_http.py
node node_modules/@playwright/test/cli.js test tests/planned-updates.spec.ts tests/ui-refinements.spec.ts --project=chromium --workers=1 --reporter=list --output=.audit-temp/ui-refinements/recheck
```

Use new artifact filenames for independent evaluation captures; their evaluators deliberately refuse overwrite. A user-managed dev-server restart is required after changes/builds under AGENTS.md.
