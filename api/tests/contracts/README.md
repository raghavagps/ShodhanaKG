# Software contract 8 (2026-10-09)

These are versioned software expectations, not new scientific ground truth.
The historical files under `../fixtures/` are unchanged.

- `chatbot_evaluation_cases_v8.json` preserves all 82 v7 cases. Only `eval_051`
  changes: the approved introductory Shodhana FAQ must succeed, identify the
  `shodhana` FAQ, and carry no KG citations or retrieved facts.
- `conversation_evaluation_cases_v4.json` preserves all v3 questions and turns.
  Only the broad Copper description expectation changes: it must return the
  complete public properties of snapshot node `#5:24`, with original keys,
  exact values, type and RID, and no invented relationship evidence.
- Explicit missing-property requests still require `no_data`. Factual retrieval,
  attribution, publication identities and frozen graph references are unchanged.

The current benchmark defaults to contract 8. Select `--contract-version 7`
to retain the prior routing/conversation expectations. Do not compare adjusted
abstention denominators as if they represented unchanged sample definitions.
The standalone node-property evaluator follows the approved current contract;
historical captured outputs and audit scores remain in their original reports.
