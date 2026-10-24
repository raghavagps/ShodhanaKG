"""Read-only snapshot -> documentation schema, using observed records only."""
import json,sys
from pathlib import Path
root=Path(__file__).resolve().parents[3]
snapshot=Path(sys.argv[1])
tables=json.loads(snapshot.read_bytes())['tables']
records={r['@rid']:r for rows in tables.values() for r in rows}
disease={'Disease','Therapy','Formulation','Symptom','Dosha','Ayurvedic_Diagnosis','Assessment_Parameter'}
vertices=[{'type':k,'domain':'Disease' if k in disease else 'Compound'} for k,rows in tables.items() if rows and rows[0]['@cat']=='v']
edges=[]
for kind,rows in tables.items():
    if not rows or rows[0]['@cat']!='e':continue
    pairs=sorted({(records[r['@out']]['@type'],records[r['@in']]['@type']) for r in rows})
    domains={'Disease' if a in disease and b in disease else 'Compound' if a not in disease and b not in disease else 'Integrated' for a,b in pairs}
    properties=sorted({k for r in rows for k,v in r.items() if not k.startswith('@') and k not in ('out','in') and v is not None and v!='' and v!=[] and v!={}})
    edges.append({'type':kind,'domain':' / '.join(sorted(domains)),'pairs':[{'source':a,'target':b} for a,b in pairs],'properties':properties})
(root/'webservice/frontend/src/data/schemaReference.json').write_text(json.dumps({'vertices':vertices,'edges':edges},indent=2)+'\n',encoding='utf-8')
