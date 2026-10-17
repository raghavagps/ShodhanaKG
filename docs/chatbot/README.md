> Current implementation and release requirements: [CURRENT_SYSTEM_STATUS.md](CURRENT_SYSTEM_STATUS.md). Older counts, LLM requirements and link behavior below describe historical versions.

# ShodhanaKG Chatbot Documentation

Current answer presentation: [Results-first answers and compact evidence](./RESULTS_PRESENTATION.md). The [earlier conversational rendering](./CONVERSATIONAL_ANSWERS.md) and its measurements remain documented for comparison.

Current graph-answer behavior: [Evidence-based answer construction](./ANSWER_CONSTRUCTION.md). Graph answers are rendered directly from retrieved facts; Ollama is no longer required for this path.
Welcome to the documentation for the ShodhanaKG Chatbot infrastructure. This AI Chatbot uses a Retrieval-Augmented Generation (RAG) approach that uses deterministic retrieval paths and graph traversal rules over ArcadeDB to constrain retrieved evidence. These controls do not guarantee factual correctness of generated answers; see the [evaluation guide](./EVALUATION_GUIDE.md) for measured coverage and limitations.

## Overview

The Chatbot is built upon a multi-layer architecture:

1. **Static FAQ & Meta Interception (`FAQMatcher`):** A lightweight, pre-orchestration interceptor that answers platform/help and navigation queries only; scientific node descriptions go through stored-property retrieval, answering them deterministically without graph traversal.
2. **[Retrieval Core](./RETRIEVAL_CORE.md):** The foundational, LLM-free deterministic retrieval layer for querying the Shodhana Knowledge Graph. It introduces **Evidence Correlation** to prevent cross-join hallucinations by ensuring that edges traversing generic conceptual hubs have strictly matching references (e.g., PubMed ID, DOI, or Ayush ID).
3. **[Orchestration Layer](./ORCHESTRATION_LAYER.md):** The final deterministic component that coordinates intent routing and knowledge retrieval to produce a unified evidence JSON payload. This payload supplies deterministic results and evidence presentation; optional model assistance cannot enumerate facts. Independent raw-reference checks assess fidelity to the graph.

## Documentation Index

- **[Retrieval Core Architecture](./RETRIEVAL_CORE.md)** - Explains the cross-join problem in graph traversals and how Evidence Correlation solves it deterministically.
- **[Orchestration Layer Architecture](./ORCHESTRATION_LAYER.md)** - Details the strict intent routing and state transitions (`success`, `no_data`, `needs_clarification`, `unsupported`, `entity_not_found`).
- **[Validation Matrix](./VALIDATION_MATRIX.md)** - A comprehensive audit report confirming the accuracy of the retrieval schemas and the successful rejection of massive hallucinated cross-joins.
- **[Query Coverage Report](./CHATBOT_QUERY_COVERAGE_REPORT.md)** - Outlines the 10 validated retrieval endpoints and patterns supported by the orchestration layer.
- **[Validation Report](./CHATBOT_VALIDATION_REPORT.md)** - Detailed testing reports for specific edge cases and queries.

## Integration

The chatbot frontend integrates with this orchestration payload using standard JSON fetching via `src/lib/chatApi.ts`.
The backend logic is served primarily from `api/chatbot_response.php` and requires `OLLAMA_URL` in the environment configuration along with `VITE_CHAT_ENABLED=true`.
