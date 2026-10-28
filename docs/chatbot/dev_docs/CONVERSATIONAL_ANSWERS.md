# Conversational Answers and Descriptive Sources

Graph answers utilize a validated fact packet, an LLM-generated explanation when enabled, and an automated verification review. If model generation is unavailable or fails review, the system falls back to deterministic factual sentences with exact literature citations. Static FAQs operate independently.

## Presentation and Attribution

The presentation provides distinct associations with numbered source buttons. Examples are identified as a subset when additional results exist. All retrieved records remain accessible through the evidence panel.

Each source card displays the supported association, followed by the recorded relationship and scoped properties such as procedure, duration, dose, or outcome. PubMed ID and DOI links are distinguished. Canonical paths preserve directed, per-edge support; projected records describe associations without inventing unavailable edge provenance. Technical properties remain expandable. Literature citations link to indexed publication identifiers.

`ConversationalAnswer` validates retrieval records through `EvidenceAnswerBuilder`, then constructs the fact packet. Templates describe relationship types and retrieved records without hardcoding entity facts. Ambiguous legacy path labels are not treated as directed edges.

## Model Contract and Constraints

- Generation receives the question, up to two recent history turns for context, and the selected facts with their scoped properties. It returns concise sentence blocks mapped to fact identifiers. Reference identifiers and URLs are assembled by the backend, not the model.
- Ollama calls use schema-constrained JSON output. The backend validates the response structure: complete fact identifiers in order, required entity names, numbers matching source evidence, bounded length, and absence of fabricated citation markers.
- A review pass checks proposed claims against fact and property scopes. Uncertain, malformed, or unverified claims reject the draft and trigger fallback.
- Review verdicts follow a schema-constrained array (`supported`, `unsupported`, `uncertain`), one entry per sentence. Sentences maintain evidence framing (such as "recorded" or "associated"). Claims of treatment efficacy or cure exceeding source evidence are rejected.
- Request timeouts bound model processing, with no recursive retries. Oversized prompts use deterministic fallback directly. The environment variable `CHATBOT_ANSWER_MODE` governs answer synthesis behavior.
- Complete answers are assembled before streaming begins. Graph answers do not depend on Ollama availability.

## API Specification

JSON responses and Server-Sent Events (SSE) `done` events provide:

- `answer_intro` and `answer_blocks` (`text`, `citation_ids`).
- `citations`: response-local identifiers, fact identifiers, record indices, statement, attribution level, and scoped properties/identifiers.
- `generation`: `mode`, `fallback_reason`, and `latency_ms`.

The client maintains the authoritative answer and citation metadata in session storage. Selecting a citation badge opens and focuses the corresponding evidence record.

## Verification

The test suite exercises conversational phrasing and verification layers:
- `test_conversational_answer.php` tests draft validation, malformed output handling, missing entity detection, review rejection, and JSON/SSE parity.
- `test_evidence_answer_builder.php` validates target coverage and corrupt-path rejection.
- Frontend tests verify descriptive reference links, outcome display, and citation selection.

Verification commands:

```sh
php webservice/api/tests/test_conversational_answer.php
php webservice/api/tests/test_evidence_answer_builder.php
```
