import copy, json, unittest
from pathlib import Path
from stored_node_contract import node_issues

class StoredNodeContractTests(unittest.TestCase):
    def test_independent_oracle_rejects_changed_omitted_or_invented_values(self):
        records = {'#1:1': {'@rid':'#1:1','@type':'Compound','name':'Test','Description':'Stored description','zero':0,'empty':''}}
        reference = {'node_id':'#1:1','property':'*'}
        response = {'status':'success','answer':'name: Test\nDescription: Stored description\nzero: 0',
                    'node_evidence':{'source':'stored_node_properties','node_id':'#1:1','node_type':'Compound','requested_property':'*',
                                     'properties':{'name':'Test','Description':'Stored description','zero':0}}}
        self.assertEqual(node_issues(reference,response,records),[])
        for properties in [{'name':'Test'},{'name':'Test','Description':'Invented','zero':0},{'name':'Test','Description':'Stored description','zero':0,'dose':'Invented'}]:
            changed=copy.deepcopy(response);changed['node_evidence']['properties']=properties
            self.assertIn('stored_node_evidence_mismatch',node_issues(reference,changed,records))
        changed=copy.deepcopy(response);changed['citations']=[{'id':'1'}]
        self.assertIn('node_answer_invented_edge_evidence',node_issues(reference,changed,records))

    def test_versioned_contract_changes_only_approved_turn(self):
        root=Path(__file__).parent
        old=json.loads((root/'fixtures/conversation_evaluation_cases_v3.json').read_text())
        new=json.loads((root/'contracts/conversation_evaluation_cases_v4.json').read_text())
        differences=[(a,b) for x,y in zip(old['scenarios'],new['scenarios']) for a,b in zip(x['turns'],y['turns']) if a!=b]
        self.assertEqual(len(differences),1)
        self.assertEqual(differences[0][1]['node_reference'],{'node_id':'#5:24','property':'*'})

if __name__=='__main__':unittest.main()
