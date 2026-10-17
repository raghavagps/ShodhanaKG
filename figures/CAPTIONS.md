# Figure captions

## core/01_schema

Actual stored schema: 17 classes, 25 directed type-level connections using 21 predicates. A class-level Process to Process edge does not assert an instance self-loop. Stored DERIEVED_FROM spelling retained. White domain clusters; no instance counts implied.

## core/02_composition

Counts of unique RIDs; inherited Node rows deduplicated by exact RID, never by display label. All 2,534 vertices and 10,123 edges included.

## core/03_integration

All 75 actual cross-domain edges and 46 incident nodes; all endpoint labels shown. Every arrow is a stored USED_FOR_TREATMENT edge. SVG metadata and CSV preserve full identities. This bridge-only detail complements the full instance graph.

## core/04_retrieval_workflow

Conceptual implementation diagram, not measured data. Based on ChatbotOrchestrator, QueryUnderstanding, ConversationContext, ResultsPresentation and ChatResponseTransport. Prior answer text is not scientific ground truth.

## core/05_software_evaluation

Exact denominators from the latest complete verified audit. Latency lines connect recorded median and p95, not confidence intervals or reconstructed distributions. These results predate subsequent production compatibility changes. No clinical accuracy claim.

## supplementary/S02_treatment_phases

Actual Vipadika case, source node #420:124, reference 253965. Selected reference maximizes represented treatment-phase types among this node’s records; ties lexical. All selected therapy edges and same-reference formulation edges retained. Missing phases are not invented.

## core/06_purification_case

All compound-domain edges sharing reference 23633854; smallest edge-count reference subgraph containing UNDERGOES, FOLLOWED_BY, USES_MEDIA, PRODUCES and VALIDATED_BY (lexical tie-break). Illustrative association records, not a validated procedure or efficacy claim.

## core/07_evidence_coverage

Namespaces come from the audited catalog, not numeric-length inference. Counts by predicate are repeated edge occurrences; distinct identifier counts use catalog entries. Namespace classification is not evidence that a publication supports the edge.

## supplementary/S03_property_coverage

Raw property names retained without semantic merging. Denominator is all edges of each predicate, not clinically eligible edges. Dash = no stored nonempty values; applicability is not established and must not be interpreted as missing required clinical data.

## supplementary/S04_connectivity

Multidegree counts every parallel edge; weak components ignore direction only for component calculation. Bridge participants are direct endpoints of USED_FOR_TREATMENT, not arbitrary path reachability or efficacy scores.

## supplementary/S05_language_robustness

108 executions across 34 scenarios; JSON/SSE executions are paired, not 108 independent questions. Categories and denominators reproduced from the verified audit; no unsupported generalization to all English questions.

## supplementary/S06_conversation_context

Actual recorded JSON turns: pronoun follow-ups, explicit topic change and ambiguity clarification. Counts are lengths of returned answer_results/citations arrays. Full sent and returned structured contexts are supplied in the accompanying JSON; prior answer wording is not scientific evidence.

## supplementary/S07_failures_limitations

Latest complete verified audit has no observed failures in these categories. Zero events are shown as points, not fabricated failure distributions. Unsupported requests are not automatically software failures. Denominators overlap and are not pooled.

## supplementary/S08_publication_status

Identifier audit dated 24 September 2026; catalog status counts reproduced as stored. User-confirmed namespace, registry existence and publication claim support are distinct. No numeric expert-review completion rate is available; it is not plotted as zero.

## supplementary/S01_complete_instance_graph

Complete actual graph, no sampling or edge concentration. Force layouts computed separately in documented domains with seed 42; bridge nodes constrained to inner sides. All nodes/edges retain IDs and raw metadata. Auto labels suppressed for dense graph; zoomable supplement, not intended for reading every edge at print scale.