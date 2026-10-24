# Evidence-based answer construction

Current answer presentation: [Conversational answers and descriptive sources](./CONVERSATIONAL_ANSWERS.md). The earlier deterministic rendering and its historical measurements below remain documented for comparison.

Graph questions now use `EvidenceAnswerBuilder` through `ResponseGenerator`. The builder formats live retrieval records directly. It does not import evaluation fixtures, look up saved answers, or call Ollama. The same question can produce different results when the database changes. The existing static FAQ path remains separate and still needs content review.

## Answer format and attribution

- Every distinct result appears in the answer summary with numbered evidence-record references. Structured paths retain entity type to distinguish Process from Shodhana_Method.
- The answer shows the first 12 detailed records with an explicit shown/total count. Every record remains in the expandable Evidence / Retrieved Knowledge panel, numbered to match the summary. The panel shows ten records at a time, supports direct record-number navigation, and displays directed relationships with their own evidence and properties. Raw records remain expandable.
- Each structured edge is displayed in its stored direction, with its own reference identifiers and properties. Separate paths are never combined into a shared-study claim.
- Path connectivity, reference/property agreement, shared-reference consistency, seed identity and related-entity identity are checked before output. Conflicting labels/types for the same node ID are rejected. Inconsistent records produce an error instead of partial factual text.
- Flat endpoint responses retain field labels, property groups, validation properties and safety-evidence scopes. The formatter does not invent missing per-edge provenance for older contracts.
- JSON and SSE use the same fully constructed answer. SSE emits text only after construction succeeds, then sends that answer and final status in the done event.
- Chatbot health now tests ArcadeDB readiness. Ollama is no longer an availability dependency for graph answers.

This improves faithful presentation of graph data. It does not verify scientific truth, correct source-data mistakes, or certify older retrieval logic. The raw `pubmed_id` field can contain other identifier types; values are presented as stored rather than advertised as verified PubMed links.

## Verification — 2026-09-23

- All 82 HTTP evaluation cases passed, including final response status and routing/retrieval checks.
- All 12 independent factual-reference answers succeeded, listing all 162 case-specific reference targets, compared with 32 named targets in the earlier model-generated run.
- The previous Alopecia HTTP-failure case and Laghu Sutshekhar false-refusal case now return graph answers. This demonstrates current behavior, not a diagnosis of the historical HTTP 500.
- Live JSON and SSE answers matched for all 12 cases; final streamed answers equal their emitted text.
- `test_evidence_answer_builder.php` checks reference target coverage, corrupt evidence rejection before streaming, disconnected paths, reference mismatches and legacy scopes. A deliberately unavailable model URL does not affect construction.
- Captures: `api/tests/fixtures/constructed_answers.json`; target/transport checks: `constructed_answer_checks.json`. These are graph-fidelity checks, not human expert answer-quality scores.

```sh
php api/tests/test_evidence_answer_builder.php
php api/tests/test_response_generator.php
php api/tests/run_evaluation_cases.php --http
php api/tests/run_evaluation_cases.php --http --fixture=api/tests/fixtures/factual_evaluation_cases.json
```

Future improvements can add a clearer expandable evidence UI and bounded optional explanations. Any model paraphrase should remain separate from the authoritative fact list and pass claim/evidence checks before display. Expert review of scientific references and FAQ content remains necessary.
