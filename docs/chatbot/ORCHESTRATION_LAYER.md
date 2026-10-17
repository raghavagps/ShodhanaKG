# Chatbot Orchestration Layer Architecture

The Orchestration Layer is the final deterministic component of the ShodhanaKG Chatbot infrastructure. It coordinates intent routing and knowledge retrieval to produce a unified, completely hallucination-free JSON payload that can later be consumed by an LLM or natural-language generation module.

## Core Principles

1. **Zero Hallucination:** The Orchestrator operates exclusively as a data-flow router. It NEVER generates factual information or rewrites canonical properties.
2. **Explicit Allowlist:** Only 10 validated retrieval endpoints can be executed. It is impossible for the orchestrator to execute arbitrary code or unauthorized paths.
3. **State Transparency:** It rigidly enforces the semantic distinctions between `success`, `no_data`, `needs_clarification`, `unsupported`, and `entity_not_found`.

## Pipeline Flow

```text
User Question
      ↓
QuestionRouter
      ↓
EntityResolver
      ↓
Intent + Entity + Endpoint
      ↓
Endpoint Allowlist Validation
      ↓
Deterministic Retriever (execute_* function)
      ↓
GraphRetriever
      ↓
EvidenceCorrelator
      ↓
KnowledgeNormalizer
      ↓
Canonical JSON
      ↓
ChatbotOrchestrator
      ↓
Standardized Chatbot Response
```

## Retriever Function Contract

The 10 validated HTTP endpoints were minimally refactored to expose their core algorithms through reusable PHP functions (e.g., `execute_compound_media()`).

- **Input:** Canonical entity string (`$compound` or `$disease`).
- **Processing:** Executes the unmodified ArcadeDB traversals and Evidence Correlator.
- **Output:** Returns a canonical PHP array. The HTTP wrapper layer executes this function to retain external API compatibility.

## Response States

### 1. Success
The router successfully matched an entity and intent, and the query returned correlated factual records.
```json
{
  "status": "success",
  "question": "What media is used for Croton tiglium Linn.?",
  "routing": { ... },
  "retrieval": {
    "status": "success",
    "path": [ ... ],
    "results": [ ... ]
  }
}
```

### 2. No Data
The router successfully matched an entity and intent, but ArcadeDB returned no records that survived Evidence Correlation.
```json
{
  "status": "no_data",
  "routing": { ... },
  "retrieval": { "status": "no_data", "results": [] }
}
```
*Note: This is a successful execution demonstrating the factual absence of data. It is not an error.*

### 3. Needs Clarification
The router resolved the entity but could not discern the intent.
```json
{
  "status": "needs_clarification",
  "reason": "intent_ambiguous",
  "message": "What would you like to know about Copper?"
}
```

### 4. Unsupported
The user asked a question outside the boundaries of the 10 defined retrieval paths (e.g., "What is the molecular weight?").
```json
{
  "status": "unsupported",
  "reason": "no_retrieval_pattern",
  "message": "That question is outside the currently supported ShodhanaKG knowledge retrieval patterns."
}
```

### 5. Entity Not Found
The router failed to resolve the entity in ArcadeDB.
```json
{
  "status": "entity_not_found",
  "message": "The requested compound or disease could not be found in ShodhanaKG."
}
```

## Security & Integrity

- **No Remote HTTP:** The Orchestrator leverages `require_once` to include the retriever files directly, executing them safely as native functions without spawning secondary HTTP requests.
- **Strict Error Boundaries:** The `api/chatbot.php` boundary wraps the execution in a top-level `try-catch`, ensuring PHP stack traces and warnings are never leaked into the JSON payload.
- **Anti-Hallucination Testing:** The orchestration test suite (`test_chatbot_orchestrator.php`) includes integrity assertions that computationally verify the orchestrator payload is `json_encode`-identical to the raw retriever payload.
