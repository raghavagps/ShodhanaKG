"""Summarize frozen before/after evidence and inventory this implementation."""
import hashlib,json,re,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[3]
AUDIT=ROOT/'webservice/api/tests/fixtures/explorer_context_2026_09_25_v1'
DOC=ROOT/'docs/chatbot/audits/explorer_context_2026_09_25_v1'
def read(name):return json.loads((AUDIT/name).read_bytes())
before,after=read('baseline/benchmark.json'),read('accepted/benchmark.json')
assert not after['source_changed_during_run']
assert all(c['exit_code']==0 for c in after['commands'])
DOC.mkdir(parents=True,exist_ok=True)
changed=[]
with zipfile.ZipFile(AUDIT/'source_before.zip') as archive:
    for name in archive.namelist():
        path=name.replace('\\','/').removeprefix('source_before/')
        f=ROOT/path
        if not name.endswith('/') and f.is_file() and archive.read(name).decode('utf-8').replace('\r\n','\n')!=f.read_text(encoding='utf-8'):
            changed.append(path)
added=['src/hooks/useGraphEntities.ts','src/hooks/useGraphEntities.test.tsx','src/lib/entityNavigation.ts','src/lib/entityNavigation.test.ts','src/lib/graphFocus.ts','src/lib/graphFocus.test.ts','src/components/explorer/SchemaReference.tsx','src/data/schemaReference.json','tests/explorer-context-v2.spec.ts','webservice/api/tests/evaluate_structured_context_v2.py','webservice/api/tests/test_structured_context_v2.php','webservice/api/tests/test_schema_reference.py','webservice/api/tests/run_explorer_context_regressions.py','webservice/api/tests/write_explorer_context_report.py']
files=sorted(set(changed+added))
hashes={p:hashlib.sha256((ROOT/p).read_bytes()).hexdigest() for p in files}
(DOC/'manifest.json').write_text(json.dumps({'changed':sorted(changed),'added':added,'sha256':hashes,'artifacts_directory':str(AUDIT.relative_to(ROOT))},indent=2),encoding='utf-8')
rows=[]
for title,key,total in [('Routing/status','routing_status','total'),('Factual retrieval','factual','total'),('Citation attribution','attribution','cases'),('Results presentation','presentation','cases'),('JSON/SSE conversation','conversation','turn_runs')]:
    rows.append(f"| {title} | {before[key]['passed']}/{before[key][total]} | {after[key]['passed']}/{after[key][total]} |")
timing=[]
for key in ['results_first_http_json','conversation_http_json','conversation_http_sse']:
    b,a=before['latency'][key],after['latency'][key]
    timing.append(f"| {key} | {b['p50_ms']} / {b['p95_ms']} ms | {a['p50_ms']} / {a['p95_ms']} ms |")
system=read('system_final.json')['summary'];context=read('context_v2_final.json')['summary']
text='''# Explorer and structured conversation implementation — 2026-09-25 v1

All seven requested changes are implemented. KG data, edge direction/identity, publication identifiers and existing independent reference fixtures were not modified. The complete browser suite is **not green**: nine pre-existing mobile-overflow checks still fail. This is not a final-paper-readiness claim.

## Root causes and fixes

| Requirement | Finding / classification | Implemented behavior |
|---|---|---|
| How to Use schema | Confirmed bug: relationship rows appeared under the vertex heading, some pairs were incorrect, and the relationship table was incomplete. | Schema tables use 17 actual node types, 21 populated relationship types and 25 observed directed type pairs. RESOLVED_BY is separately marked declared/unpopulated. Screen and print are updated; the non-schema content guard passes. |
| Global search navigation | Confirmed bugs: raw RID interpolation made `#` a URL fragment; links omitted type; graph initialization assumed Disease/Compound; URL changes did not consistently update mounted view state. | Shared typed URL encoding, exact-ID hydration, actual-type validation, URL-driven selection and focus after graph loading. Legacy untyped IDs are resolved rather than guessed. Wrong types/stale IDs display an error. |
| Selected filter names | Confirmed bug: chips depended on graph-limited nodes or transient autocomplete results, falling back to an ID or disappearing. | Independent exact-node query cache supplies actual KG labels in all three Explorers and autocomplete. Loading/unavailable states are explicit; IDs remain internal. |
| Chat visibility | Missing capability and an existing layout restriction: no discovery animation; graph routes excluded the widget. | One 700-ms entrance per tab session, reduced-motion support, no automatic opening, continued keyboard access. Launcher also remains available on graph routes. |
| Edge property appearance | Confirmed presentation inconsistency: bare paragraphs and a monospace heading. | Shared bordered definition-list cards, consistent typography/spacing and wrapping in graph details, provenance and chatbot evidence. Values, zero/false/nested values, IDs, references and edge associations are retained. |
| Connected edge visibility | Confirmed bugs: competing provenance and selection opacity rules, stale event closures, missing focus refresh after graph replacement, and degree-zero node hiding. | Shared focus calculation; selected incident edges and selected edges override dimming. Incoming/outgoing arrowheads retain direction. Graph updates recompute focus; isolated nodes supplied by the API remain visible. Explicit visibility filters still apply. |
| Conversation memory | Existing state was present but injected as text history; follow-up result type could become null; constraints could silently disappear or be detached from the subject. | Version-2 structured routing input, fresh identity/constraint validation, direct pronoun resolution, pending clarification, intent/property/result-type retention, entity-only switches, new constraints, compatible constraint reuse and clarification for incompatible constraints. Fresh retrieval supplies all facts and citations. |

Additional related fixes: shared typed links from mode search and Explorer tables/landing pages; filter visibility and integrated traversal mode survive URL navigation; a constraint introduced in a follow-up is not mistaken for a topic change; edge clicks clear stale node focus. No question-specific answers or KG transformations were added.

## Interfaces and compatibility

- Graph links use URLSearchParams with `node=<exact ID>&type=<actual normalized type>`. Existing `node`, `seeds`, typed filter, numeric legacy and `d:`/`c:` seed forms remain readable. The existing read-only node API resolves labels and rejects non-unique targets.
- `conversation_context.version` is now 2. It contains the active `subject`, `resolved_entities`, ID-bearing `constraints`, `result_type`, `intent`, optional node `property`, and pending clarification. Version-1 input remains accepted after validation.
- Context-bearing requests do not inject synthetic history. The legacy history-only compatibility path reads user turns, not assistant claims. Client-supplied context is a retrieval hint, never factual evidence or authority to create a traversal.
- JSON and SSE return the same context semantics and evidence mapping. Session restoration and reset remain supported. Ambiguous result references require a name/selection; the system does not guess which listed result is meant.

## Before/after regression evidence

The exact Vipadika example already returned formulations before this work, but its persisted `result_type` became null. It now remains Formulation. Existing tests did not expose that structural weakness. New navigation coverage verifies all 17 types; no numerical pre-change browser score is invented for these newly added tests.

| Evaluation | Baseline | Final |
|---|---:|---:|
'''+ '\n'.join(rows)+f'''
| Frontend Vitest | 43/43 | 64/64 |
| New structured-context HTTP checks | Not previously present | {context['passed']}/{context['total']} |
| System-wide independent consistency checks | Existing framework retained | {system['passed']}/{system['total']} |

The system-wide check inspects **10,123 stored edges**, exact properties and IDs, evidence mapping, directed multi-hop paths, Disease/Compound provenance and Integrated Explorer responses. There are no snapshot-added, removed or changed records: `{json.dumps(after['graph_drift'])}`. JSON/SSE parity failures: `{json.dumps(after['transport_parity_failures'])}`.

Additional passes: 6,386 terminology assertions, 218 node-property assertions, 55 entity-resolution assertions, 45 routing/context assertions, 8 new structured-state boundary assertions, existing results/legacy/conversational presentation tests, provenance PHP 20/20, schema scope/coverage tests and terminology compiler policies. Lint: zero errors, 19 existing warnings. Production build: passes. Browser run: 62/71 passes, nine existing mobile-overflow failures; 29 new Explorer/context browser scenarios pass, including all 17 entity types, Integrated selection, history, hidden labels, edge values and launcher behavior.

An optional standalone TypeScript check is not clean: it reports diagnostics in existing examples, test typings and graph DTO types. Vite build and lint pass; Vite transpilation must not be described as a clean standalone TypeScript check.

### Local response time

| Measurement | Baseline p50 / p95 | Final p50 / p95 |
|---|---:|---:|
'''+ '\n'.join(timing)+'''

These are local end-to-end HTTP timings from sequential benchmark requests, with other local validation running concurrently. They are **not** an isolated performance comparison, first-token measurement or load test. No speed improvement is claimed. Passing factual checks establishes agreement with stored KG records, not scientific publication/claim validation.

## New tests

- `src/lib/entityNavigation.test.ts`: 17 typed RID round trips and schema-name normalization.
- `src/hooks/useGraphEntities.test.tsx`: legacy-ID actual-type resolution, graph-independent labels, reset and wrong-type rejection.
- `src/lib/graphFocus.test.ts`: incoming/outgoing focus, provenance conflicts, removed/restored edges, unchanged endpoint data and deselection.
- `tests/explorer-context-v2.spec.ts`: 29 live browser scenarios for search/navigation, all node types, Integrated Explorer, back navigation, hidden seed labels, edge values and launcher access/motion behavior.
- `api/tests/evaluate_structured_context_v2.py`: 30 JSON/SSE checks for follow-ups, entity-only switches, topic changes, preserved/new/cleared constraints, malformed/stale state, missing properties, ambiguity, version-1 compatibility and clarification continuation. It compares fresh retrieval records; assistant prose is not an oracle.
- `api/tests/test_structured_context_v2.php`: eight boundary checks proving structured state does not become synthetic history and cannot be overridden by unrelated history.
- `api/tests/test_schema_reference.py`: independently compares all displayed type pairs with frozen raw records and guards non-schema How to Use content.
- `api/tests/run_explorer_context_regressions.py`: reproducible focused-suite command/log runner.

## Changed files

'''+ '\n'.join('- `'+p+'`' for p in sorted(changed))+'''

## Added implementation, test and report files

'''+ '\n'.join('- `'+p+'`' for p in added)+'''
- `docs/chatbot/audits/explorer_context_2026_09_25_v1/REPORT.md`
- `docs/chatbot/audits/explorer_context_2026_09_25_v1/manifest.json`

The companion manifest records source hashes. All new audit artifacts, baseline source archive, snapshots, intermediate captures, final benchmark, logs and screenshot are under `api/tests/fixtures/explorer_context_2026_09_25_v1/`. Earlier failed intermediate checks are preserved for traceability; use `accepted/benchmark.json`, `context_v2_final.json`, `system_final.json` and `checks/` for final results.

## Remaining limitations

- Nine mobile-overflow failures predate the work and remain; unrelated table/count/graph-limit findings from earlier audits were not folded into this scope.
- Graph traversal/display limits still apply. An unsupported path remains unsupported; schema documentation explicitly distinguishes the unused declared relationship.
- Session context is browser-session state, not a durable account-level conversation database. Ambiguous references to individual returned results require clarification.
- Scientific source-document and domain-expert validation remain separate work. These changes do not broaden scientific claims.
'''
(DOC/'REPORT.md').write_text(text,encoding='utf-8')
print(str(DOC/'REPORT.md'))
