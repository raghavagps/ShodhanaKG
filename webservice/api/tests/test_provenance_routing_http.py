"""Live routing, transport and exact-edge checks against immutable KG references."""
import json
import os
from pathlib import Path
import unittest

from evaluate_conversations import request
from evaluate_results_presentation import load_references, reference, evaluate


class ProvenanceRoutingHttpTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        fixture = Path(__file__).parent / 'fixtures/factual_graph_snapshot_expanded.json'
        tables = json.loads(fixture.read_text(encoding='utf8'))['tables']
        cls.records = {r['@rid']: r for rows in tables.values() for r in rows}
        cls.responses = {}
        base = os.environ.get('SHODHANA_TEST_API_BASE', 'http://127.0.0.1:8081/api')
        for compound in ('Copper', 'Lead', 'Sulphur'):
            for evidence in (False, True):
                question = f'What media are used for {compound}' + (' with evidence?' if evidence else '?')
                for transport in ('json', 'sse'):
                    cls.responses[compound, evidence, transport] = request(
                        base + '/chatbot_response.php', question, [], transport)
        output = os.environ.get('PROVENANCE_TEST_OUTPUT')
        if output:
            with Path(output).open('x', encoding='utf8') as stream:
                json.dump([{'compound': k[0], 'evidence': k[1], 'transport': k[2], 'response': v}
                           for k, v in cls.responses.items()], stream, ensure_ascii=False, indent=2)

    @staticmethod
    def paths(response, evidence):
        retrieval = response['context']['retrieval']
        return retrieval['results'] if evidence else [
            p for group in retrieval['evidence_paths'] for p in group['paths']]

    def test_routes_parity_and_independent_edge_attribution(self):
        for compound in ('Copper', 'Lead', 'Sulphur'):
            path_sets = []
            for evidence in (False, True):
                with self.subTest(compound=compound, evidence=evidence):
                    response = self.responses[compound, evidence, 'json']
                    self.assertEqual(response['status'], 'success')
                    self.assertEqual(response['context']['routing']['endpoint'],
                                     'provenance_compound_method_media' if evidence else 'compound_media')
                    for field in ('status', 'answer', 'answer_results', 'citations', 'conversation_context', 'query_plan', 'context'):
                        self.assertEqual(response.get(field), self.responses[compound, evidence, 'sse'].get(field), field)
                    paths = self.paths(response, evidence)
                    self.assertTrue(paths)
                    path_sets.append({tuple(e['id'] for e in p['edges']) for p in paths})
                    edges = {e['id']: e for p in paths for e in p['edges']}
                    for edge in edges.values():
                        raw = self.records[edge['id']]
                        self.assertEqual((edge['type'], edge['source'], edge['target']),
                                         (raw['@type'], raw['@out'], raw['@in']))
                        self.assertEqual(edge['properties'], {k: v for k, v in raw.items() if not k.startswith('@')})
                    scopes = [s for c in response['citations'] for s in c['scopes']]
                    self.assertEqual({s['edge_id'] for s in scopes}, set(edges))
                    for scope in scopes:
                        edge = edges[scope['edge_id']]
                        self.assertEqual((scope['edge_type'], scope['source_id'], scope['target_id'], scope['properties']),
                                         (edge['type'], edge['source'], edge['target'], edge['properties']))
            self.assertEqual(path_sets[0], path_sets[1], compound)

    def test_frozen_copper_presentation_oracle(self):
        cases, tables, _ = load_references('presentation_cases_v5.json')
        case = next(c for c in cases if c['id'] == 'eval_traversal_provenance_compound_method_media')
        for transport in ('json', 'sse'):
            with self.subTest(transport=transport):
                report = evaluate(case, self.responses['Copper', True, transport], reference(case, tables))
                self.assertTrue(report['passed'], report)


if __name__ == '__main__':
    unittest.main()
