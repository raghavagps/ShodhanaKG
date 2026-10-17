"""Mutation checks ensure the migrated scripts still reject false evidence."""
import copy
import json
from pathlib import Path
import unittest
from legacy_reference_check import check
from reviewed_reference_oracle import identifiers
import build_expanded_ground_truth as expanded

class LegacyReferenceCheckTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        folder=Path(__file__).parent/'fixtures'
        cases=json.loads((folder/'expanded_factual_cases_v4.json').read_text(encoding='utf8'))
        cls.case=next(c for c in cases if c['expected_endpoint']=='compound_media' and c['expected_entity']=='Copper')
        tables=json.loads((folder/'factual_graph_snapshot_expanded.json').read_text(encoding='utf8'))['tables']
        records={r['@rid']:r for rows in tables.values() for r in rows}
        seed=next(r for r in records.values() if r.get('@type')=='Compound' and r.get('name')=='Copper')
        cls.answer={'status':'success','results':copy.deepcopy(cls.case['expected_results']),'evidence_paths':[]}
        for i,paths in enumerate(cls.case['expected_result_source_paths']):
            group=[]
            for ids in paths:
                vertices=[seed]; edges=[]
                for rid in ids:
                    raw=records[rid]
                    target=raw['@in'] if raw['@out']==vertices[-1]['@rid'] else raw['@out']
                    vertices.append(records[target])
                    edges.append({'id':rid,'type':raw['@type'],'source':raw['@out'],'target':raw['@in'],
                                  'properties':{k:v for k,v in raw.items() if not k.startswith('@')},'evidence':identifiers(raw)})
                group.append({'nodes':[{'id':v['@rid'],'type':v['@type'],'label':v['name']} for v in vertices],
                              'edges':edges,'correlation':'shared_study'})
            cls.answer['evidence_paths'].append({'result_index':i,'paths':group})

    def test_frozen_reference_passes(self):
        original={name:getattr(expanded,name) for name in ('identifiers','properties','legacy_project','shared_reference')}
        issues,count=check('compound_media','Copper',self.answer)
        self.assertEqual(issues,[])
        self.assertEqual(count,self.case['expected_result_count'])
        self.assertEqual(original,{name:getattr(expanded,name) for name in original},'Oracle composition leaked into other tests')

    def test_corruption_is_rejected(self):
        for fault in ('missing_row','missing_property','reversed_edge','wrong_label','duplicate_path','wrong_reference'):
            with self.subTest(fault=fault):
                answer=copy.deepcopy(self.answer)
                path=answer['evidence_paths'][0]['paths'][0]; edge=path['edges'][0]
                if fault=='missing_row': answer['results'].pop(); answer['evidence_paths'].pop()
                if fault=='missing_property': edge['properties'].clear()
                if fault=='reversed_edge': edge['source'],edge['target']=edge['target'],edge['source']
                if fault=='wrong_label': path['nodes'][0]['label']='Invented compound'
                if fault=='duplicate_path': answer['evidence_paths'][0]['paths'].append(copy.deepcopy(path))
                if fault=='wrong_reference': edge['evidence']['pubmed_id']='999999999'
                self.assertTrue(check('compound_media','Copper',answer)[0],fault)

if __name__=='__main__': unittest.main()
