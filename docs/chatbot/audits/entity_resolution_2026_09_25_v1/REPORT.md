# Entity-resolution implementation audit ? 2026-09-25 v1

Implemented the shared resolver, typed clarification, source-backed terminology suggestions, deterministic intent parsing, and validated model fallback. KG records, edge identities/directions, and publication identities were not edited. This is the resolution phase; previously audited graph-visibility/cap/table-count fixes were excluded by the agreed scope.

## Root causes and changes

| Finding | Classification | Resolution |
|---|---|---|
| Separate exact/fuzzy matching rules in conversation, endpoint lookup, and Explorer search | Confirmed bug/design gap | Shared schema-derived index over 2,534 live nodes, canonical RID/type, conservative correction and ambiguity handling |
| Database/type ordering selected one of several same-label identities | Confirmed bug | Keep all candidates; require typed clarification unless explicitly qualified; 83 ambiguous normalized labels found |
| Intent pattern interpreted purification methods as purified products | Confirmed bug | Separate requested target type from source-entity resolution; preserve validated traversal semantics |
| Cancer is an Indication, while symptoms/therapy routing assumed Disease | Confirmed routing gap; requested Indication paths unsupported by KG | Keep Indication identity, explain unavailable relationship, offer actual supported purified-compound path or reviewed narrower Disease choices |
| KG terminology lacked reviewed aliases/broader-concept guidance | Intentional initial limitation | Versioned NCI Thesaurus catalog with archived source evidence; currently two mapped concepts |
| Schema word ?Indication? fuzzily matched ?Induration? | Confirmed bug found in fresh probes | Exclude vocabulary derived from every supported schema type; regression covers all 17 types |
| Indication retrieval deduplication merged distinct method/compound paths for the same product/reference | Confirmed bug | Include path identities in deduplication keys; preserve exact edge-to-property/evidence association |
| Integrated search omitted purified nodes without a particular Disease bridge | Confirmed search limitation | Search all in-domain canonical nodes; graph rendering filters unchanged |
| Conversation could lose chosen type or accept unvalidated context/selection | Confirmed design gap | Revalidate context identity and offered choices, retain typed subject and other constraints, separate pending question from facts |
| A model proposal can be schema-valid but misunderstand user intent | Inherent NLU limitation | Validate mentions/types, reject conflicting or invented proposals; require confirmation for implicit model-proposed target |

`coper` now resolves conservatively to Copper and method questions return Shodhana methods. Cancer is never converted to Disease. Unsupported Indication ? Symptoms/Therapy/Formulation requests clarify rather than inventing a path. The supported Indication ? Purified_Compound example was reconciled to TREATS edges #82:124, #82:125, #82:348, and #82:75. Broader ESCC suggestions use external terminology evidence only for choosing an entity; answer facts still come from KG retrieval.

## Test results and before/after metrics

Suite counts overlap and must not be summed as independent observations.

| Dimension | Before | Final |
|---|---:|---:|
| New reviewed resolution questions, JSON/SSE | 12/32 | 32/32 |
| Factual retrieval, unchanged independent expectations | 168/168 | 168/168 |
| Attribution | 121/121 | 121/121 |
| Routing/status | 82/82 historical contract | 82/82 version-5 contract |
| Results presentation | 24/24 original questions | 24/24 version-5 questions |
| Conversation | 108/108 original contract | 108/108 version-2 contract |
| Final system-wide consistency/property/provenance checks | See frozen earlier audit | 242/242 |
| Added context/selection/search checks | Not present | 23/23 |
| Whole-index canonical identity retention | Not present | 2,534/2,534 |
| Resolver unit assertions | Not present | 55/55 |
| PHP test scripts | Baseline archived | 18/18 |
| Frontend unit tests | Baseline archived | 43/43 across 14 files |
| PHP production syntax | ? | 103 files, zero failures |
| Lint / build | ? | Zero lint errors (19 existing warnings); build passed |
| Selected Chromium browser suite | Existing mobile failures | 24 passed, 9 pre-existing mobile overflow failures |

The final benchmark has zero source changes during execution, zero snapshot edge drift, zero JSON/SSE parity failures, and no false refusals/acceptances in its labelled conversation cases. These are tested-corpus outcomes, not universal guarantees or clinical validation.

### Contract changes are disclosed, not hidden

The unchanged historical suites after implementation scored routing 60/82, presentation 18/24, and conversation 92/108. Conservative homonym/uncertain-spelling clarification intentionally changes those responses. Version 5 updates 22 routing expectations to clarification; presentation explicitly qualifies six ambiguous inputs. The conversation fixture qualifies intended method references while retaining the intentionally ambiguous case. Independent factual expectations are unchanged. Original fixtures and historical results remain available in `resolution_historical_regression_2026_09_25_v1`; `resolution_contract_changes_v5.json` records the edits.

The original new-case baseline said 14/32 because its checker did not require a successful retrieval status for an otherwise correct route. Rescoring the same saved responses with that gate gives 12/32. Both files are retained; the two overcredited Cancer responses are recorded.

Fresh probes initially passed 14/16 and exposed the Indication/Induration error. After fixing it they pass 16/16. The latter is a regression result, not untouched held-out evaluation. The final live model probe safely requested clarification after rejecting an incompatible interpretation; it did not retrieve facts. Earlier model traces remain historical artifacts and do not establish model reliability.

### Response time

Sequential local, full-body HTTP timing; not load testing or first-token timing. Baseline ? final p50/p95 milliseconds:

- Presentation JSON: 312/891 ? 235/453.
- Conversation JSON: 281/703 ? 328/1672.
- Conversation SSE: 250/765 ? 328/1422.
- New 32-case final corpus: 210.35/2526.14.

Routing-contract changes, model fallback, and local load affect comparability. Conversation tail latency increased; do not claim an overall speedup. Model fallback has a ten-second timeout and can be disabled.

## New and updated tests

- `api/tests/test_entity_resolution.php`: exact/RID/alias matching, typos/transpositions, ambiguity/ties/duplicate labels, explicit and incompatible types, broader concepts, stale catalog bindings, multiple entities, schema vocabulary, deterministic targets, invalid/conflicting/invented model proposals and timeout.
- `api/tests/evaluate_entity_resolution.py`: 16 reviewed questions across JSON/SSE; status-aware scoring and preserved response artifacts.
- `api/tests/evaluate_resolution_context.py`: 23 assertions including five multi-turn scenarios, typed/broader choices, forged selections, unknown entities, topic changes, Explorer search, exact TREATS provenance/properties.
- `src/components/chatbot/EntityClarification.test.tsx`: correction notice and typed choice interactions (2 tests).
- `tests/entity-resolution.spec.ts`: typo answer, Cancer choice-to-answer, and Explorer canonical suggestion selection (3 tests).
- Existing conversation routing structural assertion updated for grouped ambiguity candidates. Benchmark/conversation runners accept the explicit versioned contract. Existing fact and evidence tests remain intact.

## Remaining limitations

- Source-backed terminology coverage is only the initial two concepts, not all medical synonyms or broader disease families. Source checking is not domain-expert approval.
- Very short, tied, and uncertain names ask for clarification. More prompts are intentional; no uncertain match is forced.
- Natural-language support is English-oriented and bounded by the existing validated traversal catalog. This does not add unsupported KG paths.
- Legacy typed endpoint inputs remain exact; resolve natural language upstream through chatbot/search/resolve_entity. Explorer search now centers on canonical labels/aliases and lexical suggestions rather than treating arbitrary description words as entity aliases.
- Llama may reject or misinterpret unusual wording. Structured validation cannot prove intent; implicit targets require confirmation. No LLM-produced answer is used as ground truth.
- Prior graph rendering gaps remain: Compound hides Disease nodes/75 USED_FOR_TREATMENT edges; Integrated excludes Indication/AyurvedicFormulation and 637 TREATS plus 84 USED_IN_FORMULATION edges. Prior graph caps omit 40 FOLLOWED_BY edges at ordinary limits. Table-count duplication remains. These are prior-audit figures, not claims of a new complete display reconciliation.
- Nine existing mobile-overflow failures and previously identified standalone TypeScript issues remain. Passing Vite build is not proof of a clean standalone typecheck.
- Publication support and clinical/scientific accuracy are not established. The system is not declared ready for final paper evaluation while the full frontend regression suite has known failures.

## Artifacts and changed files

Final reproducible benchmark: `api/tests/fixtures/resolution_benchmark_2026_09_25_v3/benchmark.json` (source hashes, fixture hashes, commands, timing, drift and parity).
Baseline: `resolution_baseline_2026_09_25_v1`; supplemental results: `resolution_after_2026_09_25_v1`; original-contract regression: `resolution_historical_regression_2026_09_25_v1`. These paths are under `api/tests/fixtures/`.

[file_manifest.json](file_manifest.json) enumerates implementation files and every archived audit/source artifact with SHA-256 hashes. No git history is available in this workspace. `src/lib/api.ts` was added to benchmark hash coverage but not modified. This report and its manifest are additional documentation outputs.

Implementation/test/contract files:

- `api/chatbot_response.php`
- `api/core/ChatbotOrchestrator.php`
- `api/core/ConversationContext.php`
- `api/core/ConversationEntities.php`
- `api/core/EntityResolution.php`
- `api/core/EntityResolver.php`
- `api/core/QueryUnderstanding.php`
- `api/core/QuestionRouter.php`
- `api/core/SemanticQuery.php`
- `api/core/terminology_catalog_v1.json`
- `api/indication_compound.php`
- `api/resolve_entity.php`
- `api/search.php`
- `api/tests/evaluate_conversations.py`
- `api/tests/evaluate_entity_resolution.py`
- `api/tests/evaluate_resolution_context.py`
- `api/tests/fixtures/chatbot_evaluation_cases_v5.json`
- `api/tests/fixtures/conversation_evaluation_cases_v2.json`
- `api/tests/fixtures/presentation_cases_v5.json`
- `api/tests/fixtures/resolution_contract_changes_v5.json`
- `api/tests/run_current_benchmark.py`
- `api/tests/test_conversation_routing.php`
- `api/tests/test_entity_resolution.php`
- `docs/chatbot/entity_resolution.md`
- `src/components/chatbot/ChatMessage.tsx`
- `src/components/chatbot/ChatbotWidget.tsx`
- `src/components/chatbot/EntityClarification.test.tsx`
- `src/lib/chatApi.ts`
- `src/lib/types.ts`
- `tests/entity-resolution.spec.ts`
