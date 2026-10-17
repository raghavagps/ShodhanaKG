# ShodhanaKG chatbot: system architecture, methodology and evaluation results

## 1. Chatbot communication through the three-tier architecture

ShodhanaKG separates browser-based presentation, application services and knowledge-graph storage into three logical tiers, as shown in [Figure 9, Three-tier ShodhanaKG system architecture](../../../figures/core/09_three_tier_architecture.pdf). The tiers define responsibilities and communication boundaries; they do not require three separate physical servers. Apache delivers the compiled frontend assets and serves the PHP API, whereas ArcadeDB stores the graph records queried by the application.

The **presentation tier** is implemented in React and TypeScript, with Vite producing the HTML, CSS, JavaScript, fonts and other assets delivered to the browser. The chatbot interface accepts questions and follow-up requests and displays answers, retrieved entities and supporting citations. Related graph exploration views use Cytoscape.js to display nodes, directed edges and their properties. Static asset delivery is distinct from chatbot communication: after loading the interface, the browser sends a JSON-encoded HTTP POST request to the PHP chatbot endpoint. This request carries the question and, where applicable, recent conversation history, structured conversation context, a selected clarification candidate and a pagination offset. The browser does not query ArcadeDB directly.

The **application tier** receives requests through `api/chatbot_response.php` and coordinates input validation, entity resolution, intent recognition, retrieval planning, evidence handling and answer construction. A supported retrieval request is translated into schema-aware graph operations with an explicit subject, requested result type and relevant constraints. The PHP services send SQL commands through the ArcadeDB REST command interface. Retrieval retains node and edge record identifiers (RIDs), relationship direction and stored properties so that results can be traced to the records that support them. Reference services preserve namespace distinctions among PubMed identifiers, AYUSH identifiers and digital object identifiers (DOIs), rather than treating every numerical reference as a PubMed identifier.

The **data tier** returns graph records to PHP as JSON. It contains the Compound Shodhana and Disease Shodhana subgraphs and their directed integration relationship, `Purified Compound → USED_FOR_TREATMENT → Disease`. The application traverses only supported paths and uses the returned node and edge properties to construct the answer and its provenance. These graph relationships describe stored associations; traversing a treatment relationship does not establish therapeutic efficacy.

After retrieval, the application returns the answer, result entities, citation mappings, pagination information and updated structured context through either a JSON response or server-sent events (SSE). In the SSE path, the frontend consumes context and answer events followed by terminal response metadata; SSE is a response transport and does not imply that an LLM generated the answer. The browser renders the results and maps citation selections to the corresponding evidence records. Namespace-specific links allow users to open the original reference record where a valid link is available. These links provide access to cited sources and are not an external publication-search step in the query pipeline.

Ollama is an optional application-side service communicating with PHP over HTTP. It can assist language interpretation or generate a short introduction from validated facts, but it does not enumerate retrieved result entities or supply scientific ground truth. The deterministic retrieval and answer paths therefore remain distinct from model availability. Solid arrows in Figure 9 represent static-file or application-data transfer; the dashed Ollama connections represent conditional language assistance.

## 2. Methodology: chatbot architecture and evaluation workflow

### 2.1 Chatbot architecture

[Figure 10, Chatbot architecture and pipeline](../../../figures/core/10_chatbot_architecture_pipeline.pdf), details the progression from a question to an evidence-linked answer. The request is first validated and interpreted in relation to the available conversation context. The entity resolver uses KG entity types, canonical labels, aliases and supported spelling/terminology matching to identify candidate nodes. Intent recognition determines whether the user is requesting relationships, a particular result type, or stored node properties. Ambiguous entity or intent interpretations trigger clarification rather than an arbitrary selection.

For relationship questions, the router and retrieval planner select a registered endpoint or supported semantic traversal while preserving the requested entity type, graph direction and constraints. The framework contains 52 catalogued retrieval endpoints and supports bounded schema-valid paths up to five hops. Retrieval is followed by validation of the returned facts and their provenance. Canonical fact identifiers, result indices and citation references retain the association between an answer item and its supporting edge or path. Treatment-phase terminology is derived from the actual relationship type. Result enumeration and citation assembly are deterministic; optional language assistance cannot replace the retrieved entity list.

Stored-node requests follow a separate path: the system returns non-empty properties of the resolved node, with explicit named-property questions restricted to the requested field. Missing properties are reported as unavailable rather than inferred from the node name, related entities or model knowledge. Curated introductory FAQs are a further, separate educational path; their answers are not represented as KG-derived facts and do not receive fabricated KG evidence identifiers. Figure 10 summarizes the principal graph-answering path rather than every specialized branch.

Multi-turn interpretation uses structured context containing the active entity and type, resolved entities, constraints, prior intent or requested result type, and pending clarification. Context and entity references are revalidated before fresh retrieval on subsequent turns. Recent messages can assist interpretation, but previous assistant prose is not treated as trusted scientific evidence. Unsupported paths and absent records yield explicit unsupported or no-result responses. These clarification and context mechanisms are supported by the implementation; the supplied Figure 10 PDF summarizes the main pipeline and does not depict every conversation-state transition. Its final “Results and Source ID” stage presents retrieved results together with their supporting reference identifiers.

### 2.2 Evaluation workflow

The evaluation process follows the four-stage cycle in [Figure 8, Conceptual evaluation workflow](../../../figures/core/08_evaluation_loop.pdf). An evaluation runner coordinates configuration, case loading, execution and validation. First, it selects the endpoint or HTTP transport, versioned fixtures and run configuration. Second, it loads a question, its expected software contract, independently specified reference facts and any conversation context required by the scenario. Third, it executes the query or conversation and captures returned results, evidence, status and elapsed time. Fourth, it compares the response with the relevant reference assertions and records outcomes, diagnostic details and timings before proceeding to the next case.

The dashed connections between the central runner and the four stages denote orchestration of this sequence. They do not represent an additional learned feedback mechanism. The outer “next test case” arrow repeats the evaluation cycle; it does not train or fine-tune the model, automatically repair the system, modify expected answers or write to the KG. The depicted fixture and metrics stores correspond to saved input and output artifacts, not necessarily separate database services. A fresh sandbox deployment for every case and autonomous agent registration are not part of the implemented workflow.

Reference expectations were derived from frozen raw KG records independently of the answer-generation implementation. Evaluators checked returned facts and supporting paths against those records rather than accepting the chatbot's own output or an LLM judgment as ground truth. Independence here concerns the retrieval/answer implementation: the references remain KG-derived and are not an independent scientific or clinical truth set. Raw responses, evaluator outcomes, timing observations, source hashes and fixture checksums were retained. JSON/SSE comparisons required agreement in answer content, routing, retrieval, reference and citation mappings, and structured conversation context; optional model-attempt diagnostics were recorded separately.

### 2.3 Evaluation design and metric definitions

The quantitative analysis uses the saved contract-version-8 benchmark completed on 10 October 2026 at 21:52 UTC (11 October in India), together with its accompanying browser regression run. No new experiment was performed for this manuscript revision. The local runtime record identifies Windows 11, Python 3.12.9 and PHP 8.2.12 CLI. The configured model name was `llama3.2`; this metadata neither attests the loaded model digest nor measures the model's contribution independently.

The factual suite comprised **168 endpoint/entity cases across 52 retrieval endpoints**, including 133 expected-success cases and 35 expected-no-data cases. Direct endpoint invocation deliberately bypassed question routing. A case passed when the response satisfied its expected status, result count and specified content, without unexpected or duplicate rows. The attribution suite evaluated **121 cases across 40 endpoints**, checking supporting edge paths, node and edge identity, direction, raw properties and reference bindings. These attribution cases overlap the factual suite. A further **24 HTTP presentation cases** tested result grouping, complete-result representation, constrained answer text and mappings among result items, facts and citations. The **82 routing/status cases** assessed endpoint selection and response contracts separately from factual agreement.

Conversation evaluation comprised **34 scenarios with 54 turns**, each executed using JSON and SSE, producing **108 turn executions and 54 paired transport comparisons**. The scenarios covered paraphrases, spelling or formatting variation, ambiguous names, clarification, pronoun and elliptical follow-ups, topic changes, history-window behavior, stored and missing node properties, multiple entities, unsupported requests, unknown entities, true no-data responses and recovery. Each transport included 40 designated answerable turns, 12 expected-abstention turns and two additional frozen-reference no-data controls. False refusals were counted when designated answerable turns returned an abstaining status; false acceptances were counted when designated abstention turns returned success. These subsets evaluate the fixture-defined response contracts, not a comprehensive medical-safety policy.

Case-level pass rate was calculated as passed cases divided by evaluated cases within each suite. Completeness was assessed by expected counts, missing or extra results, duplicate detection, supporting-path coverage and citation linkage. The benchmark did not provide a separate corpus-wide recall, claim-level precision/F1, RAGAS score or independent scientific-accuracy estimate. Overlapping suites and repeated transport executions were reported separately and were not pooled into an overall accuracy or independent sample count.

Latency was measured sequentially on the local system, with no warm-up exclusion. Median and 95th-percentile (p95) values used nearest-rank estimates; means and maxima were also retained. Direct endpoint timing excludes routing and HTTP. HTTP timing extends through response-body receipt and is not time to first token or browser rendering latency. Supplemental suites tested structured context, entity resolution, terminology, stored properties, requested result types and system consistency. Whole-application frontend, backend and browser regressions supplied supporting software-validation evidence rather than additional independent chatbot questions.

## 3. Results: chatbot evaluation and performance metrics

### 3.1 Retrieval, attribution and response correctness

All **168/168 factual cases (100.0%)**, **121/121 attribution cases (100.0%)**, **24/24 presentation cases (100.0%)** and **82/82 routing/status cases (100.0%)** passed their specified contracts (Table 1). These results demonstrate agreement with the tested stored KG records, supporting paths and software expectations. All 52 catalogued endpoints were represented, but the selected entities and paths do not constitute exhaustive KG coverage. Completeness and provenance checks found no contract failures in their evaluated scopes; these case-level outcomes should not be relabelled as independently measured scientific accuracy or corpus-wide recall.

**Table 1. Chatbot contract evaluation.**

| Metric | Passed / evaluated | Pass rate | Unit / scope |
|---|---:|---:|---|
| Factual retrieval (stored-KG agreement) | 168/168 | 100.0% | endpoint/entity cases; 52 endpoints |
| Per-edge attribution | 121/121 | 100.0% | cases; 40 endpoints |
| Results-first presentation and citation mapping | 24/24 | 100.0% | HTTP questions |
| Routing and response status | 82/82 | 100.0% | contract cases |
| Conversation correctness | 108/108 | 100.0% | 54 turns × JSON/SSE; 34 scenarios |
| JSON/SSE semantic parity | 54/54 | 100.0% | paired turns |
| Answerable turns returned success — JSON | 40/40 | 100.0% | answerable subset |
| Answerable turns returned success — SSE | 40/40 | 100.0% | same subset repeated via SSE |
| Expected abstention — JSON | 12/12 | 100.0% | acceptable-status subset |
| Expected abstention — SSE | 12/12 | 100.0% | same subset repeated via SSE |

Each row is a separate evaluation scope; rows are not independent samples and must not be summed into an overall accuracy. The 121 attribution cases are a subset of the factual evaluation, not 121 additional independent questions. Routing pass rate is not factual accuracy. “Abstention” includes clarification, unknown/unsupported requests or missing information when specified by the fixture; it is not a harmful-request safety benchmark. Both transports repeat the same scenarios.

### 3.2 Conversation behavior and transport parity

All **108/108 conversation turn executions** passed, with semantic parity in **54/54 JSON/SSE pairs**. Passing executions included 14 paraphrase, 12 typo/formatting, eight pronoun-follow-up, six elliptical-follow-up, ten topic-change and six clarification-follow-up executions across both transports; these category counts are subsets of the 108 executions. In each transport, all **40/40 answerable turns** returned success, with **0/40 false refusals**, and all **12/12 designated abstention turns** met their contracts, with **0/12 false acceptances**. The two additional no-data controls per transport also passed. No infrastructure or generation-error turns were recorded in this conversation suite. These small, curated subsets do not establish a universal zero-error rate.

The benchmark's targeted reference-namespace scan reported no known AYUSH-as-PMID errors among the checked occurrences of 147 reviewed AYUSH values. This is a classification regression result, distinct from verifying publication existence, source-document support or the validity of a scientific association.

### 3.3 Response-time performance

For 24 results-first HTTP JSON requests, median and p95 completion times were **438 and 672 ms**, respectively. Conversation requests had median/p95 times of **531/860 ms** for JSON and **531/859 ms** for SSE, with 54 measurements per transport (Table 2). The corresponding means were 478.00, 519.19 and 517.26 ms. The 168 direct endpoint measurements had a median of 8 ms and p95 of 98 ms, but excluded the additional work of routing and HTTP communication. All four timing series had zero missing observations. The similar JSON/SSE summaries are descriptive; the single sequential run does not establish a statistically significant transport difference or predict production latency.

**Table 2. Local sequential response times.**

| Measurement | n | Median (ms) | p95 (ms) | Mean (ms) | Maximum (ms) |
|---|---:|---:|---:|---:|---:|
| Direct endpoint (non-HTTP) | 168 | 8 | 98 | 24.96 | 564 |
| Results-first HTTP JSON | 24 | 438 | 672 | 478.00 | 734 |
| Conversation HTTP JSON | 54 | 531 | 860 | 519.19 | 2,109 |
| Conversation HTTP SSE | 54 | 531 | 859 | 517.26 | 1,937 |

Direct endpoint measurements bypass the router and HTTP and must not be presented as chatbot response latency. HTTP measurements extend through body receipt, not first-token delivery or perceived rendering time. All reported series have zero missing timings; no warm-up observations were removed. A single local sequential run does not establish statistical significance, throughput or production p95.

### 3.4 Supplemental validation and integrity

All supplemental suites passed (Table 3). Their cases overlap other evaluations, and the system-consistency suite includes Explorer and graph-property paths in addition to chatbot behavior.

**Table 3. Supplemental software-contract checks.**

| Supplemental suite | Passed / evaluated |
|---|---:|
| structured context | 30/30 |
| entity resolution | 32/32 |
| resolution context | 23/23 |
| node properties | 23/23 |
| terminology | 39/39 |
| method results | 45/45 |
| fragment methods | 49/49 |
| system consistency | 242/242 |

All rows above passed at 100.0%; units are the respective suite's cases/checks and overlap with the primary evaluation. System consistency includes graph/Explorer/property paths, not only chat answers. These targeted regression fixtures are not a representative sample of unrestricted user queries.

The accompanying whole-application browser suite passed **546/546 executions**, comprising 182/182 each in Chromium, Firefox and WebKit, with no skipped, unexpected or flaky results. These executions are not 546 chatbot questions. Frontend tests passed 120/120, Python evaluator tests 84/84, maintained PHP scripts 43/43 and supplemental PHP scripts 19/19. The audit recorded unchanged checksums for 1,351 frozen fixture files and unchanged contents for 12,657 KG records across 38 tables. Later compatibility checks repeated 168/168 factual cases on each of PHP 5.6 and PHP 8.1 and passed 27/27 packaged browser smoke checks. The subsequent production deployment passed nine Chromium smoke checks and four API check groups. These later checks remain separate from the primary local benchmark and do not establish a complete production evaluation or production capacity.

The quantitative figure set provides a complementary presentation: the [main evaluation figure](../../../figures/chatbot_evaluation/core/F_chatbot_evaluation.pdf) summarizes outcomes and completion times; [Supplementary Figure S1](../../../figures/chatbot_evaluation/supplementary/S1_chatbot_coverage.pdf) shows conversation and endpoint coverage; and [Supplementary Figure S2](../../../figures/chatbot_evaluation/supplementary/S2_chatbot_latency.pdf) shows full timing distributions and matched JSON/SSE observations.

### 3.5 Limitations and missing evaluations

Evaluation used curated regression cases and frozen graph-derived references rather than an independently adjudicated scientific question set or a prospective user study. Perfect case pass rates therefore indicate agreement with tested stored facts and contracts, not absence of errors outside the evaluated scope. Publication existence, source-document entailment, expert assessment of claim support, and clinical efficacy or safety were not established; the saved benchmark reports zero expert-approved claims and scientific accuracy as not established. English-oriented routing, finite terminology coverage, selected entities per endpoint and bounded supported paths limit generalization. Timing observations reflect one local sequential environment and do not establish institutional-server scalability, sustained reliability or first-token latency. Deterministic enumeration and optional model assistance were not compared in a controlled ablation.

**Missing or incomplete evaluations.**

| Evaluation | What remains missing / why it matters |
|---|---|
| Independent scientific/clinical truth | No expert-approved claim set in the primary report; KG agreement cannot substitute for it. |
| Publication/source verification | Historical reviews are partial; no comprehensive source-document entailment score or expert inter-rater agreement is established. Identifier classification is separate. |
| Held-out natural-language generalization | No representative unseen user-question cohort or external validation dataset is established by these regression fixtures. |
| Human usability and answer quality | No verified participant-based satisfaction, readability, task-completion or blinded expert rating is available in this run. |
| Model comparison / ablation | No controlled model-on/off comparison, model-specific hallucination rate or attributable LLM improvement is measured. |
| RAGAS or alternative semantic scores | No verified RAGAS score is supplied by this benchmark; do not substitute KG case pass rates for semantic faithfulness or answer relevancy scores. |
| Exhaustive factual coverage | Endpoint coverage is complete at 52/52 for selected cases; all-entity, arbitrary-path and open-domain coverage is not established. |
| Performance generalization | No current production load/capacity benchmark, time-to-first-token distribution, repeated-run confidence intervals or long-duration reliability estimate. Historical local load experiments are not pooled with this run. |
| Broad safety/adversarial testing | The 12 abstention cases per transport do not establish comprehensive adversarial or medical-safety performance. |

### 3.6 Data and implementation provenance

The three architecture/workflow PDFs were inspected together with their saved visual previews and figure metadata. They are conceptual diagrams, not measurements. The Figure 10 PDF differs from its saved PNG preview in wording and the extent of context annotations; the description above follows the supplied PDF and the inspected implementation, rather than assuming that the preview is identical. The communication description was checked against the frontend transport client, PHP chatbot entry point and orchestrator, documented system scope, and evaluation-runner implementation. Figures and application code were not changed.

The primary evidence remains the [raw benchmark](../../audits/browser-six-20261011/benchmark/benchmark.json), [factual capture](../../audits/browser-six-20261011/benchmark/factual.json), [attribution report](../../audits/browser-six-20261011/benchmark/attribution.json), [presentation report](../../audits/browser-six-20261011/benchmark/presentation.json), [conversation report](../../audits/browser-six-20261011/benchmark/conversation.json), and [full-run summary](../../audits/browser-six-20261011/summary.md). Later [compatibility](../../audits/deployment-remediation-20261011/summary.md) and [production](../../deployments/production-20261011/summary.md) checks have different scopes. The [current system documentation](../../chatbot/CURRENT_SYSTEM_STATUS.md) describes implementation limitations.

The numerical tables retain the previously verified denominators and measurements. Raw inputs are identified in the accompanying source_checksums.json; metric and latency CSVs remain unchanged. The report was revised on 12 October 2026 without new queries, benchmark execution, deployment or KG modification.

## 4. Chatbot Interface and Interactive Features

### 4.1 Chatbot panel and conversational interaction

The ShodhanaKG chatbot is integrated into the shared application interface and is accessible through the **“Ask the graph”** launcher. On desktop screens, activating the launcher opens a right-docked panel below the navigation bar and adjusts the adjacent page content to create a split-window arrangement. This allows users to ask questions while retaining access to the knowledge-graph view or other current page. The panel can be widened on larger screens, closed, or reset to begin a new conversation. On narrow mobile screens, it occupies the full available width rather than maintaining a side-by-side graph layout.

Users can enter questions about diseases, compounds, therapies and their associated KG records, or select a suggested question to initiate an interaction. Follow-up questions are supported through the structured conversation context described in Section 2.1. References to a previously resolved entity can therefore be interpreted without requiring the user to repeat its full name; ambiguous requests prompt clarification. Each supported factual follow-up initiates fresh KG retrieval rather than treating an earlier answer as evidence. User messages and assistant responses remain visually distinct within the conversation history.

Retrieved entities are presented as structured result rows beneath a short heading and introduction. Each row displays the entity name and the number of associated citation records. Initially, up to five result rows are shown, with controls to reveal all available rows or return to the compact view. Selecting a result opens an evidence sheet within the panel, filtered to that result; a separate control provides access to the complete associated source list. Numbered citations retain their links to the supporting records. Evidence cards show reference identifiers, available entity and relationship fields, and meaningful stored properties, including procedure text where present. Namespace filters allow users to inspect all sources or restrict the evidence view to PubMed or AYUSH records. “Open ID” links provide access to the corresponding reference record, and source-pagination controls remain available where applicable. Source counts describe associated citation records, not independently established studies or independent confirmations of a claim.

### 4.2 Advanced Search and reference-centered exploration

**Advanced Search**, available from the main navigation, provides a reference-centered alternative to conversational retrieval. It searches PubMed and AYUSH references and associated records already stored in ShodhanaKG; it does not discover external publications or search article full text. Users can select PubMed, AYUSH, or both namespaces, enter an **ID number**, and search **Keywords** in stored records. Additional **Domain**, **Entity type** and **Relationship** filters narrow the eligible graph records. Entity-type and relationship options update according to the selected domain. Filters can be combined, removed individually through active-filter chips, or cleared using the reset controls.

The results summary reports matching references and their supporting graph edges. Each reference card presents its identifier and namespace, the number of supporting edges, and relationship-specific counts. Within a card, edges are grouped by relationship type and identify their subject and object entities. Expanding an edge reveals its available stored properties and links to open the associated entities in Graph Explorer, preserving their node identity. Cards initially display up to five edges and provide a control to reveal the remaining edges. Reference-level “Open ID” links connect users to the corresponding PubMed or AYUSH record.

Users navigate the reference collection through numbered pages and **Prev/Next** controls, with page sizes of **25, 50 or 100 references**. **“Sort this page”** supports the original order, reference-identifier order, or descending supporting-edge count; this sorting applies to the current page, not the complete matching collection. The submitted query, filters and pagination position are retained during navigation within the browser session. Together, the chatbot and Advanced Search offer complementary routes from an entity-oriented question or a reference-oriented search to the exact stored graph relationships and their source identifiers. These interface capabilities support evidence inspection but do not themselves establish the scientific validity of the cited associations.
