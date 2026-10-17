"""Independent UNDERGOES edge oracle for method questions, JSON/SSE parity."""
import argparse,json
from pathlib import Path
from evaluate_entity_resolution import request
p=argparse.ArgumentParser();p.add_argument('--output',type=Path,required=True);a=p.parse_args()
tables=json.loads((Path(__file__).parent/'fixtures/ui_result_contract_2026_09_25_v1/baseline/snapshot.json').read_bytes())['tables']
nodes={n['@rid']:n for rows in tables.values() for n in rows if n.get('@cat')=='v'}
rows=[]
for entity in ['Copper','Lead','Sulphur']:
 seed=next(n for n in nodes.values() if n['@type']=='Compound' and n['name']==entity)
 expected_edges={e['@rid'] for e in tables['UNDERGOES'] if e['@out']==seed['@rid']}
 expected_nodes={e['@in'] for e in tables['UNDERGOES'] if e['@out']==seed['@rid']}
 expected_labels={nodes[r]['name'] for r in expected_nodes}
 for pattern in ['What Shodhana method is used to purify {}?','Which purification technique is used for {}?','How can {} be purified using a Shodhana method?','Name the methods used to purify {}.','List purification techniques for {}.']:
  pair=[]
  for transport in ['json','sse']:
   q=pattern.format(entity);r,ms=request(q,transport);data=r.get('context',{}).get('retrieval',{});paths=data.get('results',[])
   actual_edges={e['id'] for path in paths for e in path.get('edges',[])}
   actual_nodes={path['nodes'][-1]['id'] for path in paths if path.get('nodes')}
   labels={n['label'] for n in r.get('answer_results',[])}
   passed=r['status']=='success' and actual_edges==expected_edges and actual_nodes==expected_nodes and labels==expected_labels and all(path['nodes'][-1]['type']=='Shodhana_Method' for path in paths)
   rows.append(dict(question=q,transport=transport,passed=passed,latency_ms=ms,response=r));pair.append((paths,r.get('answer_results'),r.get('citations')))
  rows.append(dict(question=q,transport='parity',passed=pair[0]==pair[1]))
result={'summary':{'total':len(rows),'passed':sum(r['passed'] for r in rows)},'rows':rows}
a.output.write_text(json.dumps(result,indent=2),encoding='utf-8');print(result['summary'])
raise SystemExit(any(not r['passed'] for r in rows))
