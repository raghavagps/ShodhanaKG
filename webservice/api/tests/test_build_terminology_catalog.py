import sys, tempfile, unittest, zipfile
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_terminology_catalog import compile_catalog

class TerminologyPolicyTests(unittest.TestCase):
    def test_source_ambiguity_type_and_alias_policy(self):
        lines=[
            ['C1','','C2','Clinical Condition|External alias|CC|Severe Clinical Condition','','','', 'Disease or Syndrome'],
            ['C2','','','Broad Illness','','','', 'Disease or Syndrome'],
            ['C3','','','Duplicate','','','', 'Disease or Syndrome'],
            ['C4','','','Duplicate','','','', 'Disease or Syndrome'],
            ['C5','','','Retired Condition','','','Retired_Concept', 'Disease or Syndrome'],
        ]
        nodes=[{'@rid':'#1:1','@type':'Disease','@cat':'v','name':'Clinical Condition'},
            {'@rid':'#1:2','@type':'Disease','@cat':'v','name':'Duplicate'},
            {'@rid':'#1:3','@type':'Compound','@cat':'v','name':'Clinical Condition'},
            {'@rid':'#1:4','@type':'Disease','@cat':'v','name':'Retired Condition'},
            {'@rid':'#1:5','@type':'Disease','@cat':'v','name':'External alias'},
            {'@rid':'#1:6','@type':'Confirmation_Study','@cat':'v','name':'Recorded Measurement Test (RMT)'}]
        with tempfile.TemporaryDirectory() as tmp:
            archive=Path(tmp)/'source.zip'
            with zipfile.ZipFile(archive,'w') as z:z.writestr('Thesaurus.txt','\n'.join('\t'.join(f) for f in lines))
            catalog,proof,audit=compile_catalog({'tables':{'nodes':nodes}},archive)
        states={r['rid']:r['status'] for r in audit}
        self.assertEqual(states['#1:2'],'ambiguous_source_concepts')
        self.assertEqual(states['#1:3'],'incompatible_source_category')
        self.assertEqual(states['#1:4'],'unmapped')
        mappings={m['rid']:m for m in catalog['mappings']}
        first=mappings['#1:1']
        policies={a['term']:a['policy'] for a in first['aliases']}
        self.assertEqual(policies['External alias'],'resolve')
        self.assertEqual(policies['CC'],'confirm')
        self.assertEqual(policies['Severe Clinical Condition'],'confirm')
        self.assertEqual(first['broader_terms'],[{'term':'Broad Illness','path':['C1','C2']}])
        self.assertTrue(all(a['policy']=='confirm' for a in mappings['#1:5']['aliases']))
        self.assertEqual(mappings['#1:6']['property_bindings'],{'name':'Recorded Measurement Test (RMT)'})
        self.assertEqual({a['term'] for a in mappings['#1:6']['aliases']},{'Recorded Measurement Test','RMT'})

if __name__=='__main__':unittest.main()
