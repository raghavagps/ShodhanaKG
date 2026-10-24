"""Assemble the implementation audit from saved baseline and regression artifacts."""
import hashlib
import json
import math
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
BEFORE = ROOT / 'webservice/api/tests/fixtures/system_consistency_baseline_v1'
AFTER = ROOT / 'webservice/api/tests/fixtures/system_consistency_after_v1'
FINAL = ROOT / 'webservice/api/tests/fixtures/system_consistency_final_benchmark_v2'
OUT = ROOT / 'docs/chatbot/audits/system_consistency_2026_09_24_v1'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def latency(rows):
    values = sorted(r['latency_ms'] for r in rows)
    return {'n': len(values), 'p50_ms': values[math.ceil(len(values)*.5)-1], 'p95_ms': values[math.ceil(len(values)*.95)-1]}

def main():
    OUT.mkdir(parents=True, exist_ok=True)
    old = read(BEFORE / 'all_source_hashes.json')
    candidates = list((ROOT / 'api').glob('*.php')) + list((ROOT / 'api/core').glob('*')) + list((ROOT / 'webservice/api/tests').glob('*.php')) + list((ROOT / 'webservice/api/tests').glob('*.py')) + list((ROOT / 'src').rglob('*')) + list((ROOT / 'tests').glob('*'))
    changes = []
    for path in candidates:
        if not path.is_file() or path.suffix not in ('.php', '.py', '.ts', '.tsx', '.json', '.css'): continue
        name = path.relative_to(ROOT).as_posix(); digest = hashlib.sha256(path.read_bytes()).hexdigest()
        if old.get(name) != digest: changes.append({'path': name, 'change': 'modified' if name in old else 'added', 'sha256': digest})
    changes.sort(key=lambda r: r['path'])
    artifacts = []
    for folder in [BEFORE, AFTER, ROOT / 'webservice/api/tests/fixtures/system_consistency_final_benchmark_v1', FINAL, ROOT / 'dist', ROOT / 'test-results', ROOT / 'playwright-report']:
        if folder.exists(): artifacts += [p.relative_to(ROOT).as_posix() for p in folder.rglob('*') if p.is_file()]
    artifacts += ['webservice/api/tests/fixtures/' + f for f in ('expanded_factual_cases_v4.json', 'chatbot_evaluation_cases_v4.json', 'presentation_cases_v4.json')]
    artifacts += ['docs/chatbot/audits/system_consistency_2026_09_24_v1/' + f for f in ('REPORT.md', 'file_inventory.json', 'metrics.json')]
    (OUT / 'file_inventory.json').write_text(json.dumps({'source_files': changes, 'generated_artifacts': sorted(artifacts)}, indent=2), encoding='utf-8')
    metrics = {
        'kg_unchanged': read(BEFORE / 'snapshot.json')['tables'] == read(AFTER / 'final_snapshot.json')['tables'],
        'baseline': read(BEFORE / 'benchmark.json'),
        'final_benchmark': read(FINAL / 'benchmark.json'),
        'current': {name: read(AFTER / filename)['summary'] for name, filename in [('factual', 'factual_v4.json'), ('attribution', 'attribution_v4_final.json'), ('presentation', 'presentation_v4.json'), ('conversation', 'conversation_final.json'), ('system', 'system_complete.json')]},
        'current_latency': {'direct_factual': latency(read(AFTER / 'factual_v4.json')['cases']), 'presentation_http_json': latency(read(AFTER / 'answers_v4.json')['rows'])},
        'php_units': read(AFTER / 'php_units_final.json'),
        'php_syntax': read(AFTER / 'php_syntax.json'),
        'new_semantic_assertions': 18,
        'frontend': {'passed': 41, 'failed': 0},
        'browser_chromium': {'passed': 31, 'failed': 9, 'failure_class': 'pre-existing mobile horizontal overflow'},
        'routing_v4': {'passed': 82, 'failed': 0},
        'readiness': 'Not ready for final paper evaluation: complete regression suite is not green.'}
    (OUT / 'metrics.json').write_text(json.dumps(metrics, indent=2), encoding='utf-8')
    inventory = '\n'.join(f"- `{r['path']}` — {r['change']}." for r in changes)
    text = '''# System-wide chatbot and Explorer audit — 2026-09-24, v1

Implemented the eight requested areas with shared retrieval, edge presentation and conversation contracts. This is a KG-fidelity report, not publication verification or scientific/clinical validation. **Not ready for final paper evaluation:** nine baseline mobile overflow tests still fail, and the standalone TypeScript check still fails.

## Baseline and independent evidence

Before production edits, captured source hashes/archive, all 38 raw graph tables, the existing benchmark, the five reported examples, an endpoint property audit, lint/build/frontend results and browser results in `api/tests/fixtures/system_consistency_baseline_v1/`. After implementation, the complete raw graph table contents compare equal to that snapshot. No KG facts, edge directions, edge IDs, publication identities or reference catalog entries were rewritten.

The independent Python oracle joins raw records and reads the previously reviewed namespace audit. It does not import the production planner, presentation mapper or router, and never uses an LLM as ground truth. Tests cover all 52 historical registered endpoints through 168 direct factual cases, the new methods endpoint, constrained queries, graph APIs and the Integrated table. An exhaustive mapper check covers all **10,123 stored edges**, including all **1,155 treatment-phase edges**. Endpoint execution covers selected seeds, not every possible entity/question combination.

## Root causes, classification and resolution

| Finding | Classification | Resolution |
|---|---|---|
| Phase terminology was inconsistent, and historical flat presentation could consult a `phase` property. | Confirmed bug | A shared edge mapper derives pre-treated/treated/post-treated only from the stored relationship type. Citation and Explorer properties use that value. Removed property-based phase selection. All three supported phase types and misleading synthetic properties are tested. |
| Disease/Formulation flat projection retained identifiers and phase but omitted Dose, Adjuvant and Duration. | Confirmed bug | Preserve meaningful formulation-edge properties and attach every supporting edge's complete properties separately. Inverse traversal uses the same contract. |
| Ten endpoint families had omitted flat projections; graph and detail panels used property whitelists. | Confirmed bug | Dynamic `display_properties` and per-edge raw `properties` flow through retrieval, citations, graph DTOs and panels. Preserve zero/false/future fields; hide empty/internal/reference-only fields from property lists. Identifiers remain separately displayed. |
| Multiple recognized entities caused blanket clarification; no planner could bind disease plus therapy as constraints. | Confirmed limitation addressed | Compose typed schema paths, validate constraints against actual KG nodes, and use the requested terminal entity type. Three Vipadika/Sarvanga Abhyanga paraphrases now return Nalpamaradi Keram. |
| Method questions were routed to a method→product traversal, and presentation selected the final product. | Confirmed bug | A dedicated compound-method endpoint returns the requested method nodes directly from UNDERGOES. Lead now returns Dhalana and Nirvapa; copper returns Swedana and Nirvapa. Product and reverse-method questions retain their separate endpoints. |
| Shodhana-method treatment intent could execute a Therapy endpoint with the same name. | Additional confirmed bug | Route through the typed planner as Shodhana_Method, including questions without an explicit “which disease” noun phrase. |
| Integrated traversal skipped Process nodes and required one citation across purification and treatment. | Confirmed bug / overly restrictive contract | Traverse actual production paths, including Process→Process, within the five-hop bound. Keep different studies in explicit groups only at a verified purified-product/production/treatment boundary. Never merge identifiers or claim the studies are one experiment. Copper now reaches Eczema. |
| Graph nodes used local numeric IDs; Integrated Graph also stripped canonical record IDs. | Additional confirmed bug | Preserve full RIDs in shared node/edge mapping, seed handling, navigation and Integrated DTOs. Prevent identity collisions between node types. |
| Graph expansion skipped edges when both endpoints were already loaded; capped graphs could construct dangling edges. | Additional confirmed bug | Preserve already-loaded-node edges and require both nodes before adding an edge. |
| Explorer edge DTOs and the Integrated mapper dropped raw properties and metadata. Integrated enums omitted Indication/AyurvedicFormulation/TREATS. | Confirmed bug | Preserve full directed edge metadata; add the actual node/edge types and USES_FORMULATION traversal name. Exact Explorer paths are compared with independently joined raw records in both supported directions. |
| Integrated table supplied labels in `compoundIds`, numeric product IDs, and evidence-row counts as disease counts. | Additional confirmed bugs | Return canonical IDs, separate labels, unique target counts, all direct edges/properties, and namespace-aware distinct-reference counts. Remove silent edge-query caps and fail explicitly on query failure. |
| Text-only history reconstruction had no validated active subject or pending clarification state; frontend retained six messages and omitted clarification responses. | Confirmed limitation addressed | Versioned conversational context, a 20-message window, active subject and typed constraints, pending clarification, explicit topic switches, session persistence and reset. Re-resolve context against the KG; never store/reuse factual answers as retrieval evidence. |
| Old provenance tests used a nonexistent numeric seed and assumed every reference was PMID. Some PHP tests used obsolete namespace fixtures or did not fail their exit status. | Confirmed test defects | Source-backed canonical seeds, typed reference assertions, real browser/API checks without positive-case skips; correct namespace test data and exit codes. An old test no longer attempts to start a mock service. |
| Multiple alternatives or unresolved same-name node types. | Requires clarification | Preserve clarification instead of arbitrarily selecting or comparing entities. |
| Missing matching path or missing same-study evidence inside a purification/provenance group. | Unsupported by the requested KG path/evidence contract | Return no data; do not invent a join. Shodhita Tamra→Disease and Psoriasis→raw compound have no accepted path in the independently tested bounded contract. This does not assert that the entities have no possible scientific association. |
| Arbitrary unbounded graph questions and unrestricted history. | Intentional limitation | Typed supported relationship vocabulary; at most five hops, three entity constraints, no node cycles, Process type may occur twice. Stop explicitly above 50,000 intermediate paths. Memory is context, not a source of facts. |

The ten flat-projection endpoint families were assessment_disease, classification_process, compound_validation, confirmation_study_purified_compound, disease_assessment, disease_formulation, formulation_disease, formulation_therapy, process_classification and purified_compound_validation. Their raw evidence paths already retained these properties in the baseline; the fix makes the correct per-edge properties reliably available to the presentation layer. It does not claim those facts were absent from the KG.

## What each requested issue now does

1. Treatment phase comes from PRE_TREATED_BY/TREATED_BY/POST_TREATED_BY. The schema has these three supported forms; no new KG edge types were invented.
2. Disease→Formulation exposes the actual formulation-edge fields, preserving the treatment edge separately.
3. Every sampled answer's citation scopes match supporting edge IDs, direction and meaningful properties. Every stored edge passes the global mapping test.
4. Multiple named entities become validated path constraints when their types can form a supported path; equivalent wording is tested.
5. Requested method/product/compound types remain distinct. Existing raw→purified (DERIEVED_FROM), purified→disease, method→compound and other purification endpoint factual tests remain in the 168-case suite.
6. Disease and compound Graph Explorer paths preserve canonical node identity, stored direction, exact supporting references and all edge properties. Reverse canonical provenance endpoints are tested independently.
7. Integrated Graph's default evidence-path mode follows production paths through Process nodes. Each path retains edge-specific sources; users can inspect complete evidence pages even if graph display caps hide some paths. Bridge/one-hop modes remain available.
8. Five new multi-turn scenarios run over both JSON and SSE: active-subject follow-ups, explicit topic change, entity-only clarification, retained path constraints, and recovery after an unsupported request. A frontend test verifies forwarding server context and clearing it for a new conversation.

## Regression results and before/after metrics

| Dimension | Before | After |
|---|---:|---:|
| Existing routing/status cases | 82/82 | 82/82 on versioned v4 contract |
| Independent direct factual cases | 168/168 | 168/168 on versioned v4 contract |
| Exact attribution cases | 121/121 | 121/121, including canonical Integrated replacements |
| Independent result/presentation cases | 24/24 | 24/24 on versioned v4 contract |
| Existing JSON/SSE conversation turns | 108/108 | 108/108; no parity failures |
| New system consistency checks | Not available | 239/239 |
| New semantic/tampering/context assertions | Not available | 18/18 |
| Existing PHP unit script group | Not all separately baselined | 16/16 scripts pass; traversal script includes 195 assertions |
| PHP syntax | — | 100 production PHP files pass |
| Frontend Vitest | 34/34 | 41/41 |
| Chromium targeted browser suite | 24 passed, 13 failed, 2 skipped | 30 passed, 9 failed, no skips |
| Lint | 0 errors, 19 warnings | 0 errors, 19 warnings |
| Production build | Pass | Pass; bundle/Browserslist warnings |
| Standalone TypeScript | Not separately baselined in this task | Fails in existing GraphCanvas/example/test/DTO/SearchResults areas; Vite build is not a type-check |
| Reported wrong-method / constrained / copper-disease examples | 0/4 correct | 4/4 correct (two method examples, constrained formulation, copper disease) |

The original v3 fixtures are preserved. Against those unchanged expectations, the initial post-change run was 81/82 routing, 163/168 factual, 115/121 attribution and 23/24 presentation. Those differences concern the deliberately corrected method result type and the two Integrated endpoint contracts. New v4 expectations were generated from independent raw graph joins, not copied from actual responses. Historical successes and failures remain available; the new suite does not silently re-label old failures as passes.

Existing conversation evaluation retains zero false refusals among its answerable cases and zero false acceptances among its expected abstentions. The new memory scenarios make fresh retrievals on every answerable turn. No Llama output constructs, enumerates or supplies the factual results.

On the same local 24-case presentation suite, HTTP JSON response time changed from p50 **312 ms**, p95 **656 ms** to p50 **218 ms**, p95 **438 ms**. The 168 direct endpoint cases changed from p50 **13 ms**, p95 **166 ms** to p50 **8 ms**, p95 **116 ms**. These are nearest-rank, sequential local measurements, include changed contracts, have no controlled warmup/load design, and do not establish a general speedup. Raw measurements and hashes are in `metrics.json` and the saved capture files.

## New tests and artifacts

- `capture_system_properties.php`: all 10,123 edges, selected endpoint answers, and synthetic future properties/empty values/misleading phase fields.
- `evaluate_system_consistency.py`: independent bounded joins, completeness and pagination, seven natural-language questions over both transports, parity, five conversation scenarios, graph identity/properties, forward/reverse provenance and Integrated table contracts.
- `test_semantic_contracts.php`: legitimate and forged study groups, reversed traversal, forbidden internal study switches, raw versus purified boundary, unclassified references, result types, forged/stale context and method-versus-therapy routing.
- `EdgeProperties.test.tsx`: all three phases and meaningful zero/false/nested values (4 tests).
- `IntegratedPaths.test.tsx`: exact path direction, citation namespace, edge property and next-page request (1 test).
- `PaginationMessage.test.tsx`: complete server-result page access separate from local expansion (1 test).
- `ConversationContext.test.tsx`: frontend context forwarding and new-conversation reset (1 test).
- Reworked `tests/provenance.spec.ts`: eight live provenance/API/browser checks using independently captured record IDs; no positive-case skips.
- `build_system_ground_truth.py`: reproducible, write-once v4 fixtures; refuses to overwrite different existing contents.
- `write_system_audit_report.py`: source change inventory, artifact inventory and assembled metrics/report.

New frozen files: `expanded_factual_cases_v4.json`, `chatbot_evaluation_cases_v4.json`, and `presentation_cases_v4.json`. All captures/logs are under `api/tests/fixtures/system_consistency_after_v1/`. Final authoritative files include `factual_v4.json`, `attribution_v4_final.json`, `presentation_v4.json`, `conversation_final.json`, `system_final.json`, `routing_complete.log`, `php_units_final.json`, `semantic_contracts_final.log`, `frontend_complete.log`, `browser_final.log`, `lint_complete.log`, `build_complete.log`, and `typescript_final.log`. Earlier iterations are retained for traceability.

## Remaining limits and release assessment

- The nine mobile overflow failures exist on the baseline and remain unresolved. This task did not redesign mobile layouts. Standalone TypeScript is also not green. Therefore the complete regression suite has **not** passed and final paper-evaluation readiness is **not** claimed.
- The browser run covers Chromium with the specified route/mobile/reference/provenance selection, not every browser or every historical exploratory test script.
- The planner supports bounded composition of its explicitly allowed schema relationships, not arbitrary Cypher/SQL or arbitrary prose. Raw/purified DERIEVED_FROM-only links remain available through their dedicated endpoints and the bridge table; Integrated evidence-path mode follows the production path contract.
- Disease, Indication, Formulation and Ayurvedic_Formulation remain distinct KG types. A missing Disease path is not replaced with an Indication under the wrong label.
- Same-study joins and explicit cross-study groups establish graph/reference attribution only. They do not establish clinical efficacy, publication claim support or a single experimental chain; domain review remains necessary.
- Pagination recomputes the bounded query and is not a database transaction snapshot. The 50,000-intermediate-path guard requires narrowing; it never silently labels a truncated answer complete. Legacy standalone graph/provenance views still have their existing declared display/page limits.
- Structured context is session-local and bounded. Ambiguous alternatives still require clarification, and pronouns keep the active subject rather than automatically selecting an entity mentioned in an answer.
- A user-managed dev-server restart is required by AGENTS.md after these changes/builds. The agent did not manage the npm dev server.

## Every changed source file

SOURCE_INVENTORY

`file_inventory.json` additionally enumerates every generated baseline/current report artifact and current build/browser-output file. Generated build outputs are not hand-edited production source.
'''.replace('SOURCE_INVENTORY', inventory)
    text = text.replace('30 passed, 9 failed', '31 passed, 9 failed').replace('eight live provenance/API/browser checks', 'nine live provenance/API/browser checks').replace('`browser_final.log`', '`browser_complete.log`').replace('239/239', '242/242').replace('`system_final.json`', '`system_complete.json`')
    text = text.replace('| Multiple alternatives or unresolved same-name node types.', '| `node.php` called an undefined `fetch_node_context` helper. | Additional confirmed API defect | Implement direct context through the shared canonical node/edge mapping, preserve all returned edge properties and references, and report neighbor truncation explicitly. Three independently checked node-detail cases cover this path. |\n| Multiple alternatives or unresolved same-name node types.')
    text += '\n## Fresh final benchmark and reproduction\n\nThe final frozen-source benchmark is in `api/tests/fixtures/system_consistency_final_benchmark_v1/benchmark.json`. Its source-drift, graph-drift, namespace and transport-parity checks are explicit. The separate system audit and frontend/browser checks above extend that benchmark. Test suites overlap; their pass counts must not be added as independent claims.\n\n```powershell\npython -X utf8 api/tests/run_current_benchmark.py --contract-version 4 --php php --output-dir NEW_REPORT_DIRECTORY\nphp api/tests/test_semantic_contracts.php\nnpm.cmd run lint\nnpm.cmd run build\nnpm.cmd test\nnpx.cmd playwright test --config playwright.external.config.ts --project chromium --grep "Reference classification|Route smoke|Mobile|provenance"\n```\n\nAll services must already be running. These commands do not manage the npm dev server. Choose a new report directory to preserve prior captures.\n'
    final_latency = metrics['final_benchmark']['latency']
    text += '\nFresh final benchmark latency: presentation HTTP JSON p50 **' + str(final_latency['results_first_http_json']['p50_ms']) + ' ms**, p95 **' + str(final_latency['results_first_http_json']['p95_ms']) + ' ms**; direct endpoints p50 **' + str(final_latency['direct_endpoint_not_http']['p50_ms']) + ' ms**, p95 **' + str(final_latency['direct_endpoint_not_http']['p95_ms']) + ' ms**. The earlier post-change timing table above is retained as a separate measurement, not combined with this run.\n'
    text = text.replace('system_consistency_final_benchmark_v1/', 'system_consistency_final_benchmark_v2/')
    text = text.replace('Confirmed limitation addressed', 'Intentional limitation (expanded)').replace('Additional confirmed API defect', 'Confirmed bug').replace('Additional confirmed bug', 'Confirmed bug').replace('Additional confirmed bugs', 'Confirmed bug').replace('Confirmed test defects', 'Confirmed bug')
    (OUT / 'REPORT.md').write_text(text, encoding='utf-8')
    print(f'Report written; {len(changes)} changed/added source files, {len(artifacts)} generated artifacts')

if __name__ == '__main__': main()
