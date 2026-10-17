# Quantitative evaluation

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

All rates apply only to the named suite and denominator. Suites overlap. Post-hoc error rates are lower-is-better.

| Request class | n | Mean (ms) | Median (ms) | p95 (ms) | Maximum (ms) |
|---|---:|---:|---:|---:|---:|
| direct endpoint not http | 168 | 25.12 | 8 | 91 | 681 |
| results first http json | 24 | 423.75 | 406 | 610 | 687 |
| conversation http json | 54 | 607.74 | 468 | 766 | 9859 |
| conversation http sse | 54 | 456.06 | 453 | 765 | 1953 |

Nearest-rank percentiles; sequential single-client local requests, no warmup removal. Includes failed requests. End-to-end HTTP body receipt; not first-token latency or load testing.
