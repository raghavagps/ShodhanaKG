"""Versioned regression logs. Uses user-managed servers; never starts/stops them."""
import json, os, subprocess, sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[3]
OUT=ROOT/'webservice/api/tests/fixtures/ui_result_contract_2026_09_25_v1/checks'
OUT.mkdir(exist_ok=True)
php=os.environ.get('PHP_BIN', 'php')
jobs=[('lint',['npm.cmd','--prefix','webservice/frontend','run','lint']),('build',['npm.cmd','--prefix','webservice/frontend','run','build']),('frontend',['npm.cmd','--prefix','webservice/frontend','test'])]
jobs += [(p,[php,'webservice/api/tests/'+p]) for p in ['test_method_result_contract.php','test_reference_record_urls.php','reference_classification.test.php','test_terminology_expansion.php','test_node_property_answer.php','test_entity_resolution.php','test_results_presentation.php','test_legacy_evidence_presentation.php','test_conversational_answer.php','test_conversation_routing.php','test_context_immutability.php','test_structured_context_v2.php','provenance-subgraph.test.php']]
jobs += [('schema_reference',[sys.executable,'webservice/api/tests/test_schema_reference.py']),('compiler_policy',[sys.executable,'webservice/api/tests/test_build_terminology_catalog.py'])]
jobs += [('browser',['npx.cmd','playwright','test','--config','webservice/frontend/playwright.external.config.ts','--project=chromium','--workers=2','--grep','UI result contract|Explorer context redesign|Route smoke|Mobile|Node properties|Entity resolution|Terminology expansion|Provenance','--reporter=line'])]
results=[]
for name,cmd in jobs:
    with (OUT/(name+'.log')).open('w',encoding='utf-8') as log:
        run=subprocess.run(cmd,cwd=ROOT,stdout=log,stderr=subprocess.STDOUT)
    results.append({'name':name,'command':cmd,'exit_code':run.returncode})
    (OUT/'summary.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
    print(name,run.returncode,flush=True)
raise SystemExit(any(r['exit_code'] for r in results))
