# Figure captions

Abbreviations: KG, knowledge graph; JSON, JavaScript Object Notation; SSE, server-sent events; HTTP, Hypertext Transfer Protocol; p95, 95th percentile.

## F_chatbot_evaluation

Chatbot correctness and response time. (a) Case-level agreement with frozen KG-derived references and software contracts. All listed scopes passed, but suites overlap and must not be summed. (b) Answerability and abstention outcomes for the same turns through JSON and SSE. Two additional reference-based no-data controls per transport are not part of the 40 answerable/12 abstention denominators. (c) Local sequential HTTP completion times: filled circles denote medians and open diamonds denote nearest-rank p95. Connecting lines are descriptive ranges, not confidence intervals. Measurements extend through body receipt, not first-token delivery. Agreement with KG records does not establish scientific or clinical validity.

## S1_chatbot_coverage

Evaluation coverage. (a) All 17 conversation categories, counted once per scenario/turn; 54 turns across 34 scenarios were each executed through JSON and SSE (108 executions). Labels indicate passed/evaluated turns in each transport. (b) Factual cases per endpoint: 8 endpoints had 2 cases, 26 had 3, 16 had 4 and 2 had 5, totaling 168 cases across 52 endpoints. Of these cases, 133 expected success and 35 expected no data. Endpoint coverage does not imply exhaustive entity, path or natural-language coverage.

## S2_chatbot_latency

Local response-time distributions. (a) Empirical cumulative distribution of 168 direct endpoint measurements, bypassing HTTP and routing. (b) Complete observations from 24 presentation JSON, 54 conversation JSON and 54 conversation SSE requests. (c) JSON and SSE timings matched by scenario and turn for 54 pairs; the dashed identity line indicates equal completion time, and overlapping points are retained without jitter. All observations, including extremes, are retained. Timings are from one sequential local run with no warm-up exclusion; they do not estimate production capacity or demonstrate a significant transport effect.
