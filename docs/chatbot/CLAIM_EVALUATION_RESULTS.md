# Full claim-level evaluation — 2026-09-23

All 12 factual-reference questions were submitted once to the live JSON chatbot. No failed request was retried or removed. This is an **AI-assisted graph-evidence review**, not human/expert certification or a scientific benchmark. The reference snapshot remains unchanged.

| Case | Outcome | Named targets / reference targets |
|---|---|---:|
| Abhyanga formulations | General-description review pending | 6 / 35 |
| Cow Milk methods/processes | Method/Process wording review pending | 4 / 6 |
| Process following Bhavana | Mapped claims supported | 1 / 1 |
| Methods preceding Marana | Mapped claims and eight cited edge IDs supported | 1 / 8 |
| Producer of Shodhita Jaypala | Method/Process wording review pending | 1 / 2 |
| Substances removed by Swedana | Mapped claims and ten cited node IDs supported | 10 / 40 |
| Process following Marana | Mapped claims supported | 1 / 2 |
| Processes preceding Marana | Mapped claims supported | 1 / 5 |
| Copper media with evidence | Five relationships and reference 241564 supported | 5 / 10 |
| Compounds using Cow Milk in the same study | **Unsupported shared-study assertion** | 2 / 20 |
| Alopecia formulations with evidence | **HTTP 500 / export_error** | 0 / 32 |
| Diseases using Laghu Sutshekhar with evidence | **False refusal despite success status** | 0 / 1 |

The report has 6 supported answers, 3 incomplete reviews, 1 unsupported-claims answer, 1 generation/infrastructure failure and 1 false refusal. The authored annotations contain 40 supported claims, 1 unsupported shared-study claim and 4 unresolved claims. Repeated assertions are deduplicated; cited individual edge IDs are separate evidence assertions. Counts depend on AI reviewer mapping quality.

## Cross-study attribution error

The Cow Milk answer states: “Both of these compounds are mentioned in the same study as using Cow Milk in the same procedure.” It names Biotite Mica and Sulphur. Their individual paths exist, but their frozen reference identifier sets are disjoint:

- Biotite Mica: `33402266`, `10.5005/jdras-10059-0086`, `16566` (Nirvapa).
- Sulphur: `28757226`, `22556497`, `34690042`, `38759538`, `22404`, `18748` (Dhalana or Urdhva Patana).

The answer combines separate supported paths into an unsupported shared-study assertion. Same-procedure wording is separately unresolved: methods and procedure texts differ, and the scorer does not infer procedural equivalence. These are stored graph identifiers, not externally verified scientific references.

## False refusal hidden by status

The Laghu Sutshekhar answer says information is unavailable, while returning `status: success`. The reference contains two correlated paths through Oral Medication to Endometrioma, with identifiers `254496` and `36604296`. Status-only evaluation would pass. Reviewed answer behavior is now recorded as a false refusal with no factuality score.

## Completeness and unresolved claims

Only the Bhavana successor answer names every unique related target in its reference case. Across case-specific target sets, 32 of 162 targets are named; failures/refusals retain zero coverage. This is named-target coverage, not citation recall, claim recall, or a requirement to enumerate every evidence path. Several singular answers and unqualified lists obscure available alternatives.

Abhyanga's general therapy description is outside the relationship reference. Cow Milk and Shodhita Jaypala answers use “method” and “process” loosely relative to vertex types; these may be ordinary-language paraphrases rather than factual errors and remain unscored pending adjudication. Scientific efficacy/safety was not assessed.

## Artifacts and reproduction

All files below are in `api/tests/fixtures/`:

- `claim_full_answers.json`: all 12 captured responses, failures and original metadata.
- `claim_full_decisions.json`: explicitly authored AI semantic decisions.
- `claim_full_reviews.json`: quote spans, reviewer identity/kind, answer/reference hashes, claim mappings and node-ID audits.
- `claim_full_report_v2.json`: **authoritative report**. The earlier `claim_full_report.json` is historical; v2 ensures unresolved claims do not hide known unsupported claims.
- `claim_full_coverage.json`: named and omitted targets per case.
- `claim_full_run_metadata.json`: local Ollama observation: `llama3.2:latest`, digest `a80c4f17acd55265feec403c7aef86be0c25983ab279d83f3bcd3abbcb5b8b72`, context length 4096. This is not per-response server attestation.

```sh
python api/tests/evaluate_claims.py --answers api/tests/fixtures/claim_full_answers.json --reviews api/tests/fixtures/claim_full_reviews.json --output=NEW_REPORT.json
python -m unittest discover -s api/tests -p "test_evaluate*.py"
php api/tests/run_evaluation_cases.php --fixture=api/tests/fixtures/factual_evaluation_cases.json
```

`assemble_claim_full_review.py` materializes authored decisions and refuses existing outputs. It supplies reference IDs and validates cited node IDs; it does not infer prose meaning. Edit a copy of the review packet and rescore to a new report for independent adjudication.

## Follow-up priorities

1. Require a common reference across all grouped paths before asserting “same study.”
2. Detect prose refusals independently of backend status.
3. Label partial answers and investigate evidence selection/context limits. Some serialized retrieval contexts exceed 30,000 characters; the locally observed context was 4096 tokens. Context truncation is a hypothesis, not a proven cause.
4. Diagnose Alopecia's HTTP 500. The exporter retained the failure but not the raw error body; this run does not establish its cause.
5. Obtain human review of mappings, ambiguous wording and scientific references before publishing scores.

## Verification

All 12 live factual retrieval cases pass (486 reference paths). All 18 Python evaluator tests pass, including cited record IDs, shared-study assertions, prose refusals and failure precedence. Lint passes with 19 existing warnings; production build and 24 Vitest tests pass. Existing mobile overflow failures remain separate from evaluation.
