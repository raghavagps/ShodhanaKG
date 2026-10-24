"""Assemble the current audit from recorded results; no server or KG mutations."""
import json,zipfile,hashlib
from pathlib import Path
ROOT=Path(__file__).resolve().parents[3]
AUDIT=ROOT/'webservice/api/tests/fixtures/ui_result_contract_2026_09_25_v1'
OUT=ROOT/'docs/chatbot/audits/ui_result_contract_2026_09_25_v1'
OUT.mkdir(parents=True,exist_ok=True)
before=json.loads((AUDIT/'baseline/benchmark.json').read_bytes())
after=json.loads((AUDIT/'final/benchmark.json').read_bytes())
checks=json.loads((AUDIT/'checks/summary.json').read_bytes())
methods=json.loads((AUDIT/'method_results_final.json').read_bytes())
context=json.loads((AUDIT/'structured_context.json').read_bytes())
consistency=json.loads((AUDIT/'consistency.json').read_bytes())
changed=[]
with zipfile.ZipFile(AUDIT/'source_before.zip') as z:
    for name in z.namelist():
        p=ROOT/name
        if p.exists() and z.read(name).decode('utf-8').replace('\r\n','\n')!=p.read_text(encoding='utf-8'):
            changed.append(name)
extra=['webservice/frontend/src/components/explorer/PropertyList.tsx','webservice/frontend/src/components/explorer/PropertyList.test.tsx','webservice/frontend/src/lib/referenceUrl.ts','webservice/frontend/src/lib/referenceUrl.test.ts','webservice/api/tests/test_schema_reference.py','webservice/api/tests/generate_schema_reference.py','webservice/api/tests/test_method_result_contract.php','webservice/api/tests/test_reference_record_urls.php','webservice/api/tests/evaluate_method_results.py','webservice/api/tests/run_ui_result_contract_checks.py','webservice/api/tests/write_ui_result_contract_report.py','webservice/frontend/tests/ui-result-contract.spec.ts','webservice/frontend/tests/explorer-context-v2.spec.ts']
files=sorted(set(changed+extra))
manifest={p:hashlib.sha256((ROOT/p).read_bytes()).hexdigest() for p in files}
(OUT/'files.json').write_text(json.dumps(manifest,indent=2)+'\n',encoding='utf-8')
lines=['# UI and result-type audit — 2026-09-25 v1 (completed 2026-09-26)','',
'All six requested areas were implemented. KG records, reference catalog assignments, edge identities/directions, and independent factual fixtures were not changed. This is not a claim of clinical/scientific validation or paper readiness.','',
'## Root causes and changes','',
'1. **Disease edge dimming — confirmed bug relative to the requested display.** Shared focus logic faded unrelated edges and separately dimmed edges outside provenance paths. Disease now disables both edge-dimming paths; node emphasis remains. Selection, provenance updates, filtering and expansion all use the same focus helper. Compound/Integrated retain their existing focus behavior.',
'2. **Purification result type — confirmed language coverage bug.** Explicit output detection missed “technique”, the “Name …” request form, and embedded “How … using a Shodhana method”. Those requests fell through to the general purification traversal ending at a purified product. The shared language interpreter now recognizes these requested method types, selecting the existing typed method traversal. Results presentation also rejects a terminal entity inconsistent with a declared query-plan result type. No entity-specific answers or KG traversals were fabricated. The exact Copper wording already passed the pre-change local API reproduction; related wordings demonstrably failed. Fresh browser tests now cover the exact wording and variants.',
'3. **Property inconsistency — confirmed UI divergence.** Nodes used a compact uppercase grid; edges used a separate card. Both now use PropertyList, including legacy mapped edge properties, with identical font, labels, spacing and value rendering. Structured values, zero and false are retained. Descriptions remain their own node section.',
'4. **Reference destination — confirmed URL bug.** AYUSH always opened generic search. Article IDs differ from portal row IDs: AYUSH 76448 maps to rp6=9603, for example. Shared link resolution now uses the audited, host/path/query-validated record URL already present in the classified reference source. PMID and DOI use their namespace destinations. All references have an ID row and a green Open ID action when a safe direct destination exists. Unmapped AYUSH offers Copy ID and Search AYUSH, clearly labelled as a fallback. Unclassified/conflicting references receive no guessed destination. Canonical evidence objects stay unchanged; a trial additive backend URL was removed when strict legacy-evidence tests detected its incompatibility.',
'5. **Launcher notice — insufficient existing animation.** A greeting appears after one second for up to seven seconds, once per tab session. It can be dismissed or clicked to open chat, never opens the full panel itself, and works without animation under reduced-motion preferences. Closed chat is now also CSS-invisible so it cannot receive focus as an opacity-only panel.',
'6. **Schema — outdated representation.** The schema-only screen and print tables now show 17 Domain → Entity node types and 21 populated edge types, with actual directed pairs and observed nonempty stored property keys. RESOLVED_BY had no stored edges and is omitted from this observed-schema table. No backend schema or relationships were deleted. Ref is labelled Source ID; property lists are unions across observed edges, not promises that every edge contains every property. The strict non-schema How to Use content guard passes.','',
'## Regression results','',
'| Dimension | Baseline | Final |','|---|---:|---:|']
for key,label,total in [('routing_status','Routing/status','total'),('factual','Factual retrieval','total'),('attribution','Attribution','cases'),('presentation','Answer/presentation','cases'),('conversation','Conversation turn runs','turn_runs')]:
    lines.append(f"| {label} | {before[key]['passed']}/{before[key][total]} | {after[key]['passed']}/{after[key][total]} |")
lines += ['| Frontend unit tests | 64/64 | 73/73 |',
'| Browser regression group | Prior phase: 62/71 | 69/78; same nine mobile-overflow failures |',
'',f"New independent method checks: **{methods['summary']['passed']}/{methods['summary']['total']}** (15 questions, JSON and SSE, plus 15 parity comparisons). Expected method node IDs and UNDERGOES edge IDs come directly from the captured raw KG, not the chatbot or model.",
f"Structured conversation checks: **{context['summary']['passed']}/{context['summary']['total']}**.",
'System consistency: **242/242**, including **10,123** stored edges, exact property association, integrated paths and provenance.',
'AYUSH: **147/147** audited mappings, checking every bound edge against the independent publication audit. This verifies destination construction, not current portal availability or publication claims.',
'Other passing suites: 16 method/result-contract checks; 6,386 terminology assertions; 218 node-property assertions; 55 entity-resolution assertions; 95 legacy answers / 2,961 edge scopes; structured state boundaries; conversation routing; context immutability; provenance; reference classification; schema and terminology-compiler guards.',
'Lint: zero errors (19 existing warnings). Production build passed. Vite build is not a separate full TypeScript typecheck.',
f"Final benchmark source changed during run: **{after['source_changed_during_run']}**. JSON/SSE parity failures: **{len(after['transport_parity_failures'])}**. KG drift: **{json.dumps(after['graph_drift'])}**.",
'', '### Response times','', '| Local sequential HTTP measure | Baseline p50 / p95 ms | Final p50 / p95 ms |','|---|---:|---:|']
for key in ['results_first_http_json','conversation_http_json','conversation_http_sse']:
    a,b=before['latency'][key],after['latency'][key]
    lines.append(f"| {key} | {a['p50_ms']} / {a['p95_ms']} | {b['p50_ms']} / {b['p95_ms']} |")
lines += ['', 'Latency is local, sequential, full-response timing; these are not load-test or first-token measurements.', '',
'## New and updated tests','',
'- test_method_result_contract.php: generic wording across compounds, output-noun precedence, wrong-result-type rejection.',
'- evaluate_method_results.py: Copper, Lead and Sulphur; five wordings; raw-edge/node completeness; JSON/SSE equality.',
'- test_reference_record_urls.php and referenceUrl.test.ts: all audited AYUSH mappings; distinct PMID/DOI namespaces; malformed/untrusted URLs and missing mappings.',
'- PropertyList.test.tsx: identical node/edge markup, structured values, zero/false.',
'- graphFocus.test.ts: no Disease edge fading through selection, provenance and graph changes.',
'- ui-result-contract.spec.ts: greeting/reduced-motion/session dismissal, all-edge visibility under selection/filtering, real browser method requests, and 21-edge schema.',
'- test_schema_reference.py: independent node/type/pair/property coverage and non-schema scope guard.',
'- ReferenceLinks, IntegratedPaths and ConversationalMessage tests: updated action labels and namespace destinations. Existing Explorer screenshot capture now uses per-test output rather than overwriting an old audit image.', '',
'## Remaining limitations','',
'- The nine existing mobile horizontal-overflow failures remain on the Disease, Compound and Integrated explorer landing, graph and table routes. They are outside this approved change set; the complete browser suite is not green.',
'- AYUSH live landing-page verification timed out even outside the network sandbox; the web fetch tool also could not access it. URLs match the independent audited mappings, but current publisher/portal reachability is not claimed.',
'- Language understanding remains bounded and can still require clarification for unrecognized/ambiguous wording. The result-type guard applies where the validated query plan declares a result type; it does not fabricate missing type metadata for historical responses.',
'- The intermediate after/ benchmark overlapped implementation review and is superseded by final/. Its source-change/parity findings must not be presented as the final result.',
'- A user-managed dev-server restart is required after these files and the build. Post-restart browser confirmation is pending at report creation.', '',
'## Files changed or added','']
lines += ['- `'+p+'`' for p in files]
lines += ['', 'Generated audit artifacts are under `api/tests/fixtures/ui_result_contract_2026_09_25_v1/`; this report and `files.json` are under `docs/chatbot/audits/ui_result_contract_2026_09_25_v1/`. The source archive is the pre-edit baseline, and source/fixture hashes are recorded by the benchmark.']
(OUT/'README.md').write_text('\n'.join(lines)+'\n',encoding='utf-8')
print(OUT/'README.md')
