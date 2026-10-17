# Assistant offline/unresponsive diagnosis and local recovery fixes

Date: 2026-10-07. Scope: local implementation and verification. **Production incident cause and deployed recovery remain unverified.** The remote network attempt was blocked by the sandbox; the requested elevated connection attempt was interrupted. No production logs were obtained, no code was deployed, and no production service was stopped.

## Findings and evidence

| Finding | Evidence / certainty |
|---|---|
| Completed SSE answers could keep the widget busy until HTTP EOF/deadline | Reproduced by a stream delivering a valid final `done` event and leaving its connection open. Before the fix the promise remained pending. The new regression now completes and cancels the remaining reader. |
| A failed five-second health probe disabled all input | Confirmed in widget code and its old health unit test. A failed probe was treated as authoritative even though the real API might already be healthy. Recovery previously depended on focus/online events or the next 30-second probe. |
| Stale health results could contradict a successful conversation | A health request started before a chat could finish later and overwrite status. New regression verifies that a stale failure is ignored. |
| The ArcadeDB raw socket fallback waited for connection close and did not enforce its read deadline | Controlled server sent a complete JSON response and held the socket open for three seconds. Before: 3.109 s despite a one-second PHP read-timeout setting. After: 0.078 s for Content-Length and 0.063 s for chunked framing; stalled peer fails at 1.078 s under a one-second budget. These are controlled-transport measurements, not overall application speedup. |
| PHP 5.6 stream-wrapper timeout could exceed the shared budget | The new one-second-budget test initially took 2.079 s on PHP 5.6. Legacy hosts without cURL now use the explicit deadline-bounded socket implementation first; the unchanged test passes on PHP 5.6 and PHP 8.2. |
| Production-build default was unsafe | `.env.production` embedded `http://127.0.0.1:8081`, which points to each visitor's own machine. The historical deployment script explicitly overrode this, so it is a confirmed packaging risk, not proof of the current production incident. Default is now same-origin with the configured Vite base path. |
| Ollama introduces variable optional latency | Local tags endpoint returned 200 with llama3.2:latest. A live clarification proposal took 9.843 s; SSE connection acknowledgement arrived in 0.046 s. Invalid model interpretation was rejected, preserving clarification and KG validation. |
| Local health probes can queue behind work | During a separate real model request, health waited 2.500 s while the chat took 2.703 s, then returned 200. This illustrates single-worker local PHP contention, but did not reproduce a five-second probe timeout in that attempt and does not establish Apache worker behaviour in production. |

Six ordinary local KG/SSE requests passed before the fix in 0.375-0.547 s. No baseline evidence of a persistent ArcadeDB or Ollama outage was found. The reproduced transport and readiness defects explain ways the symptoms can occur; they do not prove which one caused a particular production incident.

## Fixes

- Use the authoritative SSE final event as completion, preserving its full answer, citations and structured conversation metadata. Release/cancel the reader without waiting for HTTP close. Duplicate terminal events already present in the received batch are still rejected; events after the terminated protocol are not awaited.
- Treat health checks as advisory: do not lock out a fresh real request. Retry failed probes after five seconds; retain 30-second healthy polling and focus/online recovery.
- Avoid probes while a conversation request is active, ignore stale probe results, and release request/busy state through a guarded `finally`, including failures and cancellations. Conversation reset still ignores late replies.
- Give the health DB command a two-second budget inside the frontend's five-second deadline and disable health-response caching.
- Bound database network fallback work, complete socket reads using Content-Length/chunked framing, preserve HTTP status/body validation and authentication, enforce response limits, and close sockets on every exit.
- Send/flush an initial SSE comment, disable response buffering/compression where permitted, and preserve JSON/SSE semantic events. Add server-side failure location/class logging without dumping questions, credentials or raw database records.
- Make the ordinary production build and chatbot URL resolution use the deployment base path by default. Explicit configured API locations still override the default.

## Verified results

| Check | Result |
|---|---|
| Lint | 0 errors; 9 pre-existing warnings (8 UI fast-refresh warnings and one plotting-environment unused directive) |
| Typecheck / production build | Passed; generated assets contain no `127.0.0.1:8081` API endpoint |
| Frontend suite | 104/104 tests, 31 files |
| Controlled DB framing/deadline/recovery tests | 3/3 on PHP 8.2 and 3/3 on PHP 5.6 |
| Existing legacy host transport checks | 11/11 on both PHP versions |
| New Chromium recovery checks | 2/2: automatic recovery without reload, and failed-request recovery followed by consecutive/multi-turn retrieval |
| Chromium route/mobile smoke | 20/20 |
| Live node/FAQ JSON/SSE checks | 10 scenarios, 20 responses; parity passed |
| Live provenance / frozen-record attribution | 2 unittest methods, 12 responses across Copper, Lead and Sulphur; passed |
| Provenance routing | 33/33 |
| Stored-node checks | 218/218 |
| Traversal/provenance contracts | 195/195 |
| Structured context | 8 boundary assertions and missing-referent clarification passed |
| Five repeated health probes | 5/5 HTTP 200, 0.031-0.047 s, `no-store` headers present |
| Isolated API with unavailable Ollama | Copper methods and Vipadika therapies succeeded; ambiguous Copper question returned clarification with `model_unavailable`. Three responses each had exactly one final SSE event. |

The Ollama-outage check used an agent-owned temporary local PHP process with an unavailable model URL. The existing model, dev server, database and production services were not stopped. Browser failures were injected only into that browser's requests. The original failed reproductions and initial test-harness corrections are retained in the audit folder. One browser test was corrected to inspect delivered UI/session state rather than CDP response bodies after the client correctly cancelled its completed SSE reader.

Browser tests used fresh sessions on the running local development server. After the user confirmed the restart, both recovery checks passed again (2/2 in 29.2 s; browser-post-restart.log). No agent-managed restart occurred. Only final report/summary updates followed this verification. This is not a full release benchmark, scalability result or scientific validation.

## Files changed

Application/configuration:
- `.env.production`
- `src/lib/chatApi.ts`
- `src/components/chatbot/ChatbotWidget.tsx`
- `api/db.php`
- `api/chatbot_health.php`
- `api/chatbot_response.php`
- `api/core/SocketHttp.php`
- `api/core/ChatResponseTransport.php`

Regression coverage:
- `src/lib/chatApi.recovery.test.ts` (new)
- `src/lib/chatApi.stream.test.ts`
- `src/components/chatbot/HealthRecovery.test.tsx`
- `api/tests/test_db_transport_recovery.py` (new)
- `tests/chat-recovery.spec.ts` (new)

This report: `docs/chatbot/assistant_recovery_2026_10_07.md`. Build artifacts: existing `dist/`. Raw results and captures: `.audit-temp/chat-recovery/` on D:. No KG data/schema, frozen reference fixtures or dependencies were changed.

## Remaining verification

Production access is still required to inspect actual Apache/PHP logs, deployed asset API URLs, worker saturation, ArcadeDB connectivity, Ollama endpoint/model configuration and proxy buffering. The local fixes have not been deployed or repeatedly tested on the deployment server. Do not describe the production incident as resolved on the basis of these local checks.
