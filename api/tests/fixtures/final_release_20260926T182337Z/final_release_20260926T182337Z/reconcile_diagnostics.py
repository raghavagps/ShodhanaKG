"""Independent read-only analysis of captured raw records and legacy test assumptions."""
import json
from pathlib import Path
p=Path(__file__).resolve().parent
d=json.loads((p/'diagnostics.json').read_text())
s=json.loads((p/'release/benchmark/snapshot.json').read_text())
records={r['@rid']:r for rows in s['tables'].values() for r in rows}
def refs(e):
    aliases={'pubmed_id':['pubmedId','pubmed_id','Pubmed ID','ref'],'doi':['doi','DOI'],'ayush_id':['ayush_id','Ayush_ID','ayushId']}
    return tuple(next((str(e[k]) for k in keys if e.get(k)), '') for keys in aliases.values())
expected=set()
for a in d['raw_treatment_edges']:
    for b in d['raw_formulation_edges']:
        if b['@out']!=a['@in']: continue
        pairs=[(x,y) for x,y in zip(refs(a),refs(b)) if x and y]
        if not pairs or pairs[0][0]!=pairs[0][1]: continue
        expected.add((records[a['@in']]['name'],records[b['@in']]['name'],a['@type'],*refs(b)))
actual={(r['therapy'],r['formulation'],r['properties']['phase'],r['properties'].get('pubmed_id',''),r['properties'].get('doi',''),r['properties'].get('ayush_id','')) for r in d['disease_formulation']['results']}
out={'disease_formulation_old_test':{'expected_unique_tuples':len(expected),'actual_unique_tuples':len(actual),'expected_only':sorted(expected-actual),'actual_only':sorted(actual-expected)}}
# Independently show candidate paths with the same purified entity/ref but different method identity.
paths=[]
for u in s['tables']['UNDERGOES']:
    if u['@out']!='#5:0': continue
    for prod in s['tables']['PRODUCES']:
        if prod['@out']!=u['@in'] or refs(u)!=refs(prod): continue
        for t in s['tables']['TREATS']:
            if t['@out']!=prod['@in']: continue
            paths.append({'edges':[u['@rid'],prod['@rid'],t['@rid']], 'method':records[u['@in']]['name'], 'purified_compound':records[prod['@in']]['name'], 'indication':records[t['@in']]['name'], 'references':refs(t), 'procedure':u.get('procedure','')})
groups={}
for r in paths:
    key=(r['purified_compound'],r['indication'],r['references'],r['procedure'])
    groups.setdefault(key,[]).append(r)
out['therapeutic_use_method_collisions']=[v for v in groups.values() if len({r['method'] for r in v})>1]
(p/'diagnostic_reconciliation.json').write_text(json.dumps(out,indent=2))
print(json.dumps(out,indent=2)[:7000])
