# Conversational answers and descriptive sources

Graph answers now use a validated fact packet, a model-written explanation, and a separate model review. If either model call fails, times out, or returns an unsupported or incomplete draft, the answer uses readable factual sentences with the same citations. Static FAQs remain separate.

## Presentation and attribution

The initial answer contains up to four distinct associations, selected in retrieval order, with numbered source buttons. Examples are explicitly identified as a subset when more results exist. Selection is not a ranking of treatments or evidence strength. All original records remain accessible through **View all results and sources**.

Each source card starts with **What this reference supports**, followed by the recorded relationship and scoped properties such as procedure, duration, dose or outcome. PMID and DOI links remain distinct. Missing outcomes are stated explicitly. Canonical paths preserve directed, per-edge support; legacy records describe associations without inventing missing edge provenance. Technical fields are expandable. No paper titles, abstracts or findings are fetched or fabricated.

`ConversationalAnswer` validates full retrieval through `EvidenceAnswerBuilder`, then builds the fact packet. The old builder's database-style text is retained only for validation/legacy tests; it is no longer the successful chat response. Templates describe relationship types and live records, never question-specific scientific answers. Ambiguous legacy path labels are not interpreted as directed edges.

## Model contract and limits

- Generation receives the question, at most two recent history turns for context only, and the selected facts and their scoped properties. It returns one short sentence block per fact ID. Reference IDs and URLs are assembled by the backend, not the model.
- The local Ollama calls use [schema-constrained JSON output](https://docs.ollama.com/capabilities/structured-outputs). The backend still checks the response: complete fact IDs in order, required entity names, numbers within each fact's evidence, bounded text, and no generated citation markers.
- A separate review call checks every proposed claim against only its own fact and property scopes. Missing, uncertain, malformed, or negative decisions reject the draft. This is the same configured model in a separate call, not an independent expert or proof of correctness; correlated model errors remain possible.
- Review verdicts are a schema-constrained ordered array (`supported`, `unsupported`, `uncertain`), one entry per sentence. The backend owns the positional mapping. Every sentence must retain evidence framing (for example, “recorded” or “associated”). Direct checks reject stronger treatment/cure/efficacy claims and leaked implementation labels even if the reviewer approves them. This conservative restriction can reject legitimate paraphrases; detailed stored outcomes remain accessible in the source cards.
- Generation gets 30 seconds and review up to 15 seconds, within a 45-second model-work deadline; there are no retries. Oversized prompts use fallback directly. Default mode is hybrid; the server environment option `CHATBOT_ANSWER_MODE=fallback` disables model calls without changing the citation UI. It is not accepted from user requests.
- Both modes build a complete accepted answer before streaming any text. Health remains dependent on graph availability, so unavailable Ollama does not disable the assistant.

## API compatibility

Existing `answer`, `status` and `context` remain. Successful JSON responses and SSE `done` events additionally contain:

- `answer_intro` and `answer_blocks` (`text`, `citation_ids`).
- `citations`: response-local ID, fact IDs, zero-based original record indices, statement, attribution level and scoped properties/identifiers. IDs are stable within that response, not global publication IDs.
- `generation`: `mode`, `fallback_reason`, and `latency_ms`. These are diagnostic fields, not product prose. Server logs record this metadata without question text.

The client keeps the authoritative final answer and citation metadata in session storage. Citation selection opens, paginates to, and focuses the correct record. Older saved messages retain their existing renderer.

## Verification

`test_conversational_answer.php` covers accepted drafts with stubbed model calls, unavailable/malformed generation, unknown IDs, unsupported numbers, missing entities, fabricated citations, incomplete/negative reviews, safe fallback and JSON/SSE presentation parity. `test_evidence_answer_builder.php` retains corrupt-path rejection and now checks complete target coverage in the citation packet, rather than requiring every target in the short prose.

The frontend tests exercise descriptive DOI support, missing outcomes, legacy attribution limits, opening a source beyond the first page, and SSE metadata. Playwright covers the captured Vipadika response with all 11 original records and restores citations after reload. Historical captured answers and factual labels are unchanged.

Run the live diagnostic pilot with a **new** output path:

```sh
php api/tests/run_conversation_pilot.php api/tests/fixtures/NEW_conversation_pilot.json
```

It records actual drafts, review outputs, latency and whether each result used hybrid or fallback. These are diagnostics, not independently reviewed factual labels. Report acceptance separately from successful fallback responses. Earlier pilot versions document timeouts, malformed reviews and unsupported drafts; they are not passing quality claims.

Manual inspection of pilot v6 found a reviewer false positive: it approved “treats Vipadika” for evidence describing a recorded association. That draft is retained as a negative diagnostic example, not an accepted scientific claim. The direct wording guard and its regression test were added after this discovery. Pilot v6 is superseded and must not be used as current quality evidence.

Repository checks for this change: lint has zero errors and 19 existing warnings; build succeeds; all 29 frontend tests pass. The 82 routing/retrieval cases and both 12-case independent factual suites pass in direct mode. Backend regression tests pass, including 195 traversal assertions. Chromium has 12 passes (including both evidence tests) and nine existing mobile overflow failures. Live model observations are recorded separately in the pilot artifacts; these direct-mode retrieval results do not imply 106 successful model-generated answers.

## Final local pilot

`conversational_pilot_v7.json` records three accepted hybrid answers: Vipadika (28.5 seconds), Swedana (24.9 seconds), and Laghu Sutshekhar (10.7 seconds). All nine displayed statement blocks were additionally inspected against their mapped records during development: they retain the recorded associations and, for Endometrioma, post-treatment phase. This is AI-assisted inspection, not a domain-expert assessment or a representative accuracy benchmark. Zero fallbacks in this small final pilot does not establish a production fallback rate; earlier runs demonstrate important failure modes.

`conversational_transport_check.json` records an additional real HTTP JSON/SSE comparison for Laghu Sutshekhar: both used hybrid mode, answers and citation maps matched, and concatenated streamed text matched the authoritative completion. Warm calls took approximately 4.8 and 4.7 seconds. Model latency depends on local load and model warm-up.

The visual previews are `artifacts/conversational-answer.png` and `artifacts/conversational-source.png`. The source panel was inspected for actual claim descriptions and available reference links, not just presence of record counts.
