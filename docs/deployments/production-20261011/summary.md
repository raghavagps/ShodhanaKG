# Production deployment — 11 October 2026

The user-authorized release was activated at https://webs.iiitd.edu.in/raghava/shodhanakg/. No service restart, runtime upgrade, institutional configuration change, or KG write was performed.

## Integrity and verification

| Check | Verified result |
|---|---:|
| Deployed release manifest | 230/230 files match |
| Protected production config and RID-bound reference catalog | Both unchanged |
| KG before/after, full records keyed by RID | 12,657/12,657 identical across 38 tables |
| Fresh pre-deployment application backup | 205 files, archived contents verified |
| Production assistant health | Online |
| Production Chromium UI smoke | 9/9, no page errors or unexpected request failures |
| Additional production API check groups | 4/4 |

Additional checks verified a cited Copper-method JSON answer; equality of answer, results, citations, query plan and status between JSON and one-terminal-event SSE; a Vipadika therapies-to-formulations follow-up retaining the same entity; and denied private backup/config/catalog access plus rejection of an unknown API path. Raw responses are in `api-smoke.json`. These are four check groups, not four independent benchmark cases.

**Final deployment decision: technical deployment PASS; release left active. Runtime-security signoff remains outstanding.**

Browser checks covered the landing page, three Explorer routes, Advanced Search, AYUSH ID 76448, RID-based Explorer selection, a cited Copper-method answer, and the subdirectory-aware 404 home link. Chromium's intentional SSE reader cancellation after the completed answer is retained separately in the raw log.

The extra API diagnostic's first attempt incorrectly required a query plan from the legacy disease-formulation route. The actual response successfully preserved Vipadika and returned formulations with citations. The diagnostic was corrected to assert the documented conversation_context fields. Both attempts are retained; no application or existing test was changed.

## Backup and rollback

Private server directory:
`/gpsr/webserver/cgidocs/raghava/shodhanakg/.deployment-backups/release-20261011-184233`

The fresh `pre-deploy.tar.gz` SHA-256 is:
`937c7da60895e3d3c8bcf4c9a18882af63d538c240bd7993db72e030eed9dd82`.

The previous API directory, including its runtime storage and preserved permissions, is retained as `previous-api`. Archive contents exclude runtime storage and older backups. The deployment activation had an automatic rollback path on failed health/integrity checks; it was not needed. A manual rollback can be executed over authenticated SSH using:

```sh
python3 /gpsr/webserver/cgidocs/raghava/shodhanakg/.deployment-backups/release-20261011-184233/server_deploy.py rollback
```

That restores the previous API and archived application files and verifies previous hashes. It does not restore or modify KG data. Newly added immutable assets may remain unreferenced. No destructive live rollback rehearsal was performed.

Release archive SHA-256:
`473b6844ddf318e120ab5f93d0f11b1b6a0f6b85c8e76b8e022df2302298b90d`.

## Scope and remaining risk

Deployment tests accessed the HTTPS hostname through the VPN/private server address with hostname resolution overridden. They do not independently establish reachability from the public Internet. These are production smoke checks, not a new full benchmark or a scalability/clinical validation.

Previously verified local evidence remains separate: 546/546 full browser tests, 120/120 frontend tests, 27/27 packaged browser checks, and PHP 5.6/8.1 compatibility and factual checks. See the deployment-remediation audit for exact scopes. Lint retains eight existing warnings.

The installed PHP runtimes remain upstream end-of-life. Deployment authorization does not establish vendor security maintenance or close the institutional runtime-security signoff requirement. Application deployment success must not be read as unconditional security approval.

All local deployment scripts, raw checks, screenshots and checksums were saved on D:. No application source, existing tests, fixtures or KG records were edited during this deployment.
