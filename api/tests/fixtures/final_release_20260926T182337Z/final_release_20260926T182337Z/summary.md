# Final release audit — FAIL

**Deployment readiness: not approved.** The fixed backend benchmark passes, but the wider audit reproduces application defects beyond its sampled cases. Source code and KG data were not repaired during this audit.

- Factual retrieval **168/168**, routing/status **82/82**, attribution **121/121**, presentation **24/24**, conversation **108/108**; all passed on their fixed references. These overlapping suites do not imply universal accuracy.
- Frontend **95/95 tests**, Python **73/73 tests**, maintained PHP **14/14 scripts**, lint/build passed. PHP syntax **406/407 files**.
- Browser retry: **489/489 expected outcomes; 0 unexpected, 0 flaky, 0 skipped; exit 0**. Initial two-worker run aborted with memory/launch errors; both attempts retained.
- Supplemental PHP: **14/35 scripts without unexpected failures**, **21/35 with failures**, including stale harnesses/contracts and genuine application defects. Four server-managing scripts excluded. One of the 14 records five expected legacy-validator limitations.
- Post-hoc: therapeutic-use evidence error for **15/52 compounds**; disease questions wrongly return purified-form routes for **50/52** compound seeds (two ambiguous). Multi-output method/media requests also lose a requested output type.
- Warnings: **19 lint warnings**; large bundle/outdated Browserslist; **7 advisory-flagged packages** (3 high, 3 moderate, 1 low), applicability unresolved.
- Protected baseline: **1567 files**, changed **0**, missing **0**. Raw KG before/after: **12657 records**, added 0, removed 0, changed 0.

Before release: fix and independently test method-aware deduplication and requested-output precedence; define multi-output/procedure intent behavior; repair stale/broken test harnesses and diagnostic syntax; triage dependency advisories; rerun the full gate on adequate resources. Do not alter KG identities or weaken validation to obtain a pass.

Exports: [metrics table](metrics_table.md), [CSV](metrics.csv), [machine-readable metrics](metrics.json), [failure investigation](failures.md), [manuscript text](manuscript_results.md). Raw per-case outputs are in release/benchmark; all logs and supplemental probes are retained. SHA-256 manifest and ZIP accompany this report.
