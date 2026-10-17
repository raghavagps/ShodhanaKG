"""Export this audit's observed results; does not change application or test sources."""
import csv, hashlib, json, re, zipfile
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
P=Path(__file__).resolve().parent
ROOT=P.parents[3]
def read(name):
    raw=(P/name).read_bytes()
    return json.loads(raw.decode('utf-16' if raw[:2] in (b'\xff\xfe',b'\xfe\xff') else 'utf-8-sig'))
def save(name,obj): (P/name).write_text(json.dumps(obj,indent=2,ensure_ascii=False),encoding='utf-8')
def text(name): return (P/name).read_text(encoding='utf-8',errors='replace')
def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()
b=read('release/benchmark/benchmark.json'); release=read('release/release.json')
supp=read('supplemental_php.json'); syntax=read('php_syntax.json'); npm=read('dependency_audit.json')
assert supp['complete']
browser=read('browser_retry.json'); browser_exit=read('browser_retry_exit.json')
baseline=read('protected_before.json'); changed=[]; missing=[]
for name,digest in baseline['sha256'].items():
    f=ROOT/name
    if not f.exists(): missing.append(name)
    elif sha(f)!=digest: changed.append(name)
integrity={'checked':len(baseline['sha256']),'changed':changed,'missing':missing,'unchanged':not changed and not missing,'exclusions':baseline['exclusions']}
save('protected_integrity.json',integrity)
snapshot=read('release/benchmark/snapshot.json'); after=read('post_audit_snapshot.json')
def byid(s): return {r['@rid']:r for rows in s['tables'].values() for r in rows}
before_records=byid(snapshot); after_records=byid(after)
drift={'before_records':len(before_records),'after_records':len(after_records),'added':sorted(after_records.keys()-before_records.keys()),'removed':sorted(before_records.keys()-after_records.keys()),'changed':sorted(k for k in before_records.keys()&after_records.keys() if before_records[k]!=after_records[k])}
save('graph_integrity.json',drift)
rows=[]
def rate(label,passed,total,unit,source,note='',kind='fixed_benchmark'):
    rows.append({'metric':label,'numerator':passed,'denominator':total,'percent':round(100*passed/total,2) if total else None,'unit':unit,'scope':kind,'source':source,'notes':note})
rate('Factual retrieval exact-match cases',b['factual']['passed'],b['factual']['total'],'cases','release/benchmark/factual.json','Agreement with frozen independent KG reference; not clinical accuracy.')
f=read('release/benchmark/factual.json'); endpoints=len({r['endpoint'] for r in f['cases']})
for status in ['success','no_data']:
    subset=[r for r in f['cases'] if r['expected_status']==status]
    rate('Factual '+status+' controls',sum(r['passed'] for r in subset),len(subset),'cases','release/benchmark/factual.json','Subset of the 168 factual cases; not additional samples.')
rate('Reference-tested retrieval endpoints',endpoints,52,'endpoints','release/benchmark/factual.json','Endpoint coverage is not exhaustive entity/path coverage.')
rate('Legacy evidence attribution',b['attribution']['passed'],b['attribution']['cases'],'cases','release/benchmark/attribution.json','40 legacy endpoints; exact stored edge identity/direction/properties.')
rate('Results-first presentation contracts',b['presentation']['passed'],b['presentation']['cases'],'answers','release/benchmark/presentation.json')
presentation=read('release/benchmark/presentation.json')['results']
answer_rows=read('release/benchmark/answers.json')['rows']
rate('Deterministic results-first generation',sum(r['response'].get('generation',{}).get('mode')=='deterministic' for r in answer_rows),len(answer_rows),'answers','release/benchmark/answers.json','Descriptive generation-mode count; not a quality score.')
rate('Result entity occurrences retained',sum(r['actual_result_count'] for r in presentation),sum(r['expected_unique_entities'] for r in presentation),'within-answer grouped entity occurrences','release/benchmark/presentation.json','375 includes repeats across questions; matching identities checked by presentation contracts.')
rate('Citation occurrences retained',sum(r['actual_citations'] for r in presentation),sum(r['expected_evidence_records'] for r in presentation),'citation occurrences','release/benchmark/presentation.json','Repeated evidence across answers is counted again; not unique publications.')
rate('Routing/status contracts',b['routing_status']['passed'],b['routing_status']['total'],'cases','release/benchmark/routing.log','Joint routing/status checks, not a standalone classifier accuracy estimate.')
rate('Conversation turn executions',b['conversation']['passed'],b['conversation']['turn_runs'],'turn executions','release/benchmark/conversation.json','34 scenarios, 54 turns repeated through JSON and SSE.')
rate('JSON/SSE semantic parity',54-len(b['transport_parity_failures']),54,'paired turns','release/benchmark/conversation.json','Model-attempt diagnostics reported separately; no differences observed.')
for t,r in b['refusals'].items():
    rate(t.upper()+' false refusals',r['false_refusals'],r['answerable_turns'],'answerable turns','release/benchmark/benchmark.json','Lower is better; two of 54 turns are outside these answerable/abstention denominators.')
    rate(t.upper()+' correct abstentions',r['correct_abstentions'],r['expected_abstention_turns'],'expected abstention turns','release/benchmark/benchmark.json')
    rate(t.upper()+' false acceptances',r['false_acceptances'],r['expected_abstention_turns'],'expected abstention turns','release/benchmark/benchmark.json','Lower is better.')
for name,r in b['supplemental'].items(): rate(name.replace('_',' ').capitalize(),r['passed'],r['total'],'checks','release/benchmark/'+name+'.json')
syschecks=read('release/benchmark/system_consistency.json')['checks']; prop=next(c for c in syschecks if c['id']=='all_stored_edge_properties')
rate('Stored edge property contract scan',prop['edges'] if prop['passed'] else 0,prop['edges'],'stored edges','release/benchmark/system_consistency.json','Normalization/property metadata scan; not every edge rendered in every browser answer.')
rate('Treatment phase scan',prop['phase_edges'] if prop['passed'] else 0,prop['phase_edges'],'phase edges','release/benchmark/system_consistency.json')
rate('Frontend unit tests',95,95,'tests','release/frontend_unit.log','25 files.','software')
rate('Python evaluation/HTTP tests',73,73,'tests','release/evaluation_unit.log','unittest discovery.','software')
rate('Maintained PHP regressions',14,14,'scripts','release/release.json','Scripts contain unequal numbers of assertions.','software')
rate('PHP syntax',syntax['passed'],syntax['total'],'files','php_syntax.json','One malformed diagnostic script.','software')
stats=browser['stats']; rate('Browser retry expected outcomes',stats['expected'],sum(stats[k] for k in ['expected','unexpected','flaky','skipped']),'browser test executions','browser_retry.json','Single worker; original two-worker run aborted. Skips/flakes reported separately.','software')
browser_cases=[]
def walk_suites(suites):
    for suite in suites:
        for spec in suite.get('specs',[]):
            for test in spec.get('tests',[]):
                browser_cases.append({'file':spec.get('file'),'title':spec.get('title'),'project':test.get('projectName'),'status':test.get('status'),'results':[{'status':r.get('status'),'duration_ms':r.get('duration'),'errors':r.get('errors',[])} for r in test.get('results',[])]})
        walk_suites(suite.get('suites',[]))
walk_suites(browser['suites'])
save('browser_case_summary.json',browser_cases)
sweep=read('compound_sweep.json')['cases']; statuses=Counter(r['therapeutic_use_status'] for r in sweep)
wrong=sum(r['routing'].get('routing',{}).get('endpoint')=='compound_purified_forms' for r in sweep)
rate('Therapeutic-use endpoint errors (post-hoc)',statuses['error'],len(sweep),'compound seeds','compound_sweep.json','Lower is better. Non-errors are not independently scored as factually correct.','post_hoc')
rate('Wrong purified-form route (post-hoc)',wrong,len(sweep),'compound-seeded questions','compound_sweep.json','Template requested diseases; remaining two require clarification. Not a random question sample.','post_hoc')
bad=[]; clean=[]
for r in supp['results']:
    name=Path(r['file']).name
    (bad if r['exit_code'] or r['failure_lines'] or name=='test_real_query_matrix.php' else clean).append(r)
rate('Supplemental scripts without unexpected failures',len(clean),len(supp['results']),'scripts','supplemental_php.json','One of the 14 includes five explicitly expected legacy-validator limitations. Three other scripts print failures but exit zero.','software')
metrics={'audit_id':P.name,'generated_at':datetime.now(timezone.utc).isoformat(),'release_gate':'FAIL','deployment_ready':False,'maintained_runner_passed':release['passed'],'metrics':rows,'latency':b['latency'],'conversation_categories':b['conversation']['categories'],'browser_retry':stats,'browser_retry_exit':browser_exit,'supplemental_script_counts':{'total':len(supp['results']),'without_unexpected_failures':len(clean),'with_unexpected_failures':len(bad),'nonzero_exit':sum(r['exit_code']!=0 for r in supp['results']),'excluded_server_managing':4},'dependency_advisories':npm['metadata']['vulnerabilities'],'source_integrity':integrity,'graph_integrity':drift,'scientific_accuracy':b['scientific_accuracy'],'notes':['Do not sum overlapping suites into a single accuracy estimate.','Fixed benchmarks and post-hoc diagnostic probes have different sampling designs.','No application source, test sources, configuration, dependencies, ground truth or KG facts were intentionally modified.']}
metrics['system_consistency_check_groups']=dict(Counter(c['id'].split('_')[0] for c in syschecks))
metrics['known_namespace_errors']=b['known_namespace_errors']
metrics['refusals']=b['refusals']
metrics['not_measured']=['Clinical/scientific accuracy','Publication-support accuracy','Expert inter-rater agreement','Routing-only latency','Concurrent throughput','Time to first token','Held-out population accuracy']
metrics['kg_snapshot']={'vertices':sum(r.get('@cat')=='v' for r in before_records.values()),'edges':sum(r.get('@cat')=='e' for r in before_records.values()),'counts_by_type':{k:len(v) for k,v in snapshot['tables'].items()}}
metrics['browser_projects']={project:dict(Counter(r['status'] for r in browser_cases if r['project']==project)) for project in sorted({r['project'] for r in browser_cases})}
metrics['browser_clean_exit_helper_messages']=text('browser_retry.log').count('[test teardown] WebKit process')
save('metrics.json',metrics)
with (P/'metrics.csv').open('w',newline='',encoding='utf-8-sig') as fp:
    w=csv.DictWriter(fp,fieldnames=list(rows[0]));w.writeheader();w.writerows(rows)
table='| Metric | Observed / denominator | Percentage | Scope |\n|---|---:|---:|---|\n'+'\n'.join(f"| {r['metric']} | {r['numerator']:,}/{r['denominator']:,} | {r['percent']:.2f}% | {r['scope']} |" for r in rows)
latency='| Request class | n | Mean (ms) | Median (ms) | p95 (ms) | Maximum (ms) |\n|---|---:|---:|---:|---:|---:|\n'+'\n'.join(f"| {k.replace('_',' ')} | {v['n']} | {v['mean_ms']} | {v['p50_ms']} | {v['p95_ms']} | {v['max_ms']} |" for k,v in b['latency'].items() if isinstance(v,dict))
(P/'metrics_table.md').write_text('# Quantitative evaluation\n\n'+table+'\n\nAll rates apply only to the named suite and denominator. Suites overlap. Post-hoc error rates are lower-is-better.\n\n'+latency+'\n\n'+b['latency']['method']+'\n',encoding='utf-8')
shell_names={'test_compound_detoxification.php','test_compound_media.php','test_compound_purification.php','test_compound_therapeutic_use.php','test_compound_toxicity.php','test_compound_validation.php','test_disease_symptoms.php','test_disease_therapy.php','test_integrated_compound_disease.php','test_retrieval_core_integration.php'}
triage=[]
for r in bad:
    name=Path(r['file']).name
    if name in shell_names: reason='Obsolete subprocess harness: requires endpoint through php -r, but endpoint SCRIPT_FILENAME guard prevents JSON emission. Null response triggers failures/count TypeError. Does not itself establish endpoint factual failure; therapeutic-use also has a separately reproduced application defect.'
    elif name=='test_disease_formulation.php': reason='Outdated namespace expectation: 36 expected and 36 actual unique tuples; 19 differ only because reference 238408 is classified AYUSH rather than the old test\'s PMID bucket. No missing tuple established by this failure.'
    elif name=='test_compound_shodhana_process.php': reason='Mixed: expected old method endpoint; multi-output method-and-media intent is reduced to methods, and procedure wording is captured by node-property handling. Procedure interpretation requires clarification. Prints three FAIL lines but exits zero.'
    elif name=='test_real_query_matrix.php': reason='Historical matrix reports 19 actual failures/36 cases but exits zero. Mix of intentional typed ambiguity, changed negative-status semantics, and routing limitations; not a valid all-pass gate.'
    elif name=='test_chatbot_orchestrator.php': reason='Mixed: confirmed Croton therapeutic-use evidence error plus changed typed-ambiguity, node-property and negative-status contracts. Raw log has 18 failure markers; not 18 independently confirmed application bugs.'
    elif name=='test_schema_query_coverage.php': reason='Nine failed cases: mostly cross-type ambiguity; also confirmed purified-product keyword precedence returning the wrong result type for a disease question.'
    elif name=='test_query_robustness.php': reason='Historical matrix: 64 cases; 22 positive passes, five expected negatives, 37 failures under old expectations. Many are now intentional cross-type clarifications; reported categories are legacy test labels, not an independent root-cause diagnosis.'
    elif name=='test_therapy_disease.php': reason='Seven printed failures with exit zero: typed ambiguity (Alopecia/Panchakarma) and a context-free pronoun expected to route without clarification. Historical expectations.'
    else: reason='Historical expected statuses/endpoints conflict with current cross-type ambiguity, node-property answering or explicit purified-form routes. Raw failures retained; do not reinterpret the old script as passed.'
    triage.append({'file':r['file'],'exit_code':r['exit_code'],'reason':reason,'raw_log':r['log']})
save('failure_triage.json',triage)
failuretext='''# Release audit failures and limitations

## Confirmed application defects

1. **Therapeutic-use deduplication detaches method identity from evidence.** `api/compound_therapeutic_use.php:94` deduplicates by purified compound, indication and properties, omitting Shodhana method. Line 106 records the retained row against subsequent method paths. `api/core/LegacyEvidence.php:141` correctly rejects mismatched result labels. For Croton, Bhavana and Swedana paths share the same purified compound/indication/reference (e.g. `#73:0 → #78:0 → #82:0` and `#73:3 → #78:12 → #82:0`). Read-only sweep: **15/52 compounds error**, 14 succeed, 23 return no_data. Only the errors were diagnosed; no_data does not prove absence of every possible graph path. Both HTTP transports reproduce the Croton error. See `compound_sweep.json`, `diagnostic_reconciliation.json`, `diagnostic_http.json`.
2. **Purified-product keywords outrank the requested disease result.** `api/core/QuestionRouter.php:272` returns compound_purified_forms before checking disease/integrated intent at line 274. “What diseases are treated by the purified compound from Croton tiglium?” returns purified compounds. The diagnostic template routes **50/52** compound questions this way; two are ambiguous. Both JSON/SSE reproduce the wrong-target answer. No KG path is invented: absent disease results should still remain disease-targeted no_data, not purified-form answers.
3. **Compound requests for multiple output types remain limited.** “What method and media are used to purify Copper?” chooses compound_methods before the full process branch, omitting the media request. This is a confirmed intent-completeness gap, not a missing-KG conclusion. “Give me the Shodhana procedure for Copper” is treated as a node property and returns no_data; whether the user means a stored node description or a relationship procedure needs clarification. Neither was fixed.

## Test/infrastructure defects

- `scripts/debug/check_types.php:2` has a PHP parse error (406/407 PHP files pass). This diagnostic file is outside the application API.
- Initial 489-case browser suite aborted at the twentieth scheduled execution (not a completed denominator), exit 134, after launch failures and a Node “Committing semi space failed” allocation error. Windows memory measured afterward: about 1.1 GiB available virtual memory. A one-worker retry uses identical tests/config and the existing Windows clean-exit pipe helper. This does not erase the original failure. A first audit-wrapper retry also failed before test discovery due to preload path escaping; its log is retained, and only the audit wrapper was corrected.
- Ten supplemental subprocess harnesses include endpoints via php -r without invoking their current SCRIPT_FILENAME entrypoint guard, producing no JSON. Three supplemental scripts report failed assertions but return exit zero. Neither exit-zero nor old expected statuses were treated as proof of correctness.
- Disease/formulation test assumes every raw ref is PMID; 19 of 36 Alopecia tuples differ only by corrected AYUSH namespace 238408. See `diagnostic_reconciliation.json`.
- The curated factual suite samples 168 cases over 52 endpoints, including only two positive therapeutic-use compounds (Abrus variants) and one no-data control. It does not cover all entities or the failing method-collision case. The all-edge property scan is not an all-entity endpoint sweep.

## Warnings and unverified scope

- Dependency audit: seven flagged package entries (three high, three moderate, one low; zero critical). This is not seven demonstrated exploits. `postcss` and its nanoid chain and brace-expansion include tool/peer dependency paths; `react-router-dom` is used by the frontend. Advisory reachability/applicability has not been conclusively established. No dependency changed. Raw registry evidence and dependency paths are exported.
- ESLint: 19 warnings, zero errors. Build warns about a >500 kB chunk and outdated Browserslist data. No warnings were suppressed.
- Standalone legacy validator regression explicitly accepts five known limitations (unlisted entities, unrelated in-context associations, mixed provenance, cross-connected paths, qualitative claims). The deterministic/current evidence pipeline has additional safeguards; these standalone tests do not prove an end-to-end exploit. No expert scientific-claim validation was performed.
- Four legacy tests start/manage PHP servers (some also overwrite mock fixtures) and were excluded: test_audit_gap_closure.php, test_end_to_end_chatbot.php, test_chatbot_inverse.php, test_security_http.php. Current maintained HTTP/transport tests were run, but this is not a complete penetration test.
- Tested local running services only; no deployed TLS/authentication/load/availability certification. Local PHP health returned wildcard CORS. Actual deployment origin controls remain unverified.
- Browser coverage mixes live integration tests with fixture/interception-based rendering and failure-state tests; 489 browser executions are not 489 independent live scientific queries. Historical documentation is explicitly marked as historical in docs/chatbot/README.md; this fresh audit supersedes earlier release-pass claims for readiness.
- Intentional coverage limits remain: Integrated Explorer excludes Indication and Ayurvedic_Formulation by the user's earlier decision (the raw snapshot contains 637 TREATS and 84 USED_IN_FORMULATION edges involving these categories). Explorer node/edge/depth caps are not complete-KG visibility. Natural-language routing is English-oriented and semantic planning is bounded to supported paths (up to five hops); finite terminology and cross-type clarification remain part of the contract. No unsupported path was added for this audit.
- Publication registries/source documents were not freshly revalidated in this run. Identifier classification and edge attribution are not evidence that publications support clinical claims. Zero expert-approved claims in the benchmark.

## Supplemental failures by file

'''
failuretext+='\n'.join(f"- **{x['file']}** (exit {x['exit_code']}): {x['reason']} Raw: [{x['raw_log']}]({x['raw_log']})." for x in triage)
failuretext+='\n\n## Browser retry result\n\n'+json.dumps(stats,indent=2)+'\n\nSee browser_retry.json for each project, test, outcome, and error; browser_retry.log for progress and clean-exit helper messages.\n'
(P/'failures.md').write_text(failuretext,encoding='utf-8')
browserline=f"{stats['expected']}/{sum(stats[k] for k in ['expected','unexpected','flaky','skipped'])} expected outcomes; {stats['unexpected']} unexpected, {stats['flaky']} flaky, {stats['skipped']} skipped; exit {browser_exit['exit_code']}"
summary=f'''# Final release audit — FAIL

**Deployment readiness: not approved.** The fixed backend benchmark passes, but the wider audit reproduces application defects beyond its sampled cases. Source code and KG data were not repaired during this audit.

- Factual retrieval **168/168**, routing/status **82/82**, attribution **121/121**, presentation **24/24**, conversation **108/108**; all passed on their fixed references. These overlapping suites do not imply universal accuracy.
- Frontend **95/95 tests**, Python **73/73 tests**, maintained PHP **14/14 scripts**, lint/build passed. PHP syntax **406/407 files**.
- Browser retry: **{browserline}**. Initial two-worker run aborted with memory/launch errors; both attempts retained.
- Supplemental PHP: **14/35 scripts without unexpected failures**, **21/35 with failures**, including stale harnesses/contracts and genuine application defects. Four server-managing scripts excluded. One of the 14 records five expected legacy-validator limitations.
- Post-hoc: therapeutic-use evidence error for **15/52 compounds**; disease questions wrongly return purified-form routes for **50/52** compound seeds (two ambiguous). Multi-output method/media requests also lose a requested output type.
- Warnings: **19 lint warnings**; large bundle/outdated Browserslist; **7 advisory-flagged packages** (3 high, 3 moderate, 1 low), applicability unresolved.
- Protected baseline: **{integrity['checked']} files**, changed **{len(changed)}**, missing **{len(missing)}**. Raw KG before/after: **{len(before_records)} records**, added {len(drift['added'])}, removed {len(drift['removed'])}, changed {len(drift['changed'])}.

Before release: fix and independently test method-aware deduplication and requested-output precedence; define multi-output/procedure intent behavior; repair stale/broken test harnesses and diagnostic syntax; triage dependency advisories; rerun the full gate on adequate resources. Do not alter KG identities or weaken validation to obtain a pass.

Exports: [metrics table](metrics_table.md), [CSV](metrics.csv), [machine-readable metrics](metrics.json), [failure investigation](failures.md), [manuscript text](manuscript_results.md). Raw per-case outputs are in release/benchmark; all logs and supplemental probes are retained. SHA-256 manifest and ZIP accompany this report.
'''
(P/'summary.md').write_text(summary,encoding='utf-8')
manuscript=f'''# Manuscript evaluation results

## Methods

We evaluated the local ShodhanaKG implementation on 26–27 September 2026 using versioned regression fixtures and read-only live KG retrieval. Exact retrieval outputs were compared with frozen reference cases derived independently from raw KG records, rather than LLM-generated answers. The fixed evaluation comprised 168 factual cases across 52 retrieval endpoints, 121 attribution cases across 40 legacy endpoints, 24 results-first answer cases, 82 routing/status cases, and 34 conversation scenarios containing 54 turns repeated through JSON and SSE (108 turn executions). Additional contract suites assessed structured context, entity resolution, node properties, terminology and method result types; a 242-check consistency suite included all 10,123 stored edges and 1,155 treatment-phase edges. Software checks included 95 frontend unit tests, 73 Python tests, 14 maintained PHP scripts, 35 additional PHP scripts, syntax checks, a build and 489 planned browser executions across Chromium, Firefox and WebKit. Following failures in the broader scripts, a separate post-hoc diagnostic sweep examined all 52 Compound nodes. Response times were measured for sequential local requests, with nearest-rank percentiles and no warm-up exclusion. Suites overlap and were not pooled into a single accuracy estimate.

## Results

The fixed suites passed 168/168 factual cases (100%), 121/121 attribution cases (100%), 24/24 answer-presentation cases (100%) and 82/82 routing/status cases (100%). Presentation checks retained 375/375 within-answer grouped entity occurrences and 1,088/1,088 citation occurrences; these are not distinct database entities or publications. All 108/108 conversation executions passed, with semantic parity in 54/54 paired turns. Each transport had 0/39 false refusals among answerable turns and 13/13 correct abstentions; these subsets do not exhaust all 54 turns. Median/p95 complete-response times were 406/610 ms for 24 results-first JSON answers, 468/766 ms for 54 conversational JSON requests and 453/765 ms for 54 SSE requests. The JSON maximum was 9,859 ms. All 95 frontend tests, 73 Python tests and 14 maintained PHP scripts passed. The full initial browser run aborted with allocation/launch errors; its unchanged one-worker retry recorded {browserline}. Broader validation did not support release approval: 21/35 supplemental PHP scripts reported unexpected failures (including obsolete harnesses/expectations), one of 407 PHP files failed syntax checking, and the post-hoc sweep found therapeutic-use evidence-validation errors for 15/52 compounds (28.85%). The disease-request diagnostic template incorrectly selected purified-form results for 50/52 seeds (96.15%); two required clarification. These diagnostic rates are separate from fixed-fixture pass rates. Seven dependency package entries were flagged by the advisory audit (three high, three moderate, one low); exploitability was not established. The overall release gate was **FAIL**.

## Metrics

{table}

{latency}

Percentages use the stated denominators. False-refusal, endpoint-error and wrong-route rates are lower-is-better. Test/script counts are not interchangeable. Citation counts are occurrences across evaluated answers. Local response times include complete-body receipt, not time to first token or concurrent-load performance. Post-hoc probes used one diagnostic phrasing per compound and were not a held-out natural-language test set.

## Limitations

This evaluation measures software behavior and agreement with stored KG records, not the scientific correctness of those records, publication support for every association, treatment efficacy or clinical safety. The fixed suite is finite and non-random; endpoint coverage did not prevent defects in unsampled entities and paraphrases, as the post-hoc findings demonstrate. All 24 results-first answers used deterministic generation; the study does not estimate general free-form LLM answer quality. Existing deterministic validators share some implementation contracts with production, although factual references were built independently from raw KG records. Browser tests mix live integration with intercepted/fixture-driven UI checks. Browser resource failures and excluded server-managing scripts limit whole-suite conclusions. Local sequential timing is not a deployment performance guarantee. Publication/source-document review remains partial and no expert-approved clinical claims were established by this benchmark.
'''
(P/'manuscript_results.md').write_text(manuscript,encoding='utf-8')
# Cross-check exported arithmetic and denominators before packaging.
assert endpoints==52 and b['conversation']['turn_runs']==108
assert sum(r['expected_unique_entities'] for r in presentation)==375
assert len(sweep)==52 and statuses['error']==15 and wrong==50
assert not changed and not missing and not any(drift[k] for k in ['added','removed','changed'])
assert len(bad)==21 and len(clean)==14
save('export_validation.json',{'passed':True,'checks':['source hashes unchanged','raw KG records unchanged','fixed sample sizes and post-hoc counts reconciled','CSV and Markdown generated from the same metrics rows'],'generated_at':datetime.now(timezone.utc).isoformat()})
files=sorted(f for f in P.rglob('*') if f.is_file() and f.name!='checksums.sha256')
(P/'checksums.sha256').write_text(''.join(sha(f)+'  '+f.relative_to(P).as_posix()+'\n' for f in files),encoding='utf-8')
archive=P.with_suffix('.zip')
with zipfile.ZipFile(archive,'w',compression=zipfile.ZIP_DEFLATED) as z:
    for f in sorted(P.rglob('*')):
        if f.is_file(): z.write(f,P.name+'/'+f.relative_to(P).as_posix())
with zipfile.ZipFile(archive) as z: assert z.testzip() is None
archive.with_suffix('.zip.sha256').write_text(sha(archive)+'  '+archive.name+'\n',encoding='utf-8')
print(json.dumps({'verdict':'FAIL','browser_retry':stats,'protected_files':integrity['checked'],'archive':str(archive),'archive_bytes':archive.stat().st_size},indent=2))
