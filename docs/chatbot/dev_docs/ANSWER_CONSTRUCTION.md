# Evidence-Based Answer Construction

Answer presentation details: [Conversational Answers and Descriptive Sources](CONVERSATIONAL_ANSWERS.md).

Graph questions use `EvidenceAnswerBuilder` through `ResponseGenerator`. The builder formats live retrieval records directly. It does not import evaluation fixtures, look up saved answers, or depend on Ollama availability for factual construction. Results reflect the underlying graph database records. The static FAQ path operates separately.

## Answer Format and Attribution

- Every distinct result appears in the answer summary with numbered evidence-record references. Structured paths retain entity type to distinguish Process from Shodhana_Method.
- The answer displays detailed records with an explicit shown and total count. Records remain accessible in the expandable Evidence / Retrieved Knowledge panel, numbered to match the summary. The panel supports direct record-number navigation and displays directed relationships with their evidence and properties. Raw records remain viewable.
- Each structured edge is displayed in its stored direction, with its own reference identifiers and properties. Separate paths are never combined into a shared-study claim.
- Path connectivity, reference and property agreement, shared-reference consistency, seed identity, and related-entity identity are validated before output. Conflicting labels or types for the same node ID are rejected. Inconsistent records produce an error instead of partial factual text.
- Flat endpoint responses retain field labels, property groups, validation properties, and safety-evidence scopes. The formatter does not invent missing per-edge provenance.
- JSON and SSE transports deliver the same constructed answer payload. SSE emits text only after construction succeeds, then sends that answer and status in the done event.
- Chatbot health verifies ArcadeDB connectivity.

This ensures faithful presentation of graph data. The raw `pubmed_id` field can contain other identifier types; values are presented as stored.

## Verification

The test suite validates answer construction behavior:
- `test_evidence_answer_builder.php` checks reference target coverage, corrupt evidence rejection before streaming, disconnected paths, reference mismatches, and legacy scopes.
- `test_response_generator.php` verifies that an unavailable model URL does not disrupt factual answer assembly.
- Captures and transport checks verify graph fidelity across HTTP and streaming interfaces.

```sh
php webservice/api/tests/test_evidence_answer_builder.php
php webservice/api/tests/test_response_generator.php
php webservice/api/tests/run_evaluation_cases.php --http
php webservice/api/tests/run_evaluation_cases.php --http --fixture=webservice/api/tests/fixtures/factual_evaluation_cases.json
```
