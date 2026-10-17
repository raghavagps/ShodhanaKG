# Manuscript evaluation results

## Methods

We evaluated the local ShodhanaKG implementation on 26–27 September 2026 using versioned regression fixtures and read-only live KG retrieval. Exact retrieval outputs were compared with frozen reference cases derived independently from raw KG records, rather than LLM-generated answers. The fixed evaluation comprised 168 factual cases across 52 retrieval endpoints, 121 attribution cases across 40 legacy endpoints, 24 results-first answer cases, 82 routing/status cases, and 34 conversation scenarios containing 54 turns repeated through JSON and SSE (108 turn executions). Additional contract suites assessed structured context, entity resolution, node properties, terminology and method result types; a 242-check consistency suite included all 10,123 stored edges and 1,155 treatment-phase edges. Software checks included 95 frontend unit tests, 73 Python tests, 14 maintained PHP scripts, 35 additional PHP scripts, syntax checks, a build and 489 planned browser executions across Chromium, Firefox and WebKit. Following failures in the broader scripts, a separate post-hoc diagnostic sweep examined all 52 Compound nodes. Response times were measured for sequential local requests, with nearest-rank percentiles and no warm-up exclusion. Suites overlap and were not pooled into a single accuracy estimate.

## Results

The fixed suites passed 168/168 factual cases (100%), 121/121 attribution cases (100%), 24/24 answer-presentation cases (100%) and 82/82 routing/status cases (100%). Presentation checks retained 375/375 within-answer grouped entity occurrences and 1,088/1,088 citation occurrences; these are not distinct database entities or publications. All 108/108 conversation executions passed, with semantic parity in 54/54 paired turns. Each transport had 0/39 false refusals among answerable turns and 13/13 correct abstentions; these subsets do not exhaust all 54 turns. Median/p95 complete-response times were 406/610 ms for 24 results-first JSON answers, 468/766 ms for 54 conversational JSON requests and 453/765 ms for 54 SSE requests. The JSON maximum was 9,859 ms. All 95 frontend tests, 73 Python tests and 14 maintained PHP scripts passed. The full initial browser run aborted with allocation/launch errors; its unchanged one-worker retry recorded 489/489 expected outcomes; 0 unexpected, 0 flaky, 0 skipped; exit 0. Broader validation did not support release approval: 21/35 supplemental PHP scripts reported unexpected failures (including obsolete harnesses/expectations), one of 407 PHP files failed syntax checking, and the post-hoc sweep found therapeutic-use evidence-validation errors for 15/52 compounds (28.85%). The disease-request diagnostic template incorrectly selected purified-form results for 50/52 seeds (96.15%); two required clarification. These diagnostic rates are separate from fixed-fixture pass rates. Seven dependency package entries were flagged by the advisory audit (three high, three moderate, one low); exploitability was not established. The overall release gate was **FAIL**.

## Metrics

| Metric | Observed / denominator | Percentage | Scope |
|---|---:|---:|---|
| Factual retrieval exact-match cases | 168/168 | 100.00% | fixed_benchmark |
| Factual success controls | 133/133 | 100.00% | fixed_benchmark |
| Factual no_data controls | 35/35 | 100.00% | fixed_benchmark |
| Reference-tested retrieval endpoints | 52/52 | 100.00% | fixed_benchmark |
| Legacy evidence attribution | 121/121 | 100.00% | fixed_benchmark |
| Results-first presentation contracts | 24/24 | 100.00% | fixed_benchmark |
| Deterministic results-first generation | 24/24 | 100.00% | fixed_benchmark |
| Result entity occurrences retained | 375/375 | 100.00% | fixed_benchmark |
| Citation occurrences retained | 1,088/1,088 | 100.00% | fixed_benchmark |
| Routing/status contracts | 82/82 | 100.00% | fixed_benchmark |
| Conversation turn executions | 108/108 | 100.00% | fixed_benchmark |
| JSON/SSE semantic parity | 54/54 | 100.00% | fixed_benchmark |
| JSON false refusals | 0/39 | 0.00% | fixed_benchmark |
| JSON correct abstentions | 13/13 | 100.00% | fixed_benchmark |
| JSON false acceptances | 0/13 | 0.00% | fixed_benchmark |
| SSE false refusals | 0/39 | 0.00% | fixed_benchmark |
| SSE correct abstentions | 13/13 | 100.00% | fixed_benchmark |
| SSE false acceptances | 0/13 | 0.00% | fixed_benchmark |
| Structured context | 30/30 | 100.00% | fixed_benchmark |
| Entity resolution | 32/32 | 100.00% | fixed_benchmark |
| Resolution context | 23/23 | 100.00% | fixed_benchmark |
| Node properties | 23/23 | 100.00% | fixed_benchmark |
| Terminology | 39/39 | 100.00% | fixed_benchmark |
| Method results | 45/45 | 100.00% | fixed_benchmark |
| Fragment methods | 49/49 | 100.00% | fixed_benchmark |
| System consistency | 242/242 | 100.00% | fixed_benchmark |
| Stored edge property contract scan | 10,123/10,123 | 100.00% | fixed_benchmark |
| Treatment phase scan | 1,155/1,155 | 100.00% | fixed_benchmark |
| Frontend unit tests | 95/95 | 100.00% | software |
| Python evaluation/HTTP tests | 73/73 | 100.00% | software |
| Maintained PHP regressions | 14/14 | 100.00% | software |
| PHP syntax | 406/407 | 99.75% | software |
| Browser retry expected outcomes | 489/489 | 100.00% | software |
| Therapeutic-use endpoint errors (post-hoc) | 15/52 | 28.85% | post_hoc |
| Wrong purified-form route (post-hoc) | 50/52 | 96.15% | post_hoc |
| Supplemental scripts without unexpected failures | 14/35 | 40.00% | software |

| Request class | n | Mean (ms) | Median (ms) | p95 (ms) | Maximum (ms) |
|---|---:|---:|---:|---:|---:|
| direct endpoint not http | 168 | 25.12 | 8 | 91 | 681 |
| results first http json | 24 | 423.75 | 406 | 610 | 687 |
| conversation http json | 54 | 607.74 | 468 | 766 | 9859 |
| conversation http sse | 54 | 456.06 | 453 | 765 | 1953 |

Percentages use the stated denominators. False-refusal, endpoint-error and wrong-route rates are lower-is-better. Test/script counts are not interchangeable. Citation counts are occurrences across evaluated answers. Local response times include complete-body receipt, not time to first token or concurrent-load performance. Post-hoc probes used one diagnostic phrasing per compound and were not a held-out natural-language test set.

## Limitations

This evaluation measures software behavior and agreement with stored KG records, not the scientific correctness of those records, publication support for every association, treatment efficacy or clinical safety. The fixed suite is finite and non-random; endpoint coverage did not prevent defects in unsampled entities and paraphrases, as the post-hoc findings demonstrate. All 24 results-first answers used deterministic generation; the study does not estimate general free-form LLM answer quality. Existing deterministic validators share some implementation contracts with production, although factual references were built independently from raw KG records. Browser tests mix live integration with intercepted/fixture-driven UI checks. Browser resource failures and excluded server-managing scripts limit whole-suite conclusions. Local sequential timing is not a deployment performance guarantee. Publication/source-document review remains partial and no expert-approved clinical claims were established by this benchmark.
