"""Fresh retrieval equivalence and context checks; assistant prose is never an oracle."""
import argparse,json
from pathlib import Path
from evaluate_entity_resolution import request

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--output',type=Path,required=True);args=parser.parse_args()
    rows=[]
    def check(name,ok,**details): rows.append(dict(name=name,passed=bool(ok),**details))
    def facts(response):
        retrieval=response.get('context',{}).get('retrieval',{})
        return retrieval.get('results',[])
    for transport in ['json','sse']:
        def ask(q,state=None): return request(q,transport,state)[0]
        for name,first,follow,direct,subject,target in [
            ('disease','What therapies are used for Vipadika?','What about its formulations?','What formulations are used for Vipadika?','Vipadika','Formulation'),
            ('compound','What methods purify Copper?','And which diseases is it used for?','Which diseases is Copper used for?','Copper','Disease'),
            ('topic','What therapies are used for Vipadika?','What methods purify Copper?','What methods purify Copper?','Copper','Shodhana_Method'),
            ('entity_only_switch','What methods purify Copper?','What about Lead?','What methods purify Lead?','Lead','Shodhana_Method'),
            ('constraint','Which formulation is used in Vipadika in Sarvanga Abhyanga?','What about its formulations?','Which formulation is used in Vipadika in Sarvanga Abhyanga?','Vipadika','Formulation'),
            ('new_constraint','What therapies are used for Vipadika?','What about its formulations in Sarvanga Abhyanga?','Which formulation is used in Vipadika in Sarvanga Abhyanga?','Vipadika','Formulation'),
            ('clear_constraint','Which formulation is used in Vipadika in Sarvanga Abhyanga?','What about its symptoms without previous constraints?','What symptoms are associated with Vipadika?','Vipadika','Symptom'),
        ]:
            a=ask(first); b=ask(follow,a.get('conversation_context')); c=ask(direct)
            state=b.get('conversation_context',{})
            check(transport+':'+name,b['status']==c['status'] and facts(b)==facts(c) and state.get('subject',{}).get('name')==subject and state.get('result_type')==target,status=b['status'],state=state)
            if name=='constraint': check(transport+':constraint_id',all(c.get('id') for c in state.get('constraints',[])) and bool(state.get('constraints')))
        a=ask('What therapies are used for Vipadika?');state=a['conversation_context']
        for label,tamper in [('missing_id',dict(state,subject=dict(state['subject'],id='#99999:99999'))),('wrong_type',dict(state,subject=dict(state['subject'],type='Compound'))),('bad_version',dict(state,version=999))]:
            b=ask('What about its formulations?',tamper)
            check(transport+':'+label,b['status']=='needs_clarification',status=b['status'])
        b=ask('What is its chemical formula?',state)
        check(transport+':missing_property',b['status']=='no_data' and b.get('context',{}).get('routing',{}).get('endpoint')=='node_property')
        b=ask('What diseases does that result treat?',state)
        check(transport+':ambiguous_result',b['status']=='needs_clarification')
        b=ask('What about its formulations?',dict(state,version=1))
        check(transport+':v1_compatibility',b['status']=='success' and b['conversation_context']['version']==2)
        a=ask('What symptoms occur with cancer?'); candidates=a.get('clarification',{}).get('candidates',[])
        choice=next((c for c in candidates if c['type']=='Disease'),None)
        if choice:
            selected=request(choice['label'],transport,a['conversation_context'],choice)[0]
            b=ask('What about its therapies?',selected.get('conversation_context'))
            c=ask('What therapies are used for '+choice['label']+'?')
            check(transport+':clarification_followup',facts(b)==facts(c) and b['status']==c['status'])
        else: check(transport+':clarification_followup',False)
    result={'checks':rows,'summary':{'total':len(rows),'passed':sum(r['passed'] for r in rows)}}
    args.output.write_text(json.dumps(result,indent=2),encoding='utf-8'); print(result['summary'])
    return any(not r['passed'] for r in rows)
if __name__=='__main__':raise SystemExit(main())
