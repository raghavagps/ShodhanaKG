"""Live read-only tests: terminology resolves identity; factual oracle stays canonical KG retrieval."""
import json,sys,urllib.request,urllib.parse
from pathlib import Path
from evaluate_entity_resolution import request
CASES={
 'NMR':('ambiguous',['Nuclear Magnetic Resonance (NMR)']), 'NDI':('ambiguous',['Neck Disability Index (NDI)']), 'IBS':('ambiguous',['Irritable Bowel Syndrome','Irritable Bowel Syndrome (IBS)']),
 'Lou Gehrig Disease':('resolved',['Amyotrophic Lateral Sclerosis']),
 'Chronic Renal Insufficiency':('resolved',['Chronic Kidney Disease']),
 'irritable colon':('resolved',['Irritable Bowel Syndrome']),
 'Eczematous Dermatitis':('resolved',['Eczema']),
 'Tamra':('ambiguous',['Copper']), 'Gomutra':('ambiguous',['Cow Urine']),
 'Vatsanabha':('ambiguous',['Aconitum ferox','Aconitum chasmanthum Holmes ex Stapf.']),
 'Abhraka':('ambiguous',['Mica','Biotite Mica']),
 'Ardraka Swarasa':('ambiguous',['Ginger Juice','Trikshara:']),
 'Cardiovascular Disorder':('broader',['Takayasu Arteritis','Vasculitis','Ischemic Heart Disease']),
 'Autoimmune Disorder':('broader',['Rheumatoid Arthritis']),
 'Skin Disorder':('broader',['Psoriasis','Eczema']),
 'ESCC':('resolved',['Esophageal Squamous Cell Carcinoma']), 'Copper':('resolved',['Copper']), 'zzzzunknown':('unmatched',[])}
def main():
 rows=[];responses=[]
 def check(name,ok):rows.append({'case':name,'passed':bool(ok)})
 for term,(outcome,labels) in CASES.items():
  with urllib.request.urlopen('http://127.0.0.1:8081/api/resolve_entity.php?q='+urllib.parse.quote(term),timeout=30) as f:r=json.load(f)['resolution']
  candidates=r['suggestions'] if outcome=='broader' else r['candidates']
  check('resolve:'+term,r['outcome']==outcome and set(labels)<=set(c['label'] for c in candidates));responses.append({'term':term,'resolution':r})
 for transport in ['json','sse']:
  prefix=transport+':'
  for unknown in ['Zorblax Syndrome','Unknown Autoimmune Disorder']:
   a,_=request('What symptoms are associated with '+unknown+'?',transport)
   check(prefix+'unknown modifier:'+unknown,a.get('status')=='entity_not_found' and not a.get('clarification') and not a.get('answer_results'))
  for alias,label in [('Lou Gehrig Disease','Amyotrophic Lateral Sclerosis'),('irritable colon','Irritable Bowel Syndrome')]:
   a,_=request('What symptoms are associated with '+alias+'?',transport)
   b,_=request('What symptoms are associated with disease '+label+'?',transport)
   check(prefix+'canonical factual equivalence:'+alias,a.get('status')=='success' and all(a.get(k)==b.get(k) for k in ['answer_results','citations','evidence_records']))
  a,_=request('Which methods are used to purify Tamra?',transport)
  options=a.get('clarification',{}).get('candidates',[])
  check(prefix+'KG alternate name confirmation',a.get('status')=='needs_clarification' and any(c['label']=='Copper' for c in options))
  chosen=next(c for c in options if c['label']=='Copper')
  b,_=request('Copper',transport,a.get('conversation_context'),chosen)
  c,_=request('Which methods are used to purify Copper?',transport)
  check(prefix+'selected alias preserves evidence',b.get('status')=='success' and all(b.get(k)==c.get(k) for k in ['answer_results','citations','evidence_records']))
  a,_=request('What symptoms occur with Autoimmune Disorder?',transport)
  options=a.get('clarification',{}).get('candidates',[])
  check(prefix+'broader wording',a.get('status')=='needs_clarification' and any(c['label']=='Rheumatoid Arthritis' and c['type']=='Disease' for c in options))
  chosen=next(c for c in options if c['label']=='Rheumatoid Arthritis' and c['type']=='Disease')
  b,_=request('Rheumatoid Arthritis',transport,a.get('conversation_context'),chosen)
  c,_=request('What symptoms are associated with disease Rheumatoid Arthritis?',transport)
  check(prefix+'broader choice retains type and evidence',b.get('status')=='success' and all(b.get(k)==c.get(k) for k in ['answer_results','citations','evidence_records']))
  a,_=request('Describe chronic renal insufficiency',transport)
  b,_=request('Describe Chronic Kidney Disease',transport)
  check(prefix+'node properties remain KG-only',a.get('status')=='success' and a.get('node_evidence')==b.get('node_evidence'))
 for domain in ['disease','compound','integrated']:
  term='Chronic Renal Insufficiency' if domain=='disease' else 'Tamra'
  with urllib.request.urlopen('http://127.0.0.1:8081/api/search.php?domain='+domain+'&q='+urllib.parse.quote(term),timeout=30) as f:data=json.load(f)
  check('explorer:'+domain,any(n['label']==('Chronic Kidney Disease' if domain=='disease' else 'Copper') for n in data.get('nodes',[])))
 out={'total':len(rows),'passed':sum(r['passed'] for r in rows),'cases':rows,'resolutions':responses}
 Path(sys.argv[1]).write_text(json.dumps(out,indent=2),encoding='utf-8');print(json.dumps({k:v for k,v in out.items() if k!='resolutions'}));return out['total']!=out['passed']
if __name__=='__main__':raise SystemExit(main())
