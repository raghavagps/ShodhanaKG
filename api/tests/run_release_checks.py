"""Complete local release gate; requires existing servers and never manages them."""
import argparse, json, os, shutil, subprocess, sys, time, urllib.request
from pathlib import Path
from run_current_benchmark import REPO, TESTS, sources
PHP_TESTS = [
    'test_audit_boundaries.php', 'test_method_result_contract.php', 'test_entity_resolution.php',
    'test_node_property_answer.php', 'test_structured_context_v2.php', 'test_conversation_routing.php',
    'test_semantic_contracts.php', 'test_terminology_expansion.php', 'reference_classification.test.php',
    'test_response_validator.php', 'test_evidence_correlator.php', 'test_reference_record_urls.php',
    'test_results_presentation.php', 'test_legacy_evidence_presentation.php',
    # Reconciled legacy contracts: no server lifecycle or shared fixture writes.
    'phase15_bugfixes.test.php', 'phase16_hardening.test.php', 'test_chatbot_orchestrator.php',
    'test_compound_detoxification.php', 'test_compound_media.php', 'test_compound_purification.php',
    'test_compound_shodhana_process.php', 'test_compound_therapeutic_use.php', 'test_compound_toxicity.php',
    'test_compound_validation.php', 'test_disease_formulation.php', 'test_disease_symptoms.php',
    'test_integrated_compound_disease.php', 'test_query_robustness.php', 'test_real_query_matrix.php',
    'test_real_schema_queries.php', 'test_retrieval_core_integration.php', 'test_router.php',
    'test_schema_query_coverage.php', 'test_therapy_disease.php', 'test_traversal_expansion.php',
    'test_audit_gap_closure.php', 'test_chatbot_inverse.php', 'test_end_to_end_chatbot.php',
    'test_http_test_helper.php', 'test_query_robustness_exit.php', 'test_security_http.php',
    'test_provenance_routing_precedence.php', 'test_release_remediation.php']

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--php', default='php');p.add_argument('--output-dir', type=Path, required=True)
    p.add_argument('--browser', choices=['chromium','all'],default='all')
    p.add_argument('--browser-workers', type=int, default=1 if os.name == 'nt' else 2)
    a=p.parse_args()
    if a.browser_workers < 1: p.error('--browser-workers must be positive')
    out=a.output_dir.resolve();out.mkdir(parents=True,exist_ok=False)
    for url in ('http://127.0.0.1:8081/api/chatbot_health.php','http://localhost:8080'):
        with urllib.request.urlopen(url,timeout=10) as response:
            if response.status != 200: raise RuntimeError('Required service unavailable: '+url)
    before=sources();npm=shutil.which('npm.cmd' if os.name=='nt' else 'npm');npx=shutil.which('npx.cmd' if os.name=='nt' else 'npx')
    jobs=[('lint',[npm,'run','lint']),('build',[npm,'run','build']),('frontend_unit',[npm,'test']),
          ('evaluation_unit',[sys.executable,'-B','-m','unittest','discover','-s','api/tests','-p','test*.py'])]
    jobs += [('php_'+name,[a.php,str(TESTS/name)]) for name in PHP_TESTS]
    jobs += [('benchmark',[sys.executable,'-B',str(TESTS/'run_current_benchmark.py'),'--php',a.php,'--contract-version','8','--output-dir',str(out/'benchmark')])]
    browser=[npx,'playwright','test','--config','playwright.external.config.ts','--workers='+str(a.browser_workers),'--reporter=line','--output',str(out/'browser-artifacts')]
    if a.browser!='all':browser += ['--project='+a.browser]
    jobs += [('browser',browser)]
    rows=[]
    for name,command in jobs:
        print('START',name,flush=True);start=time.monotonic()
        env = os.environ.copy()
        env['SHODHANA_TEST_PYTHON'] = sys.executable
        env['PYTHONDONTWRITEBYTECODE'] = '1'
        if name == 'browser' and os.name == 'nt':
            preload = REPO / 'tests/support/webkit-pipe-cleanup.cjs'
            env['NODE_OPTIONS'] = (env.get('NODE_OPTIONS', '') + ' --require ' + json.dumps(preload.as_posix())).strip()
            env['SHODHANA_WEBKIT_PIPE_CLEANUP'] = '1'
        with (out/(name+'.log')).open('w',encoding='utf-8') as log:
            result=subprocess.run(command,cwd=REPO,stdout=log,stderr=subprocess.STDOUT,env=env)
        rows.append({'suite':name,'command':command,'exit_code':result.returncode,'seconds':round(time.monotonic()-start,2)})
        changed=before!=sources()
        report={'source_sha256':before,'source_changed':changed,'browser_scope':a.browser,'suites':rows,
                'complete':len(rows)==len(jobs),'passed':len(rows)==len(jobs) and not changed and all(r['exit_code']==0 for r in rows)}
        (out/'release.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
        print('END',name,result.returncode,flush=True)
    return 0 if report['passed'] else 1
if __name__=='__main__':raise SystemExit(main())
