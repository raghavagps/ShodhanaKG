# Entity resolution and query understanding

The chatbot and Explorer search share `api/core/EntityResolution.php`. Resolution selects an existing KG identity; it never creates a node, relationship, fact, or publication reference.

## Resolution policy

1. Exact RID or normalized label, retaining all matching identities.
2. Source-checked aliases bound to the current RID, type, and label.
3. Conservative spelling correction: one edit for 4?7 characters, two for longer names, normalized distance at most 0.20, and a unique best candidate. Short names are not automatically corrected.
4. Less certain spelling matches and duplicate labels require clarification. Explicit type qualifiers can disambiguate; an incompatible qualifier cannot be overridden.
5. Reviewed broader concepts offer narrower KG choices. They do not establish equivalence or silently change Indication into Disease.

Schema type names and query-intent vocabulary are excluded from fuzzy mention matching. Multi-entity questions retain their separate constraints. The live index is cached only within a request.

## Interfaces

- `GET /api/resolve_entity.php?q=...&type=...`: structured resolution; optional type must be a raw supported schema type.
- `GET /api/search.php`: existing node DTO with resolution/match metadata. All Explorer autocomplete clients use this service. Domain filters still apply.
- `POST /api/chatbot_response.php`: optional `selected_entity` plus existing `conversation_context`; the server revalidates a selection against the pending question. JSON and SSE expose resolution, clarification, and understanding metadata.
- Conversation state retains canonical identity/type and pending intent separately from factual evidence. A clarification choice does not itself provide evidence.
- Legacy typed retrieval endpoints retain exact name/RID contracts. Natural-language matching happens upstream; direct endpoint callers should resolve first.

## Hybrid language understanding

Deterministic target parsing runs first. Llama fallback can propose only literal question mentions and an allowed target type. Unknown fields, invented mentions, invalid types, conflicting explicit intent, unresolved entities, and invalid JSON are rejected. The timeout is ten seconds; `KG_NLU_MODEL_ENABLED=false` disables fallback. An implicit target proposed by the model requires confirmation before factual retrieval. Model proposals are never evidence or ground truth, and do not enumerate answer results.

## Terminology maintenance

`api/core/terminology_catalog_v2.json` replaces the two-node catalog with source-backed mappings for 416 distinct KG nodes. Of these, 357 have alternate terms beyond their canonical labels. Inputs are the pinned NCI Thesaurus 26.08e release and explicit name/abbreviation fields in a frozen KG snapshot. The old catalog remains archived.

The catalog contains 238 KG-node-to-NCI mappings, KG-listed alternate-name fields on 181 nodes, and 37 explicit parenthetical abbreviations. These groups overlap. The builder does not map ambiguous source concepts or incompatible semantic categories, extract synonyms from descriptions, or convert Indication into Disease.

Canonical KG labels take precedence over aliases within the explicitly requested type. Stored alternate-name fields are not canonical labels. Source-backed broad phrases are matched as complete phrases before shorter contained names or fuzzy guesses. Uncertain aliases, short abbreviations, and KG-listed alternate names require confirmation; a typo cannot bypass that requirement. Known broader terms offer existing KG nodes as choices, never facts about all their descendants.

Regenerate using `api/tests/build_terminology_catalog.py` with the pinned archive and snapshot. Run `verify_terminology_sources.py`, `test_build_terminology_catalog.py`, `test_terminology_expansion.php`, and `evaluate_terminology_expansion.py` before replacing the runtime catalog. Source archive, concept/path evidence, per-node coverage and rejected candidates are in `api/tests/fixtures/terminology_expansion_2026_09_25_v1/`. Label, type, RID and source-field bindings must still match at runtime; stale bindings fail closed. There are no runtime terminology-network requests.

This is source checking, not clinical expert approval or a universal medical thesaurus. The full [coverage and regression report](audits/terminology_expansion_2026_09_25_v1/REPORT.md) identifies the remaining domains needing reviewed terminology. No external definitions are used to fill missing KG node properties.

## Validation and scope

See [implementation audit](audits/entity_resolution_2026_09_25_v1/REPORT.md). Independent factual fixtures remain unchanged. Revised routing/presentation/conversation fixtures explicitly record the safer ambiguity contract; original fixtures and their regression results remain available. Graph display filters, graph size caps, mobile overflow, and table count issues are outside this phase.
