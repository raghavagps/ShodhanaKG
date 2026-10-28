"""Mutations must fail independently even when all flat answer fields remain correct."""
import copy
import json
import unittest
from evaluate_legacy_evidence import ROOT, evaluate


class EvidenceTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.cases = json.loads((ROOT / 'expanded_factual_cases_v3.json').read_text(encoding='utf-8'))
        report = json.loads((ROOT / 'legacy_evidence_benchmark.json').read_text(encoding='utf-8'))
        cls.actual = {r['id']: r['actual'] for r in report['cases']}
        raw = json.loads((ROOT / 'factual_graph_snapshot_expanded.json').read_text(encoding='utf-8'))
        cls.records = {r['@rid']: r for rows in raw['tables'].values() for r in rows}

    def sample(self, endpoint='compound_media'):
        case = next(c for c in self.cases if c['expected_endpoint'] == endpoint and c['expected_results'])
        return case, copy.deepcopy(self.actual[case['id']])

    def check_bad(self, case, actual):
        self.assertTrue(evaluate(case, actual, self.records))

    def test_all_legacy_cases(self):
        legacy = {c['expected_endpoint'] for c in self.cases if c['expected_results'] and 'nodes' not in c['expected_results'][0]}
        self.assertEqual(len(legacy), 40)
        for case in self.cases:
            if case['expected_endpoint'] in legacy:
                self.assertEqual(evaluate(case, self.actual[case['id']], self.records), [], case['id'])

    def test_missing_path_and_wrong_row_mapping(self):
        c, a = self.sample()
        a['evidence_paths'][0]['paths'].pop()
        self.check_bad(c, a)
        c, a = self.sample()
        a['evidence_paths'][0]['result_index'] = 9999
        self.check_bad(c, a)

    def test_reverse_direction(self):
        c, a = self.sample('process_compound')
        edge = a['evidence_paths'][0]['paths'][0]['edges'][0]
        edge['source'], edge['target'] = edge['target'], edge['source']
        self.check_bad(c, a)

    def test_replaced_edge_id_and_node_type(self):
        for field in ('id', 'type'):
            c, a = self.sample()
            a['evidence_paths'][0]['paths'][0]['edges'][0][field] = '#0:9999' if field == 'id' else 'TREATS'
            self.check_bad(c, a)
        c, a = self.sample()
        a['evidence_paths'][0]['paths'][0]['nodes'][1]['type'] = 'Process'
        self.check_bad(c, a)

    def test_dropped_and_invented_properties(self):
        for mutate in (lambda p: p.clear(), lambda p: p.update({'invented_outcome': 'cured'})):
            c, a = self.sample()
            mutate(a['evidence_paths'][0]['paths'][0]['edges'][0]['properties'])
            self.check_bad(c, a)

    def test_duplicate_path(self):
        c, a = self.sample()
        a['evidence_paths'][0]['paths'].append(copy.deepcopy(a['evidence_paths'][0]['paths'][0]))
        self.check_bad(c, a)

    def test_cross_study_substitution(self):
        c = max((c for c in self.cases if c['expected_endpoint'] == 'disease_symptoms'), key=lambda c: c['expected_result_count'])
        a = copy.deepcopy(self.actual[c['id']])
        paths = a['evidence_paths']
        self.assertGreater(len(paths), 1)
        paths[0]['paths'] = copy.deepcopy(paths[-1]['paths'])
        self.check_bad(c, a)

    def test_selected_safety_edge_is_required(self):
        c, a = self.sample('compound_validation')
        entry = next(e for e in a['evidence_paths'] if e['paths'][0]['edges'][-1]['type'] == 'CONFIRMS_SAFETY_OF')
        entry['paths'][0]['edges'].pop()
        entry['paths'][0]['nodes'].pop()
        self.check_bad(c, a)


if __name__ == '__main__':
    unittest.main()
