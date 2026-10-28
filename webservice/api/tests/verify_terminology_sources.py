"""Independent literal evidence/identity audit of the checked-in terminology catalog."""
import collections, hashlib, json, re, zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[3]
def norm(s):return ' '.join(re.findall(r'[^\W_]+',s.lower()))
def main():
 folder=ROOT/'webservice/api/tests/fixtures/terminology_expansion_2026_09_25_v1'
 cat=json.loads((ROOT/'webservice/api/core/terminology_catalog_v2.json').read_text(encoding='utf-8'))
 archive=folder/'Thesaurus_26.08e.FLAT.zip'
 backup=ROOT/'data/archives/historical_benchmarks_backup.zip'
 if archive.exists():
  raw_zip = archive.read_bytes()
 elif backup.exists():
  import io
  with zipfile.ZipFile(backup) as bz:
   raw_zip = bz.read('terminology_expansion_2026_09_25_v1/Thesaurus_26.08e.FLAT.zip')
 else:
  print('NCIT source archive not found; skipping source verification.')
  return
 assert hashlib.sha256(raw_zip).hexdigest()==cat['source_archive_sha256']
 import io
 with zipfile.ZipFile(io.BytesIO(raw_zip)) as z:
  concepts={f[0]:f for line in z.read('Thesaurus.txt').decode('utf-8-sig').splitlines() if len(f:=line.split('\t'))>=8}
 snap=json.loads((ROOT/'webservice/api/tests/fixtures/base_snapshot.json').read_text(encoding='utf-8'))
 nodes={n['@rid']:n for rows in snap['tables'].values() for n in rows if n.get('@cat')=='v'}
 errors=[];aliases=0;paths=0;extra=set();names=collections.defaultdict(set)
 for m in cat['mappings']:
  n=nodes.get(m['rid'],{})
  if (n.get('@type'),n.get('name'))!=(m['type'],m['label']):errors.append([m['rid'],'identity'])
  if m['source_kind']=='ncit':
   f=concepts[m['concept_code']]
   if norm(m['label']) not in map(norm,f[3].split('|')):errors.append([m['rid'],'source anchor'])
   for a in m['aliases']:
    aliases+=1
    if a['policy'] not in ['resolve','confirm']:errors.append([m['rid'],'invalid alias policy'])
    if a['term'] not in f[3].split('|'):errors.append([m['rid'],'unsupported alias',a['term']])
    if a['policy']=='resolve' and (len(norm(a['term']))<4 or norm(m['label'])!=norm(f[3].split('|')[0])):errors.append([m['rid'],'unsafe automatic match'])
   for b in m['broader_terms']:
    paths+=1;p=b['path']
    if not p or p[0]!=m['concept_code'] or any(y not in concepts[x][2].split('|') for x,y in zip(p,p[1:])):errors.append([m['rid'],'unsupported hierarchy'])
    if b['term'] not in concepts[p[-1]][3].split('|'):errors.append([m['rid'],'unsupported broader label'])
  else:
   for field,value in m['property_bindings'].items():
    if n.get(field)!=value:errors.append([m['rid'],'source field'])
   for a in m['aliases']:
    aliases+=1;v=n.get(a['field'],'')
    allowed=[v]+[s.strip() for s in v.split(',')]
    if m['source_kind']=='kg_label_abbreviation':
     match=re.fullmatch(r'(.+?)\s*\(([A-Z][A-Z0-9-]{1,11})\)\s*',v)
     allowed=[match[1].strip(),match[2]] if match else []
     if match and ''.join(w[0].upper() for w in re.findall('[A-Za-z]+',match[1]) if w.lower() not in ['of','the','and','in','for'])!=''.join(re.findall('[A-Z]',match[2])):errors.append([m['rid'],'invalid abbreviation'])
    if a['policy']!='confirm' or a['term'] not in allowed:errors.append([m['rid'],'unverified KG alias'])
  for a in m['aliases']:
   if norm(a['term'])!=norm(m['label']):extra.add(m['rid']);names[m['type']].add(m['rid'])
 report={'nodes_scanned':len(nodes),'mapped_nodes':len({m['rid'] for m in cat['mappings']}),'nodes_with_alternate_terms':len(extra),'aliases_checked':aliases,'hierarchy_paths_checked':paths,'errors':errors,'alternate_term_nodes_by_type':{k:len(v) for k,v in names.items()}}
 if folder.exists(): (folder/'source_verification.json').write_text(json.dumps(report,indent=2))
 print(json.dumps(report));return bool(errors)
if __name__=='__main__':raise SystemExit(main())
