"""Live stored-node/FAQ JSON-SSE checks; no KG writes or reference-fixture edits."""
import json
import os
import unittest
from pathlib import Path
from evaluate_conversations import request

class StoredNodeHttpTests(unittest.TestCase):
    def test_stored_fields_and_transport(self):
        output=[]
        base=os.environ.get('SHODHANA_TEST_API_BASE','http://127.0.0.1:8081/api')+'/chatbot_response.php'
        questions=['Describe Acorus calamus Linn.','What is the description of Acorus calamus Linn.?','Tell me about Psoriasis','What is the common name of Acorus calamus Linn.?','Which methods purify Copper?','What is Shodhana?','What is Panchakarma?','What is Copper?','Describe Abhyanga']
        for q in questions:
            with self.subTest(question=q):
                a=request(base,q,[],'json'); b=request(base,q,[],'sse')
                output.append({'question':q,'json':a,'sse':b})
                for field in ['status','answer','node_evidence','conversation_context','citations','evidence_records']:
                    self.assertEqual(a.get(field),b.get(field),field)
                if q=='Describe Acorus calamus Linn.':
                    self.assertEqual(a['status'],'success'); self.assertEqual(a['node_evidence']['node_id'],'#5:6')
                    self.assertIn('Pharmacological_Activity',a['node_evidence']['properties'])
                    self.assertEqual(a['node_evidence']['requested_property'],'*')
                    context=a['conversation_context']
                elif q=='What is the description of Acorus calamus Linn.?':
                    self.assertEqual(a['status'],'no_data'); self.assertEqual(a['node_evidence']['properties'],[])
                elif q=='What is the common name of Acorus calamus Linn.?':
                    self.assertEqual(set(a['node_evidence']['properties']),{'Common_Name'})
                elif q=='Which methods purify Copper?':
                    self.assertNotIn('node_evidence',a); self.assertEqual(a['status'],'success')
                elif q in ['What is Shodhana?','What is Panchakarma?']:
                    self.assertEqual(a['citations'],[]); self.assertEqual(a['evidence_records'],[]); self.assertIn('faq',a)
                else:
                    self.assertEqual(a['status'],'success'); self.assertTrue(a['node_evidence']['properties'])
        a=request(base,'What is its common name?',[],'json',context)
        b=request(base,'What is its common name?',[],'sse',context)
        self.assertEqual(a['node_evidence'],b['node_evidence']);self.assertEqual(a['node_evidence']['node_id'],'#5:6')
        self.assertEqual(set(a['node_evidence']['properties']),{'Common_Name'})
        output.append({'question':'What is its common name?','json':a,'sse':b})
        path=os.environ.get('NODE_PROPERTY_TEST_OUTPUT')
        if path: Path(path).write_text(json.dumps(output,indent=2,ensure_ascii=False),encoding='utf-8')

if __name__=='__main__':unittest.main()
