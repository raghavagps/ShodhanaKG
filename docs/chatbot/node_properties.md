# Stored node-property answers

Node-information requests use `NodePropertyAnswer`, a deterministic path separate from relationship retrieval and language-model generation. It applies to every vertex type returned by the validated KG schema.

- Broad requests such as “What is X?”, “Tell me about X” and “Show details about X” return all nonempty public stored node properties. Explicit “Describe X” or description/definition requests remain description-specific, including stored variants such as `Therapy_Description` and `Description.4`. A node label or classification is never substituted for a missing description.
- ?What is the PROPERTY of X?? requests a stored field. Field matching ignores case and separators; values and original property keys are retained. ?Show me the properties of X? lists nonempty stored fields.
- Explicit ?property/attribute/field NAME? requests distinguish a node property from a relationship concept. Ordinary symptoms, methods, therapies and other traversal questions retain relationship retrieval.
- Missing, null, whitespace-only and empty-array fields return `no_data` and explicitly name the unavailable property and node. Zero and false are valid values.
- Database metadata and adjacency pointers are excluded. The property reader receives original node records, not normalized graph DTOs, edges or related nodes.
- `node_evidence` identifies the exact node RID/type, requested property, and original stored key/value pairs. Node answers do not fabricate edge citations or publication support. Stored text is KG content, not independently established scientific truth.
- Existing conservative entity resolution, ambiguity choices and conversation identity checks remain in force. Property words are isolated from entity matching; ?weight? in a property request cannot replace the selected subject with a Weight node.
- JSON and SSE use the same deterministic answer builder. A separate, bounded curated FAQ catalogue answers introductory Shodhana/Panchakarma concepts and platform help; these responses are labelled background/help, carry no KG edge evidence and never supply missing properties for specific entities. See `updates_2026_10_07.md` for the current contract and regression results.

Tests: `api/tests/test_node_property_answer.php` (all 17 vertex types), `api/tests/evaluate_node_properties.py` (independent frozen-node comparison and live JSON/SSE/context checks), and `tests/node-properties.spec.ts` (browser display and follow-up).

No KG nodes, edge records, references or existing independent factual fixtures were edited. Property wording is deterministic and bounded; unsupported phrasing still asks for clarification rather than inventing an answer.

## Evaluation contract

The older routing contract rejected molecular-weight/property questions before resolving nodes, and the older conversation contract treated ?Tell me about Copper? as a missing intent. Version 6 routing and version 3 conversation fixtures preserve the original questions and change only the relevant expected statuses: missing properties become `no_data`; ambiguous or unknown identities still clarify or remain unresolved. Personal-dose recommendations remain unsupported. No factual answer fixtures were altered.

The exact changes are archived in `api/tests/fixtures/node_properties_2026_09_25_v1/contract_changes.json`. The earlier benchmark run is retained, including the process/therapeutic-use routing regressions it exposed and that were subsequently fixed. The updated assertion helper validates node RID/type/property association instead of incorrectly demanding a relationship-results envelope for a node answer.

New checks: 218 PHP assertions, 23 independent-snapshot HTTP/context/parity checks, and one live browser flow. Lint had no errors (19 existing warnings), build passed, and 43 frontend unit tests passed. The route/mobile/browser selection passed 11 tests; nine pre-existing Explorer mobile-overflow failures remain outside this change. Broad benchmark artifacts are in the same versioned fixture directory. These checks establish stored-data fidelity, not scientific validation.

Final versioned regression outcomes: 82/82 routing, 168/168 factual, 121/121 attribution, 24/24 presentation, and 108/108 conversation turn assertions passed. The overall benchmark exited nonzero: two separate JSON/SSE requests differed in model-fallback diagnostics (unavailable versus rejected) for missing/unknown entities; their answers, statuses and conversation context matched. The new node-property transport checks had no parity differences. No claim of a wholly clean regression suite is made.

Benchmark timing overlapped the final explicit-field-name precedence adjustment. Its `source_changed_during_run` flag is retained, so this is an implementation regression record rather than an immutable paper-ready benchmark. The final precedence behavior was checked by the 218-assertion PHP suite. KG snapshot drift was checked by the benchmark; KG writes were not performed.
