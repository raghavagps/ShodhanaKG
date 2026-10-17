"""Verify structural figure exports against the original snapshot, without KG writes."""
from __future__ import annotations
import argparse
import collections
import csv
import hashlib
import json
from pathlib import Path
import xml.etree.ElementTree as ET

from plot_kg_structure_analysis import ari
from PIL import Image

NS = '{http://www.w3.org/2000/svg}'

def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()

def verify(folder: Path) -> dict:
    manifest = json.loads((folder / 'manifest.json').read_text(encoding='utf-8'))
    source = Path(manifest['input'])
    assert digest(source) == manifest['input_sha256'], 'Input changed'
    raw = json.loads(source.read_text(encoding='utf-8'))
    records = {}
    for table in raw['tables'].values():
        for row in table:
            assert row['@rid'] not in records or records[row['@rid']] == row
            records[row['@rid']] = row
    nodes = {k:r for k,r in records.items() if r['@cat'] == 'v'}
    edges = {k:r for k,r in records.items() if r['@cat'] == 'e'}
    assert len(nodes) == 2534 and len(edges) == 10123
    for r in edges.values():
        assert r['@out'] in nodes and r['@in'] in nodes
    actual = collections.Counter((nodes[r['@out']]['@type'],r['@type'],nodes[r['@in']]['@type']) for r in edges.values())
    rows = list(csv.DictReader((folder/'data/schema_counts.csv').open(encoding='utf-8')))
    assert actual == {(r['source_category'],r['predicate'],r['target_category']):int(r['records']) for r in rows}
    bridge = {k:r for k,r in edges.items() if r['@type']=='USED_FOR_TREATMENT'}
    assert len(bridge)==75
    assert all(nodes[r['@out']]['@type']=='Purified_Compound' and nodes[r['@in']]['@type']=='Disease' for r in bridge.values())
    summary=[]
    for entry in manifest['figures']:
        name=entry['name'];base=folder/name;root=ET.parse(base.with_suffix('.svg')).getroot()
        assert root.get('viewBox') and list(root.iter(NS+'text')) and list(root.iter(NS+'path'))
        assert not list(root.iter(NS+'image'))
        ids=[e.attrib['id'] for e in root.iter() if 'id' in e.attrib]
        assert len(ids)==len(set(ids)), name+' duplicate SVG IDs'
        for e in root.iter():
            for key,value in e.attrib.items():
                if key.endswith('href'):assert value.startswith('#')
        seen_nodes={};seen_edges={}
        for e in root.iter():
            if e.get('id','').startswith(('node-','edge-')):
                record=json.loads(e.find(NS+'title').text);rid=record['@rid']
                if record['@cat']=='v':
                    assert nodes[rid]==record;seen_nodes[rid]=record
                else:
                    assert edges[rid]==record;seen_edges[rid]=record
        if entry['meta'].get('nodes'):
            assert len(seen_nodes)==entry['meta']['nodes'] and len(seen_edges)==entry['meta']['edges']
            assert set(seen_edges)=={k for k,r in edges.items() if r['@out'] in seen_nodes and r['@in'] in seen_nodes}
            if len(seen_nodes)==len(nodes):assert set(seen_edges)==set(edges)
        pdf=base.with_suffix('.pdf').read_bytes()
        assert pdf.startswith(b'%PDF-') and b'/Subtype /Image' not in pdf and b'/Font' in pdf
        for suffix,subdir in [('.png','previews'),('.jpg','submission_jpeg')]:
            with Image.open(folder/subdir/(base.name+suffix)) as im:
                assert im.mode=='RGB' and min(im.info.get('dpi',(0,0)))>=299.5
        summary.append({'figure':name,'vector_and_text':True,'rgb_300dpi':True,'nodes_checked':len(seen_nodes),'edges_checked':len(seen_edges)})
    assert len(summary)==21 and sum(e['name'].startswith('core/') for e in manifest['figures'])==5
    # Known contingency-table examples check the custom agreement calculation.
    assert ari([0,0,1,1],[1,1,0,0])==1
    assert abs(ari([0,0,1,1],[0,1,0,1])+.5)<1e-12
    assert ari([0,1,2],[2,0,1])==1
    for domain in ['complete','compound','disease']:
        assignments=list(csv.DictReader((folder/f'data/{domain}_community_assignments.csv').open(encoding='utf-8')))
        assert len({r['node_id'] for r in assignments})==len(assignments)
        for weighting in ['unweighted','weighted']:
            stability=list(csv.DictReader((folder/f'data/{domain}_{weighting}_stability.csv').open(encoding='utf-8')))
            assert {int(r['seed']) for r in stability}==set(range(20))
    result={'status':'PASS','figures':len(summary),'core':5,'supplementary':16,'snapshot_sha256':digest(source),'source_nodes':len(nodes),'source_edges':len(edges),'integration_edges':len(bridge),'schema_triples':len(actual),'distinct_predicates':len({k[1] for k in actual}),'community_fits':120,'checks':summary}
    (folder/'independent_export_checks.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
    return result

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--output',type=Path,default=Path('figures/structure_analysis'))
    report=verify(p.parse_args().output)
    print(json.dumps({k:v for k,v in report.items() if k!='checks'},indent=2))
