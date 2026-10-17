"""Versioned logs for the shared Explorer/context regression suites. Never manages servers."""
import json,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'api/tests/fixtures/explorer_context_2026_09_25_v1/checks'
OUT.mkdir(exist_ok=True)
php=r'C:\xampp\php\php.exe'
jobs=[(p,[php,'api/tests/'+p]) for p in ['test_terminology_expansion.php','test_node_property_answer.php','test_entity_resolution.php','test_results_presentation.php','test_legacy_evidence_presentation.php','test_conversational_answer.php','test_conversation_routing.php','test_context_immutability.php','provenance-subgraph.test.php']]
jobs += [('schema_reference',[sys.executable,'api/tests/test_schema_reference.py']),('compiler_policy',[sys.executable,'api/tests/test_build_terminology_catalog.py'])]
results=[]
for name,cmd in jobs:
    with (OUT/(name+'.log')).open('w',encoding='utf-8') as log:
        run=subprocess.run(cmd,cwd=ROOT,stdout=log,stderr=subprocess.STDOUT)
    results.append({'name':name,'command':cmd,'exit_code':run.returncode})
    (OUT/'summary.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
    print(name,run.returncode,flush=True)
raise SystemExit(any(r['exit_code'] for r in results))
