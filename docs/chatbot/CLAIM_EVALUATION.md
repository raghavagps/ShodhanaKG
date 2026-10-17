# Answer claim evaluation

The full 12-case run and AI-assisted review are available in [Claim evaluation results](./CLAIM_EVALUATION_RESULTS.md). The one-case pilot below is retained as historical pipeline evidence.

This phase checks mapped answer claims against the independent frozen graph references. It does not use the response's retrieved context as ground truth. It does not automatically infer claims from prose, certify scientific truth, or replace expert review.

## Run and review

```sh
python -m unittest discover -s api/tests -p "test_evaluate*.py"
php api/tests/export_ragas_dataset.php --fixture=api/tests/fixtures/factual_evaluation_cases.json --filter=eval_traversal_therapy_formulation --output=NEW_ANSWERS.json
python api/tests/evaluate_claims.py --answers NEW_ANSWERS.json --prepare --output=NEW_REVIEWS.json
python api/tests/evaluate_claims.py --answers NEW_ANSWERS.json --reviews NEW_REVIEWS.json --output=NEW_REPORT.json
```

Remove the export filter to generate all 12 cases. Export invokes the existing live chatbot and model. The claim tool refuses existing output paths. Missing answer cases are listed in reports, so a one-case pilot cannot be mistaken for a complete benchmark. Preserve errors in the answer file; they are execution/generation failures, never correct abstentions or excluded rows.

Review packets include the exact answer and question. To complete a review, set `review_status` to `reviewed`, identify the reviewer, and annotate every factual assertion. Set `all_factual_claims_mapped` to true only after examining the entire answer. Select node IDs and directed edge types from the independent `factual_evaluation_cases.json`, not from live retrieval output. Do not change the answer/reference hashes: an altered answer or reference invalidates the review.

Each positive relationship assertion has this shape (illustrative IDs only):

```json
{
  "start": 0,
  "end": 21,
  "quote": "Copper uses Cow Milk.",
  "kind": "relationship",
  "edges": [
    {"source": "#1:0", "target": "#2:0", "type": "UNDERGOES"},
    {"source": "#2:0", "target": "#3:0", "type": "USES_MEDIA"}
  ],
  "citation": {"type": "pubmed_id", "value": "12345678"},
  "properties": []
}
```

Offsets are Python Unicode character offsets (start inclusive, end exclusive); the quote must match exactly. Map every cited reference as a separate claim annotation if needed. Omit `citation` when the answer makes no citation claim. Edge IDs are not required: direction, type, source and target must match a contiguous segment of one reference path. Keep edges in the reference path's order, even for reverse traversal; source/target retain their stored graph direction.

For a dose, duration or other literal property, add `{"edge_index": 0, "key": "Duration", "value": "3 Days"}` under `properties`, on the associated edge. Values must match exactly. Unit conversions and paraphrases require review; the tool does not infer equivalence. A cited multi-edge relationship must share that citation on every edge. Properties and citations must match the same candidate path, preventing cross-study mixing.

Use `kind: "unresolved"` with quote/offsets and explanatory notes for negations, efficacy/safety claims, general scientific statements, ambiguous entity mapping, or statements not expressible as positive graph relationships. Do not force these into relationship mappings. Unsupported means absent from the selected graph reference, not scientifically disproven.

## Interpretation and current evidence

- `supported_fraction` measures graph-supported annotations divided by all mapped annotations, only for complete reviews without unresolved claims. It is conditional on reviewer mapping quality; it is not an automatic factuality score.
- Pending, incomplete, and unresolved reviews receive no score. Omissions from the generated answer require a separate answer-completeness review; claim support is not recall of all expected facts.
- Wrong relationships, citation attribution and property values receive separate verdicts. Status expectation, routing correctness, and generation reliability remain separate. An intentionally expected grounding failure still counts as a generation failure.
- The existing mention-recall scorer remains a lexical proxy. It now distinguishes expected final status from reliability and no longer calls a wrong-route response a correct abstention.
- `fixtures/claim_phase_answers.json`, `claim_phase_reviews.json` and `claim_phase_report.json` contain a **one-case live pilot**, pending review, with the other 11 cases explicitly missing. They are pipeline evidence, not a completed benchmark.
- Export metadata records capture time, fixture hash, HTTP status, transport and local generator-source hash. The server does not report model identity, so it is explicitly unknown. The local source hash is not proof of the server's deployed version.

Before publishing a benchmark: review all claims, resolve scientific questions with domain experts, run the remaining cases, and record the actual deployed model/prompt/database versions. The prior 55-row saved RAGAS export remains historical.

## Extended review annotations

Set `reviewer_kind` to `ai` or `human` to identify the mapping source. AI review does not substitute for domain-expert adjudication. A mapped edge may include `id` when the answer cites a graph edge record; the ID must match that same directed relationship.

For a statement that several complete paths belong to one study, use `kind: "shared_study"` and `paths: [[...edges of first path...], [...edges of second path...]]`, plus the usual quote and offsets. Every path must exist, and all paths must share a matching evidence identifier. Optional `citation` restricts the shared identifier. Do not assume shared study merely because each path is independently correlated.

A reviewed answer that refuses in prose can set `answer_behavior: "refusal"`. It is a false refusal when the reference expects success, even if backend status is success. Hash and reviewer checks still apply. Known unsupported assertions take precedence over incomplete review in the outcome, but unresolved claims keep the fraction unscored.
