import unittest
from build_factual_ground_truth import reference_paths, shared_reference, identifiers


class IndependentOracleTests(unittest.TestCase):
    def test_versioned_migration_preserves_raw_paths(self):
        import json
        from build_factual_ground_truth import ROOT
        for name in ('factual_evaluation_cases', 'legacy_factual_evaluation_cases'):
            old = json.loads((ROOT / (name + '.json')).read_text(encoding='utf-8'))
            new = json.loads((ROOT / (name + '_v2.json')).read_text(encoding='utf-8'))
            self.assertEqual([c['id'] for c in old], [c['id'] for c in new])
            for before, after in zip(old, new):
                self.assertEqual(before['ground_truth_source']['sha256'], after['ground_truth_source']['sha256'])
                self.assertEqual(before['expected_result_count'], after['expected_result_count'])
                if 'expected_result_source_paths' in before:
                    flatten = lambda c: sorted(tuple(p) for paths in c['expected_result_source_paths'] for p in paths)
                    self.assertEqual(flatten(before), flatten(after))
                else:
                    for field in ('expected_results', 'forbidden_results'):
                        self.assertEqual(len(before[field]), len(after[field]))
                        for a, b in zip(before[field], after[field]):
                            self.assertEqual(a['nodes'], b['nodes'])
                            for x, y in zip(a['edges'], b['edges']):
                                self.assertEqual({k: v for k, v in x.items() if k != 'evidence'},
                                                 {k: v for k, v in y.items() if k != 'evidence'})

    def test_identifier_v2_classification(self):
        self.assertEqual(identifiers({'ref': ' https://doi.org/10.4103/ASL.ASL_89_24 '}),
                         {'pubmed_id': '', 'doi': '10.4103/asl.asl_89_24', 'ayush_id': ''})
        self.assertEqual(shared_reference({'ref': '10.4103/example'}, {'DOI': 'doi:10.4103/EXAMPLE'}),
                         {'type': 'doi', 'value': '10.4103/example'})
        self.assertIsNone(shared_reference({'ref': 'unknown'}, {'ref': 'unknown'}))
        self.assertEqual(identifiers({'ref': 'unknown'})['unclassified_reference'], 'unknown')

    def test_reference_precedence_and_aliases(self):
        self.assertEqual(shared_reference({'Pubmed ID': '123'}, {'ref': '123'}), {'type': 'pubmed_id', 'value': '123'})
        self.assertIsNone(shared_reference({'ref': '123', 'doi': 'same'}, {'ref': '456', 'doi': 'same'}))
        self.assertIsNone(shared_reference({}, {}))
        self.assertEqual(shared_reference({'DOI': 'x'}, {'doi': 'x'}), {'type': 'doi', 'value': 'x'})

    def test_independent_join_excludes_cross_study_and_wrong_direction(self):
        nodes = [{'@rid': rid, '@type': kind, '@cat': 'v', 'name': name} for rid, kind, name in
                 [('#1:0', 'Compound', 'C'), ('#2:0', 'Shodhana_Method', 'M'), ('#3:0', 'Media', 'Milk')]]
        tables = {'nodes': nodes, 'UNDERGOES': [
            {'@rid': '#4:0', '@type': 'UNDERGOES', '@out': '#1:0', '@in': '#2:0', 'ref': '123'}],
            'USES_MEDIA': [
                {'@rid': '#5:0', '@type': 'USES_MEDIA', '@out': '#2:0', '@in': '#3:0', 'ref': '123'},
                {'@rid': '#5:1', '@type': 'USES_MEDIA', '@out': '#2:0', '@in': '#3:0', 'ref': '456'},
                {'@rid': '#5:2', '@type': 'USES_MEDIA', '@out': '#3:0', '@in': '#2:0', 'ref': '123'}]}
        good, bad = reference_paths(tables, ('Compound', 'C', [(('UNDERGOES',), '@out', ('Shodhana_Method',)), (('USES_MEDIA',), '@out', ('Media',))]))
        self.assertEqual(len(good), 1)
        self.assertEqual(len(bad), 1)
        self.assertEqual(good[0]['edges'][1]['id'], '#5:0')
        self.assertEqual(bad[0]['edges'][1]['id'], '#5:1')
        tables['nodes'][2]['@type'] = 'Disease'
        self.assertEqual(reference_paths(tables, ('Compound', 'C', [(('UNDERGOES',), '@out', ('Shodhana_Method',)), (('USES_MEDIA',), '@out', ('Media',))])), ([], []))


if __name__ == '__main__':
    unittest.main()
