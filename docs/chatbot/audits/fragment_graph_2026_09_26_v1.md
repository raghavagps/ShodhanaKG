# Minimalist properties, Compound graph connectivity, and fragment intent — 2026-09-26

## Changes and root causes

1. **Property styling reverted.** The shared PropertyList now uses compact two-column rows, small uppercase labels, small body text and thin separators, without card backgrounds or rounded containers. Both nodes and edges use this renderer. Structured values, zero, false, reference actions and exact edge association are preserved.

2. **Compound graph display corrected.** Visibility filters removed edges without pruning the nodes disconnected by those filters. API display caps can also return an otherwise valid neighbor without a connecting returned edge: the saved capped Copper response contains three nodes but only one edge. In addition, React Query's previous-result placeholder could display the old selection's graph while a new request was pending.

   Compound Explorer now builds the displayed connected components from the current selected seeds after applying visibility filters and limits. It retains each explicitly selected seed, including a genuinely isolated seed, and all nodes reachable through the remaining visible edges. Multiple explicitly selected components are supported. Reachability works in both directions without changing stored edge direction, properties or IDs. Previous-query placeholder data is not rendered as the new selection's graph. This is a display correction; the KG and API records remain unchanged. Unfiltered Copper had five nodes and 47 edges with no isolated nodes in the initial API check, so arbitrary KG disconnection was not assumed.

3. **Requested method detection corrected.** The fragment “Shodhana method to purify lead” missed the output-noun detector because its fragment grammar did not accept the Shodhana/purification qualifiers. The fallback matched “purify” and selected a traversal ending in purified compounds. The saved baseline returned four purified-product labels.

   Qualified noun fragments now use the same output-type interpretation as full questions. The generic purification-keyword fallback to products was removed. “Purify Lead” and “Purification of Copper” now request clarification, while explicit method, media, process and product requests retain their respective meanings. Explicit purified-product noun detection was added. No compound-specific answers, identifiers or scientific facts were added.

## Verification

- Pre-edit baseline: 73/73 frontend tests; saved failing Lead answer and capped Copper graph.
- New live checks: 49/49. Five equivalent fragments across Lead, Copper and Sulphur, checked through JSON and SSE against independent raw UNDERGOES node/edge IDs; 15 parity comparisons; four ambiguous-verb abstention checks. Expectations do not come from Llama or chatbot answers.
- Method/result contract unit checks: 28 passed, including output-type precedence and ambiguous purification requests.
- Frontend: 77/77 tests passed. Four new connectivity tests cover caps/orphans, visibility changes, multiple/isolated seeds, reverse connectivity, retained edge objects and stale/unknown seeds. Shared minimalist markup and complete values are also tested.
- Lint passed with zero errors and 19 existing warnings. Production build passed.
- Browser group: 51/60 passed. All five new tests passed: relationship-filter connectivity, a deliberately delayed selection request with no old graph displayed, and fresh fragment requests for three compounds. Existing typed navigation, properties, greeting, schema and route-smoke checks passed. The nine failures are the previously recorded mobile horizontal-overflow cases on the three Explorer landing/graph/table routes.
- Fresh benchmark: routing 82/82; factual retrieval 168/168; attribution 121/121; presentation 24/24; conversation runs 108/108. No JSON/SSE parity failures, false refusals in the evaluated answerable set, or KG drift.
- Additional passing checks: 6,386 terminology assertions; 45 conversation-routing/context assertions; deterministic presentation and 160 legacy chain cards; 95 legacy answers with 2,961 exact edge scopes.

The full browser suite is not green because of the existing mobile overflow failures. Language understanding remains bounded; unclear output types lead to clarification. This work does not establish scientific/clinical validity. A user-managed dev-server restart and its final smoke check are pending at report creation.

## Files

Production changes:

- `api/core/QueryUnderstanding.php`
- `api/core/QuestionRouter.php`
- `src/components/explorer/PropertyList.tsx`
- `src/lib/connectedGraph.ts` (new)
- `src/pages/explorers/CompoundGraphView.tsx`

Regression changes:

- `api/tests/test_method_result_contract.php`
- `api/tests/evaluate_fragment_methods.py` (new)
- `src/components/explorer/PropertyList.test.tsx`
- `src/lib/connectedGraph.test.ts` (new)
- `tests/fragment-graph.spec.ts` (new)

Audit artifacts: `api/tests/fixtures/fragment_graph_2026_09_26_v1/`, including the pre-edit source archive, baseline Lead response, capped Copper response, independent fragment results and fresh benchmark. Existing independent factual/reference fixtures and KG data were not edited.
