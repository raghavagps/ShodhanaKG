import unittest
from build_legacy_ground_truth import ROOT, build, project


class LegacyReferenceTests(unittest.TestCase):
    def test_aliases_are_preserved_without_changing_flat_contract(self):
        path = {'nodes': [{'label': 'Seed'}, {'label': 'Target'}],
                'edges': [{'id': 'synthetic:alias', 'properties': {'ref': '123', 'DOI': '10.test/example', 'Ayush_ID': 'A'}}]}
        self.assertEqual(project('formulation_therapy', path), {
            'related_entity': 'Target',
            'properties': {'pubmed_id': '123', 'doi': '10.test/example', 'ayush_id': 'A'}})

    def test_distinct_references_do_not_collapse_and_audit_edges_exist(self):
        import json
        raw = (ROOT / 'factual_graph_snapshot.json').read_bytes()
        cases = build(raw)
        case = next(c for c in cases if c['expected_endpoint'] == 'formulation_therapy')
        self.assertEqual({r['properties']['pubmed_id'] for r in case['expected_results']}, {'254496', '36604296'})
        edges = {r['@rid'] for rows in json.loads(raw)['tables'].values() for r in rows if r.get('@cat') == 'e'}
        for case in cases:
            self.assertEqual(len(case['expected_results']), len(case['expected_result_source_paths']))
            for paths in case['expected_result_source_paths']:
                self.assertTrue(paths)
                for path in paths:
                    self.assertTrue(set(path) <= edges)
            for forbidden in case['forbidden_results']:
                self.assertNotIn(forbidden, case['expected_results'])


if __name__ == '__main__':
    unittest.main()
