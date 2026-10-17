# Realistic conversation evaluation

The baseline live run passed **41 of 54 turns** across **34 scenarios**. Each scenario
runs independently through JSON and SSE, producing **82/108 passing turn runs**.
Both transports have identical semantic outputs and failures; transport agreement
does not make a wrong answer correct. The subsequent routing repair now passes
**54/54 turns**, or **108/108 JSON/SSE turn runs**, against the unchanged suite.
The original failing report remains preserved.

## Routing repair

`ConversationEntities` reads graph labels and aliases for the supported vertex
types. Exact whole-label matches take precedence, with longer names taking priority
over overlapping short names. Typo matching compares the complete remaining name,
requires at most two edits and a maximum 25% edit distance, and preserves ties as
ambiguity. A multiword botanical prefix can omit an author suffix only when the
match is unambiguous. Arbitrary substring `LIKE ... LIMIT 1` lookup is no longer
used by the question router. Intent words are excluded from fuzzy entity matching.

The router resolves entities in the current turn before considering history.
Explicit new names win; unknown names and multiple entities do not borrow a prior
topic. For pronouns and short follow-ups it reconstructs entity and intent context
from the supplied user turns. Possessive pronouns such as “its” and entity-only
follow-ups such as “And Bharjana?” are supported. Ambiguous method/process names
still require clarification unless explicitly qualified. Narrow intent spelling
normalization handles common one-edit variants, and unsupported task patterns are
checked before entity-based routing.

No entity-specific scientific answer or expected test output was added to
production. Existing answer construction, provenance and factual validation remain
in force. The matcher loads the current graph vocabulary per request; it does not
retain a cross-request cache or silently use stale entity labels.

Verification artifacts:

- `conversation_evaluation_report_v4.json`: 108/108, no transport differences and
  no false refusals in the 39 selected answerable turns per transport.
- `conversation_fix_factual_report_v1.json`: 168/168 unchanged factual cases.
- `conversation_fix_attribution_report_v1.json`: 121/121 attribution cases.
- `results_conversation_fix_report_v1.json`: 24/24 independent presentation cases.
- Existing 82 HTTP routing/status cases pass. New offline routing assertions cover
  45 checks using separate synthetic entity names, including fuzzy ties, unknown
  suffixes, new-topic precedence and intent-only follow-ups.

These results close the observed failures, not every possible language ambiguity.
The old baseline findings below remain useful regression history.

## Method

`conversation_evaluation_cases_v1.json` contains questions and expectations written
before the live run. It covers paraphrases, spelling/formatting, ambiguous method
names, missing context, clarification responses, pronouns, elliptical follow-ups,
explicit topic changes, multiple entities, a longer history window, unsupported
requests, unknown entities, real graph-empty requests, and recovery after refusal.

The runner sends actual preceding user/assistant turns. It mirrors the UI: include
user turns and successful bot turns, take the most recent six messages, then let
the API apply its existing 500-character-per-message limit. Histories reset between
scenarios and transports. Failed turns remain in the report, and subsequent turns
are marked `preceding_turn_failed`; the runner never substitutes an expected answer
to repair conversation history.

Answerable questions reuse unchanged independent expanded factual references.
Checks include status, route, exact seed/type, complete facts and supporting edge
paths/properties, citation scopes, result grouping and complete-result access.
The evaluator first establishes agreement with the frozen graph, then checks the
display against those verified results. A successful HTTP response or plausible
answer alone cannot pass. Infrastructure/generation errors are separate from false
refusals. Empty graph results are checked separately from refusals of answerable
questions. Eight offline evaluator tests exercise scoring mutations and SSE/history
handling; a live browser scenario verifies real history submission for three turns.

The fixture assumes the current single-seed product contract: when asked about two
entities together, clarification is required rather than silently selecting one.
Unknown names must not be silently replaced with unrelated known entities. These
are explicit acceptance expectations, not labels copied from router behavior.

## Findings (unique turns, not doubled transport counts)

| Behavior | Passed / total |
| --- | ---: |
| Paraphrases | 6/7 |
| Typos and formatting | 5/6 |
| Ambiguous method name | 1/1 |
| Clarification follow-ups | 2/3 |
| Missing context / missing intent | 2/2 |
| Multiple-entity ambiguity | 1/2 |
| Explicit control questions | 8/8 |
| Pronoun follow-ups | 4/4 |
| Elliptical follow-ups | 0/3 |
| Topic changes | 3/5 |
| Longer history window | 1/1 |
| Unsupported requests | 4/7 |
| Unknown entity | 0/1 |
| True graph-empty requests | 2/2 |
| Recovery after refusal/empty result | 2/2 |

There are **39 answerable turns**: five false refusals/unnecessary clarifications,
two successful answers to the wrong intent/entity, and one execution/generation
error. The remaining 31 pass. The five false refusals represent 12.8% of these
selected answerable turns, not an estimated production-wide rate.

### Priority fixes supported by this run

1. **Reject unrelated fuzzy entity matches.** “Zorblax Syndrome” returns symptoms
   for Antiphospholipid Antibody Syndrome. “Its purification media, please.” after
   discussing Copper asks for clarification about Agnicikitsa Lepa. Resolve context
   before fuzzy search; avoid treating ordinary question fragments as entity names.
2. **Give a newly named entity precedence over history.** After Swedana, “Switch to
   method Bharjana. What media does it use?” still answers about Swedana. After
   Copper, an explicit Acute Renal Calculus symptom question with “it” still tries
   to use Copper. Only use history when the current turn has no explicit entity.
3. **Track entity and intent separately for follow-ups.** “And the therapies?”,
   “What about formulations?”, and “And Bharjana?” all fail. The first routes to
   Commiphora wightii. Arn. Bhand, then the evidence validator rejects an entity
   mismatch. This is a misroute followed by a validation error, not a service outage.
4. **Detect multiple named entities before committing to one.** The two-disease
   symptom request silently answers only Acute Renal Calculus. Ask which disease
   the user means, or introduce an explicitly evaluated multi-entity response.
5. **Improve intent wording and typo handling.** “Substances used during purification
   of Copper” returns purified compounds instead of purification media; “symtoms”
   fails while tested entity-name typos work. Use constrained intent normalization,
   keeping ambiguous matches visible rather than inventing confidence.
6. **Distinguish unsupported tasks from missing graph data.** A poem request returns
   entity-not-found, a guaranteed-cure question returns no-data, and a deletion
   request asks what the user wants to know about Copper. None executed a write or
   gave a cure guarantee, but their explanations do not identify the actual limit.

Retain this failing baseline. Add focused regression cases for each repair, rerun
the unchanged suite, and also rerun factual/attribution gates so conversational
improvements cannot quietly change supported facts.

## Reproduction and limits

```powershell
python api/tests/evaluate_conversations.py --output NEW_REPORT.json
python -m unittest discover -s api/tests -p test_conversation_evaluation.py
npx playwright test tests/conversation-evaluation.spec.ts --config playwright.external.config.ts --project chromium
```

The report is `api/tests/fixtures/conversation_evaluation_report_v1.json`. It retains
questions, actual sent histories, full responses, latency, failures, transport
parity and hashes for the suite, references, snapshot, runner and backend core.
The runner refuses to overwrite reports and exits nonzero for observed failures.

This is a deliberately challenging English acceptance suite, not a representative
sample of real user traffic. It does not establish medical/scientific truth,
multilingual quality, adversarial robustness, or all possible conversation lengths.
The broader 168-case factual and 121-case attribution suites remain separate gates.
