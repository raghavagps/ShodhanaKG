# Software contract 8

These are versioned software expectations, not new scientific ground truth.
Baseline fixture files under `../fixtures/` are unchanged.

- `chatbot_evaluation_cases_v8.json` covers all 53 allowlisted endpoints. In `eval_051`,
  the approved introductory Shodhana FAQ must succeed, identify the
  `shodhana` FAQ, and carry no KG citations or retrieved facts.
- `conversation_evaluation_cases_v4.json` covers conversation scenarios and turns.
  The broad Copper description expectation returns the
  complete public properties of snapshot node `#5:24`, with original keys,
  exact values, type and RID, and no invented relationship evidence.
- Explicit missing-property requests still require `no_data`. Factual retrieval,
  attribution, publication identities and frozen graph references are unchanged.

The benchmark runner defaults to contract 8. Select `--contract-version 7`
to retain the prior routing/conversation expectations. Do not compare adjusted
abstention denominators as if they represented unchanged sample definitions.
The standalone node-property evaluator follows the approved contract specification;
baseline captured outputs and audit scores remain in their original reports.
