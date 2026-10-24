"""Live statistics against independently frozen raw KG edges (read-only)."""
import json, unittest, urllib.request, urllib.error
from pathlib import Path
from collections import Counter
BASE = 'http://127.0.0.1:8081/api/'
class StatisticsHttpTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tables = json.loads((Path(__file__).parent/'fixtures/factual_graph_snapshot_expanded.json').read_text(encoding='utf-8'))['tables']
        cls.nodes = {r['@rid']:r for rows in cls.tables.values() for r in rows if r.get('@cat')=='v'}
    def get(self, query):
        return json.load(urllib.request.urlopen(BASE+query, timeout=30))
    def test_separate_source_types_and_global_pagination(self):
        cases = [('PurifiedCompound','Purified_Compound','PRODUCES','processes','shodhanaMethods'),
                 ('Media','Media','USES_MEDIA','processes','shodhanaMethods'),
                 ('ConfirmationStudy','Confirmation_Study','VALIDATED_BY','processes','shodhanaMethods'),
                 ('Process','Process','FOLLOWED_BY','previousSteps','shodhanaMethods')]
        for category, kind, edge_type, process_key, method_key in cases:
            with self.subTest(category=category):
                counts = Counter((e['@in'],self.nodes[e['@out']]['@type']) for e in self.tables[edge_type])
                rows=self.get(f'category-stats.php?domain=compound&category={category}&limit=100&sort=total&dir=desc')['rows']
                nodes={str(n['id']):rid for rid,n in self.nodes.items() if n['@type']==kind}
                for row in rows:
                    rid=nodes[str(row['id'])]
                    self.assertEqual(row[process_key],counts[(rid,'Process')])
                    self.assertEqual(row[method_key],counts[(rid,'Shodhana_Method')])
                page=self.get(f'category-stats.php?domain=compound&category={category}&limit=2&offset=2&sort=total&dir=desc')['rows']
                self.assertEqual(page,rows[2:4])
                self.assertEqual([r['total'] for r in rows],sorted((r['total'] for r in rows),reverse=True))
    def test_invalid_question_returns_400_for_both_transports(self):
        for stream in (False,True):
            request=urllib.request.Request(BASE+'chatbot_response.php',data=json.dumps({'question':[],'stream':stream}).encode(),headers={'Content-Type':'application/json'})
            with self.assertRaises(urllib.error.HTTPError) as raised:
                urllib.request.urlopen(request,timeout=10)
            self.assertEqual(raised.exception.code,400)
            self.assertEqual(json.load(raised.exception)['status'],'error')
if __name__=='__main__':unittest.main()
