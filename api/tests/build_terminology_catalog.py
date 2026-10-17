"""Offline, reproducible terminology candidates from pinned NCIt and a raw KG snapshot.
No network, KG writes, inferred clinical equivalences, or generated definitions.
"""
import argparse, collections, hashlib, json, re, zipfile
from pathlib import Path

CLINICAL={'Disease or Syndrome','Neoplastic Process','Mental or Behavioral Dysfunction','Anatomical Abnormality','Acquired Abnormality'}
CHEMICAL={'Element, Ion, or Isotope','Hazardous or Poisonous Substance','Organic Chemical','Inorganic Chemical','Pharmacologic Substance','Plant','Vitamin'}
ALLOWED={
 'Disease':CLINICAL, 'Indication':CLINICAL|{'Sign or Symptom','Finding','Pathologic Function'},
 'Symptom':{'Sign or Symptom','Finding','Mental Process','Pathologic Function','Clinical Attribute'},
 'Compound':CHEMICAL,'Toxic_Substance':CHEMICAL|{'Amino Acid, Peptide, or Protein','Enzyme'},
 'Media':{'Food','Body Substance','Inorganic Chemical','Organic Chemical','Plant'},
 'Therapy':{'Therapeutic or Preventive Procedure'},
 'Assessment_Parameter':{'Diagnostic Procedure','Laboratory Procedure','Clinical Attribute'},
 'Confirmation_Study':{'Laboratory Procedure','Molecular Biology Research Technique','Research Activity'},
}
NAME_FIELDS={'Common_Name','Ayurvedic_Name','Sanskrit_Name'}
SOURCE='https://evs.nci.nih.gov/ftp1/NCI_Thesaurus/Thesaurus_26.08e.FLAT.zip'
def norm(s): return ' '.join(re.findall(r'[^\W_]+',s.lower()))
def digest(p):return hashlib.sha256(Path(p).read_bytes()).hexdigest()
def active(c):return not any(x in c['status'].lower() for x in ['retired','obsolete'])
def alias_policy(term,preferred):
 key=norm(term)
 if not key or not any(c.isalpha() for c in key):return None
 # Short symbols and generic shortened fragments require explicit confirmation.
 if len(key)<4 or (key!=norm(preferred) and set(key.split())<set(norm(preferred).split())):return 'confirm'
 return 'resolve'
def compile_catalog(snapshot,archive):
 with zipfile.ZipFile(archive) as z: lines=z.read('Thesaurus.txt').decode('utf-8-sig').splitlines()
 concepts={};index=collections.defaultdict(set)
 for line in lines:
  f=line.split('\t')
  if len(f)<8:continue
  c=dict(code=f[0],parents=f[2].split('|') if f[2] else [],synonyms=f[3].split('|'),status=f[6],semantic_types=f[7].split('|'))
  concepts[f[0]]=c
  if active(c):
   for term in c['synonyms']:index[norm(term)].add(c['code'])
 mappings=[];audit=[];proof={}
 nodes=[r for rows in snapshot['tables'].values() for r in rows if r.get('@cat')=='v']
 def remember(code):
  if code in concepts:proof[code]=concepts[code]
 for n in nodes:
  typ=n['@type'];label=n['name'];rid=n['@rid'];compatible=[]
  candidates=sorted(index.get(norm(label),set()))
  for code in candidates:
   if set(concepts[code]['semantic_types']) & ALLOWED.get(typ,set()):compatible.append(code)
  record={'rid':rid,'type':typ,'label':label,'candidate_concepts':candidates,'compatible_concepts':compatible,'status':'unmapped'}
  if len(compatible)==1:
   code=compatible[0];c=concepts[code];remember(code)
   aliases=[]
   for term in c['synonyms']:
    policy=alias_policy(term,c['synonyms'][0])
    if policy:
     if norm(label)!=norm(c['synonyms'][0]) or set(norm(c['synonyms'][0]).split())<set(norm(term).split()):policy='confirm'
     aliases.append({'term':term,'policy':policy})
   broader=[];queue=[(code,[code])];visited={code}
   if typ in ['Disease','Indication','Symptom']:
    while queue:
     here,path=queue.pop(0)
     if len(path)>4:continue
     for parent in concepts.get(here,{}).get('parents',[]):
      if parent in visited or parent not in concepts:continue
      visited.add(parent);pc=concepts[parent];newpath=path+[parent];remember(parent)
      if active(pc) and set(pc['semantic_types']) & CLINICAL and parent not in ['C2991','C3367','C7057']:
       for term in pc['synonyms']:
        if alias_policy(term,pc['synonyms'][0])=='resolve':broader.append({'term':term,'path':newpath})
      queue.append((parent,newpath))
   mappings.append(dict(rid=rid,type=typ,label=label,concept_code=code,aliases=aliases,broader_terms=broader,
     source=SOURCE,source_kind='ncit',review_status='source_checked',review_basis='unique exact source synonym, compatible semantic category; not expert clinical validation'))
   record['status']='source_mapped';record['concept_code']=code
  elif len(compatible)>1:record['status']='ambiguous_source_concepts'
  elif candidates:record['status']='incompatible_source_category'
  # These are KG-listed names, not independently certified equivalences. Require confirmation.
  fields={k:v for k,v in n.items() if k in NAME_FIELDS and isinstance(v,str) and v.strip()}
  if fields:
   aliases=[]
   for field,value in fields.items():
    for term in dict.fromkeys([value]+[v.strip() for v in value.split(',')]):
     if norm(term) and not norm(term).isnumeric():aliases.append({'term':term,'policy':'confirm','field':field})
   mappings.append(dict(rid=rid,type=typ,label=label,aliases=aliases,broader_terms=[],property_bindings=fields,
    source='KG node '+rid,source_kind='kg_name_property',review_status='source_checked',review_basis='stored name field, confirmation required; no asserted clinical equivalence'))
   record['kg_name_fields']=list(fields)
  abbreviation=re.fullmatch(r'(.+?)\s*\(([A-Z][A-Z0-9-]{1,11})\)\s*',label)
  if abbreviation:
   full,short=abbreviation.groups()
   initials=''.join(w[0].upper() for w in re.findall('[A-Za-z]+',full) if w.lower() not in ['of','the','and','in','for'])
   if initials==''.join(re.findall('[A-Z]',short)):
    mappings.append(dict(rid=rid,type=typ,label=label,aliases=[{'term':t,'policy':'confirm','field':'name'} for t in [full.strip(),short]],
     broader_terms=[],property_bindings={'name':label},source='KG node '+rid,source_kind='kg_label_abbreviation',review_status='source_checked',
     review_basis='explicit parenthetical abbreviation whose initials match the stored label; confirmation required'))
    record['explicit_abbreviation']=short
  audit.append(record)
 catalog={'version':2,'terminology':'NCIt plus KG-listed alternate names','release':'26.08e',
  'review_scope':'Identifier/label/category and stated hierarchy evidence only; not expert or scientific validation',
  'source':SOURCE,'source_archive_sha256':digest(archive),'mappings':mappings}
 return catalog,{'concepts':proof},audit

def main():
 p=argparse.ArgumentParser();p.add_argument('--snapshot',type=Path,required=True);p.add_argument('--archive',type=Path,required=True);p.add_argument('--output-dir',type=Path,required=True);a=p.parse_args()
 cat,proof,audit=compile_catalog(json.loads(a.snapshot.read_text(encoding='utf-8')),a.archive)
 a.output_dir.mkdir(exist_ok=True,parents=True)
 for name,value in [('catalog.json',cat),('source_concepts.json',proof),('coverage.json',{'snapshot_sha256':digest(a.snapshot),'nodes':audit})]:
  (a.output_dir/name).write_text(json.dumps(value,indent=2,ensure_ascii=False),encoding='utf-8')
 print(json.dumps({'nodes':len(audit),'statuses':dict(collections.Counter(r['status'] for r in audit)),'mapped_by_type':dict(collections.Counter(r['type'] for r in audit if r['status']=='source_mapped')),'kg_name_nodes':sum(bool(r.get('kg_name_fields')) for r in audit),'source_concepts':len(proof['concepts'])}))
if __name__=='__main__':main()
