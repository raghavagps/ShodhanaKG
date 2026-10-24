"""End-to-end clarification and context; factual expectations use raw KG records."""
import argparse
import json
from pathlib import Path
import urllib.request
from evaluate_entity_resolution import request

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    snapshot = json.loads((Path(__file__).resolve().parent / 'fixtures/resolution_baseline_2026_09_25_v1/snapshot.json').read_text(encoding='utf-8'))
    records = {r['@rid']:r for rows in snapshot['tables'].values() for r in rows}
    rows = []
    def check(name, ok, **details):
        rows.append(dict(name=name, passed=bool(ok), **details))
    for transport in ['json','sse']:
        prefix = transport + ':'
        a,_ = request('What are the methods to purify coper?',transport)
        b,_ = request('And which diseases is it used for?',transport,a.get('conversation_context'))
        check(prefix+'typo_followup',b.get('context',{}).get('routing',{}).get('entity')=='Copper')
        a,_ = request('What are the symptoms of cancer?',transport)
        choices = a.get('clarification',{}).get('candidates',[])
        choice = next((c for c in choices if c['type']=='Disease'),None)
        check(prefix+'broader_choice',choice is not None and a['status']=='needs_clarification')
        if choice:
            b,_ = request(choice['label'],transport,a['conversation_context'],choice)
            r=b.get('context',{}).get('routing',{})
            check(prefix+'broader_keeps_intent',r.get('endpoint')=='disease_symptoms' and r.get('entity_id')==choice['id'])
        a,_ = request('What are the therapies of cancer?',transport)
        choice = next((c for c in a.get('clarification',{}).get('candidates',[]) if c['type']=='Indication'),None)
        if choice:
            b,_ = request(choice['label'],transport,a['conversation_context'],choice)
            actual={e['id'] for path in b.get('context',{}).get('retrieval',{}).get('results',[]) for e in path.get('edges',[])}
            expected={r['@rid'] for r in snapshot['tables']['TREATS'] if r['@in']==choice['id']}
            check(prefix+'indication_exact_edges',b['status']=='success' and actual==expected and len(expected)>0, expected=sorted(expected),actual=sorted(actual))
            paths=b.get('context',{}).get('retrieval',{}).get('results',[])
            check(prefix+'indication_direction_properties',all(e['source']==records[e['id']]['@out'] and e['target']==records[e['id']]['@in'] and e['properties']=={k:v for k,v in records[e['id']].items() if not k.startswith('@')} for p in paths for e in p.get('edges',[])))
        else:check(prefix+'indication_exact_edges',False)
        forged,_=request('Lead',transport,a.get('conversation_context'),{'id':'#5:22','result_type':'Purified_Compound'})
        check(prefix+'forged_selection',forged['status']=='needs_clarification' and not forged.get('context',{}).get('retrieval'))
        a,_=request('What are the symptoms of Alopecia?',transport)
        choice=next((c for c in a.get('clarification',{}).get('candidates',[]) if c['type']=='Disease'),None)
        check(prefix+'ambiguous_types',choice is not None and len(a.get('clarification',{}).get('candidates',[]))==2)
        if choice:
            b,_=request(choice['label'],transport,a['conversation_context'],choice)
            c,_=request('And what therapies are used for it?',transport,b.get('conversation_context'))
            check(prefix+'selected_type_memory',c.get('context',{}).get('routing',{}).get('entity_type')=='Disease' and c.get('context',{}).get('routing',{}).get('entity')=='Alopecia')
        a,_=request('What methods purify Copper?',transport)
        b,_=request('What are the symptoms of Psoriasis?',transport,a.get('conversation_context'))
        c,_=request('And which therapies are used for it?',transport,b.get('conversation_context'))
        check(prefix+'topic_change',c.get('context',{}).get('routing',{}).get('entity')=='Psoriasis')
        c,_=request('Which methods purify zzzunknown?',transport,a.get('conversation_context'))
        check(prefix+'unknown_not_memory',c['status']!='success')
    for domain,q,expected in [('compound','coper','#5:24'),('disease','ESCC','#45:39'),('integrated','coper','#5:24')]:
        data=json.load(urllib.request.urlopen('http://localhost:8080/api/search.php?domain='+domain+'&q='+q))
        check('search:'+domain+':'+q,expected in [n['id'] for n in data['nodes']])
    report={'total':len(rows),'passed':sum(r['passed'] for r in rows),'checks':rows}
    args.output.parent.mkdir(parents=True,exist_ok=True)
    with args.output.open('x',encoding='utf-8') as f:json.dump(report,f,indent=2)
    print(json.dumps(report))
    return 0 if report['passed']==report['total'] else 1

if __name__=='__main__':raise SystemExit(main())
