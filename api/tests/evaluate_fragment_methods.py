"""Fragment requests against independent raw UNDERGOES edges; no model oracle."""
import json, argparse
from pathlib import Path
from evaluate_entity_resolution import request
root=Path(__file__).resolve().parents[2]
parser=argparse.ArgumentParser();parser.add_argument('--output',type=Path,required=True);args=parser.parse_args()
tables=json.loads((root/'api/tests/fixtures/ui_result_contract_2026_09_25_v1/baseline/snapshot.json').read_bytes())['tables']
rows=[]
for name in ['Lead','Copper','Sulphur']:
 seed=next(n for n in tables['Compound'] if n['name']==name)
 edges=[e for e in tables['UNDERGOES'] if e['@out']==seed['@rid']]
 expected_ids={e['@in'] for e in edges};expected_edges={e['@rid'] for e in edges}
 labels={n['name'] for n in tables['Shodhana_Method'] if n['@rid'] in expected_ids}
 for pattern in ['Shodhana method to purify {}.','Purification methods for {}.','Shodhana techniques used for {}.','Method to purify {}.','What about the Shodhana methods for {}?']:
  pair=[]
  for transport in ['json','sse']:
   q=pattern.format(name);r,ms=request(q,transport);paths=r.get('context',{}).get('retrieval',{}).get('results',[])
   ok=r['status']=='success' and {p['nodes'][-1]['id'] for p in paths}==expected_ids and {e['id'] for p in paths for e in p['edges']}==expected_edges and {n['label'] for n in r.get('answer_results',[])}==labels
   rows.append(dict(question=q,transport=transport,passed=ok,latency_ms=ms,response=r));pair.append((paths,r.get('citations'),r.get('answer_results')))
  rows.append(dict(question=q,transport='parity',passed=pair[0]==pair[1]))
for q in ['Purify Lead.','Purification of Copper.']:
 for transport in ['json','sse']:
  r,ms=request(q,transport);rows.append(dict(question=q,transport=transport,passed=r['status']=='needs_clarification',response=r))
result={'summary':{'total':len(rows),'passed':sum(r['passed'] for r in rows)},'rows':rows}
args.output.write_text(json.dumps(result,indent=2),encoding='utf-8');print(result['summary'])
raise SystemExit(any(not r['passed'] for r in rows))
