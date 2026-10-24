"""Read-only HTTP checks against an independent pre-change KG snapshot."""
import json, sys
from pathlib import Path
from evaluate_entity_resolution import request
from stored_node_contract import node_issues
snapshot=json.loads((Path(__file__).resolve().parent / 'fixtures/resolution_baseline_2026_09_25_v1/snapshot.json').read_text(encoding='utf-8'))
nodes={r['@rid']:r for rows in snapshot['tables'].values() for r in rows}
rows=[]
def check(name,ok):
    rows.append({'case':name,'passed':bool(ok)})
responses={}
for transport in ['json','sse']:
    prefix=transport+':'
    r,_=request('Describe Psoriasis',transport)
    check(prefix+'stored description',not node_issues({'node_id':'#45:82','property':'*'},r,nodes) and r['node_evidence']['properties']['Description']==nodes['#45:82']['Description'])
    check(prefix+'node identity',r.get('node_evidence',{}).get('node_id')=='#45:82')
    check(prefix+'no edge citations',r.get('citations')==[] and r.get('evidence_records')==[])
    follow,_=request('What is its molecular weight?',transport,r.get('conversation_context'))
    check(prefix+'property followup preserves subject',follow.get('node_evidence',{}).get('node_id')=='#45:82' and follow.get('status')=='no_data')
    for q in ['What is Copper?','What is the description of coper?','What is the molecular weight of Copper?']:
        a,_=request(q,transport)
        check(prefix+q,not node_issues({'node_id':'#5:24','property': '*' if q=='What is Copper?' else 'description' if 'description' in q else 'molecular weight'},a,nodes))
    a,_=request('What is the Ayurvedic Name of Copper?',transport)
    check(prefix+'arbitrary stored field',a.get('node_evidence',{}).get('properties')=={'Ayurvedic_Name':nodes['#5:24']['Ayurvedic_Name']})
    a,_=request('What are the symptoms of Psoriasis?',transport)
    check(prefix+'relationship still separate',a.get('status')=='success' and not a.get('node_evidence') and bool(a.get('citations')))
    a,_=request('Describe Mercury',transport)
    check(prefix+'ambiguous definition clarifies',a.get('status')=='needs_clarification')
    a,_=request('What is its description?',transport)
    check(prefix+'missing context clarifies',a.get('status')=='needs_clarification')
    responses[transport]=r
check('JSON/SSE answer and provenance parity',all(responses['json'].get(k)==responses['sse'].get(k) for k in ['answer','status','node_evidence']))
out={'total':len(rows),'passed':sum(r['passed'] for r in rows),'cases':rows}
Path(sys.argv[1]).write_text(json.dumps(out,indent=2),encoding='utf-8')
print(json.dumps(out))
raise SystemExit(out['passed']!=out['total'])
