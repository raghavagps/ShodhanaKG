# Results-first presentation

Graph answers now use `ResultsPresentation` after the existing evidence validator.
The short introduction and numbered entities are deterministic; Llama does not
enumerate, select, or rewrite results. Historical conversational generation and
its evaluation tests remain available, but are not used for current graph answers.
No question-specific scientific answers are embedded in this presentation code.

The UI previews five unique target entities and offers `View all N results →`.
Entities with the same typed identity are grouped; all supporting fact IDs,
citations and original record indices remain attached. The live Psoriasis capture
contains 19 unique symptoms and 38 evidence records. Counts come from retrieval,
not from constants. Canonical nodes group by ID; legacy rows lacking IDs group by
type and exact label. Results retain retrieval order.

Clicking any numbered citation opens and focuses its evidence card, including
records beyond the first ten. Compact cards display entity labels, semantic
relationships, publication identifiers, and arbitrary nonempty supplied properties.
Zero and false are meaningful values. Empty strings, nulls and `Not supplied`
placeholders are omitted. Full records and attribution metadata remain available
under Technical details. JSON and SSE carry the same presentation fields; session
history preserves them. Older saved conversations retain their original renderer.

For canonical paths, relationship labels derive from validated source and target
node types in actual edge direction. Raw edge type, source/target IDs and edge ID
remain in technical metadata. Flat legacy paths use the directed type-pair catalog
in `api/core/relationship_metadata.json`, captured read-only from the current KG.
The capture contains schema pairs only, never entity names or answer facts. Refresh
it when the schema changes using `api/tests/capture_relationship_metadata.php` with
a new output path and review the result. Ambiguous or unavailable pairs are omitted,
not guessed. `RESOLVED_BY` could not be inspected in this database capture.

Legacy endpoints sometimes return projected record properties rather than full
edges. The presentation preserves those record-level scopes and cannot recover
omitted properties or edge IDs. It does not pretend that a multi-hop record's
reference identifies an individual edge. Canonical paths retain exact per-edge
scopes. Retrieval, evidence correlation, factual oracles and validation are unchanged.

Regression coverage: `test_results_presentation.php`, the existing evidence-builder
and conversational validation suites, `ResultsMessage.test.tsx`, and the results-first
case in `tests/chatbot-evidence.spec.ts`. The Psoriasis fixture is a live API capture
used only for tests, not an answer source.
