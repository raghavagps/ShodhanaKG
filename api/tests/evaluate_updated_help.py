"""Focused FAQ transport and stored-property fidelity checks; never edits reference data."""
import json, re, sys, urllib.request
from pathlib import Path

URL='http://127.0.0.1:8081/api/chatbot_response.php'
ROOT=Path(__file__).resolve().parents[2]
def request(question,stream=False,**fields):
    body=json.dumps(dict(question=question,stream=stream,**fields)).encode()
    raw=urllib.request.urlopen(urllib.request.Request(URL,body,{'Content-Type':'application/json'}),timeout=120).read().decode()
    if not stream:return json.loads(raw)
    for block in raw.replace('\r\n','\n').split('\n\n'):
        if block.startswith('event: done\n'):return json.loads(block.split('data: ',1)[1])
    raise AssertionError('Missing SSE terminal response')

rows=[]
def check(name,condition):rows.append(dict(name=name,passed=bool(condition)))
catalog=json.loads((ROOT/'api/core/faq_catalog.json').read_text())
for faq in catalog['entries']:
    a=request(faq['question'],faq_id=faq['id'])
    b=request(faq['question'],True,faq_id=faq['id'])
    check(faq['id']+':JSON/SSE',a==b)
    check(faq['id']+':separate evidence',a.get('citations')==[] and a.get('evidence_records')==[] and a.get('faq',{}).get('id')==faq['id'])
snapshot=json.loads((ROOT/'api/tests/fixtures/factual_graph_snapshot_expanded.json').read_text(encoding='utf-8'))
nodes={row['@rid']:row for table in snapshot['tables'].values() for row in table}
for question in ['What is Copper?','Tell me about Psoriasis.','Show me details about Sarvanga Abhyanga.']:
    a=request(question);b=request(question,True)
    for transport,response in [('JSON',a),('SSE',b)]:
        evidence=response.get('node_evidence',{});raw=nodes.get(evidence.get('node_id'),{})
        expected={k:v for k,v in raw.items() if k and not k.startswith(('@','_')) and not re.match(r'^(in|out)(_|$)',k,re.I) and v is not None and v!=[] and not(isinstance(v,str)and not v.strip())}
        check(question+':'+transport, bool(raw) and evidence.get('properties')==expected and not response.get('citations'))
    check(question+':parity', all(a.get(k)==b.get(k)for k in ['answer','status','node_evidence']))
report=dict(total=len(rows),passed=sum(r['passed']for r in rows),checks=rows)
Path(sys.argv[1]).write_text(json.dumps(report,indent=2),encoding='utf-8')
print(json.dumps(report));raise SystemExit(report['passed']!=report['total'])
