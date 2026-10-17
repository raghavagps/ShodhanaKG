# Production deployment — 28 September 2026

Deployed and smoke-verified at https://webs.iiitd.edu.in/raghava/shodhanakg/. This is a deployment verification, not a new full release benchmark or scientific validation.

## Backup and integrity

Production root: `/gpsr/webserver/cgidocs/raghava/shodhanakg`.

The complete previous application was archived before deployment at `.deployment-backups/pre-deploy-20260928.tar.gz` beneath that root. SHA-256: `59675d433b649481776eef20e1a9c56e9f7126f2aad7732a4c51fbd7dc4dd485`. The previous API directory is additionally retained as `.deployment-backups/previous-api-20260928`. Backup access over HTTP returns 403.

Final deployment archive: `.deployment-backups/release-final.tar.gz`; SHA-256: `7edc276be649abe0809a5f28803b0fa95d56fbdeca368d99a3b87e9b6702def6`. The matching local package is `.audit-temp/production-20260928/release-final.tar.gz`. All 187 checked deployed files match the final manifest. The one deliberate exception is `api/config.php`, which retains server credentials and deployment-specific settings; do not overwrite it with the package defaults.

Rollback: retain the failed/current release privately first, inspect the original backup archive, and restore its application files under the production root with the original permissions. Restore the original API/configuration and index together; preserve the private backup directory and database. Do not import or restore KG data as part of code rollback. Old hashed frontend assets were retained. No global Apache settings or shared services were restarted.

## Deployment fixes

- Apache PHP 5.6 lacks mbstring and cURL, with URL fopen disabled. Bundled the MIT-licensed Symfony mbstring polyfill (v1.19.0) and added a bounded, TLS-verifying socket HTTP transport for optional Ollama assistance.
- Ollama `llama3.2` now responds through the actual Apache PHP runtime. Set loopback URL and integer `keep_alive: -1`; warmed the model to avoid the observed approximately 70-second cold load. The observed loaded size was 2,561,524,365 bytes. Deterministic KG answers remain independent of model generation.
- Production graph RIDs differ from the frozen local graph. Reconciled all 12,657 unique records using full vertex properties, directed endpoints and complete edge properties, preserving duplicate multiplicities. Rebound 10,123 edge bindings in 410 reference catalog entries **only in the deployment catalog**. An offset-only mapping was rejected. Reference namespaces/identifiers and KG records were not changed; the local source catalog and frozen fixtures remain unchanged.
- Corrected subdirectory URLs for the landing animation, its bundled font stylesheet, and the 404 return-home link. Published the complete static build.
- Protected configuration, internal libraries/catalogs, private backups and the legacy export from direct HTTP access. Removed the temporary runtime diagnostic endpoint after verification.

## Verification

| Check | Result |
|---|---:|
| Production API/runtime smoke checks | 11/11 passed |
| Chromium production route/graph/chat/static-path checks | 10/10 passed |
| Browser page errors / failed requests | 0 / 0 |
| HTTP access-control and routing checks | 8/8 passed |
| Frontend tests | 101/101 passed, 28 files |
| Maintained PHP suites after transport changes | 43/43 passed |
| Legacy-host transport checks, PHP 5.6 | 11/11 passed |
| Legacy-host transport checks, PHP 8.2 | 11/11 passed |
| Focused provenance-routing checks | 33/33 passed |
| Deployed package file comparisons | 187 matched, 0 mismatches |
| KG before/after | 12,657 / 12,657; full records unchanged |
| Lint / typecheck / production build | Passed; 7 existing lint warnings |

The final keep-alive numeric conversion also passed focused hardening, audit-boundary, node-property and terminology checks. The last public HTML edit only changes the stylesheet URL; subsequent production browser verification passed. Earlier failed deployment checks are retained in the private pre-binding verification directory; they are not counted as successful final checks.

Production examples: Psoriasis symptoms returned 19 results/38 citations; lead purification methods 2/11; misspelled Copper methods 2/20; Copper media **with evidence** 10/70 via the provenance endpoint; Vipadika therapies 7/7, followed by 11 formulation results. JSON/SSE and all three graph endpoints were exercised. These are selected smoke cases, not complete factual coverage. Raw results and a machine-readable metrics table accompany this report.

## Files changed for deployment support

- `api/core/PhpCompatibility.php`
- `api/core/SocketHttp.php` (new)
- `api/core/LLMClient.php`
- `api/vendor/polyfill-mbstring/` (bundled dependency and license)
- `api/tests/test_legacy_host_transport.php` (new)
- `src/pages/ModeSelection.tsx`
- `src/pages/NotFound.tsx`
- `src/pages/NotFound.test.tsx` (new)
- `public/graph_animation.html`
- `docs/deployment/PHP56_COMPATIBILITY.md`
- `BACKEND_DOCUMENTATION.md`
- This deployment report, raw evidence, and D:-located deployment scripts/artifacts.

Server-specific changes additionally comprise the protected configuration/storage permissions, root/API `.htaccess`, the verified RID-bound deployment catalog, and built frontend assets. No database writes were performed.

## Remaining operational limits

PHP 5.6 is end-of-life; compatibility does not make it a supported secure runtime. Schedule a supported PHP upgrade with the server administrator. Ollama retention is not a startup guarantee: after an Ollama restart, warm `llama3.2` again; memory pressure or other clients can affect residency. Without cURL, optional model streaming uses buffered generation fallback. ArcadeDB and Ollama were observed listening on all interfaces; the administrator should verify network/firewall restrictions. No firewall changes or system-level service changes were made. Browser verification used the production hostname with a host-resolver mapping to the provided private IP; public Internet reachability was not independently established. A new full benchmark, load test, and security audit were not run for this deployment.
