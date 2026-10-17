"""Hand-authored counterexamples, independent of the production formatter."""
import copy
import unittest
from evaluate_results_presentation import evaluate, meaningful


class ResultsEvaluationTests(unittest.TestCase):
    def setUp(self):
        self.case = {'id': 'synthetic', 'expected_endpoint': 'media_process', 'expected_entity': 'Test medium'}
        self.rows = []
        self.citations = []
        # Reverse traversal: stored edges go method -> media, never media -> method.
        for i, (target, pmid, duration) in enumerate([('Method A', '111', '3 days'), ('Method A', '222', '7 days'), ('Method B', '333', '9 days')]):
            nodes = [{'id': 'seed', 'label': 'Test medium', 'type': 'Media'}, {'id': target, 'label': target, 'type': 'Shodhana_Method'}]
            props = {'Pubmed ID': pmid, 'Duration': duration, 'zero': 0, 'observed': False}
            edge = {'id': f'e{i}', 'source': target, 'target': 'seed', 'type': 'USES_MEDIA', 'properties': props, 'evidence': {'pubmed_id': pmid, 'doi': '', 'ayush_id': ''}}
            self.rows.append({'nodes': nodes, 'edges': [edge], 'related_entity': target, 'related_type': 'Shodhana_Method'})
            self.citations.append({'id': str(i + 1), 'fact_ids': [f'f{i + 1}'], 'record_indices': [i], 'attribution': 'per_edge', 'scopes': [
                {'edge_id': f'e{i}', 'source_id': target, 'target_id': 'seed', 'edge_type': 'USES_MEDIA',
                 'relationship': 'Shodhana Method → Media', 'entities': [{'type': 'Shodhana Method', 'label': target}, {'type': 'Media', 'label': 'Test medium'}],
                 'properties': props, 'identifiers': {'pubmed_id': pmid}}]})
        self.oracle = {'rows': copy.deepcopy(self.rows), 'paths': [[copy.deepcopy(r)] for r in self.rows],
                       'targets': [copy.deepcopy(r['nodes'][-1]) for r in self.rows], 'canonical': True}
        self.answer = {'status': 'success', 'context': {'routing': {'endpoint': 'media_process'}, 'retrieval': {'entity': {'name': 'Test medium'}, 'results': copy.deepcopy(self.rows)}},
                       'answer_heading': 'Shodhana Methods associated with Test medium',
                       'answer_intro': 'The available records associate Test medium with the following shodhana methods:',
                       'answer_results': [
                           {'id': 'f1', 'label': 'Method A', 'citation_ids': ['1', '2'], 'fact_ids': ['f1', 'f2'], 'record_indices': [0, 1]},
                           {'id': 'f3', 'label': 'Method B', 'citation_ids': ['3'], 'fact_ids': ['f3'], 'record_indices': [2]}],
                       'citations': copy.deepcopy(self.citations), 'answer_blocks': [], 'generation': {'mode': 'deterministic'}}
        self.answer['answer'] = self.answer['answer_heading'] + '\n\n' + self.answer['answer_intro'] + '\n\n1. Method A [1, 2]\n2. Method B [3]'

    def test_positive_independent_example(self):
        self.assertTrue(evaluate(self.case, self.answer, self.oracle)['passed'])

    def test_mutations_are_detected(self):
        mutations = {
            'missing_entity': lambda a: a['answer_results'].pop(),
            'duplicate_entity': lambda a: a['answer_results'].append(copy.deepcopy(a['answer_results'][0])),
            'invented_label': lambda a: a['answer_results'][0].update(label='Invented method'),
            'omitted_support': lambda a: a['answer_results'][0]['citation_ids'].pop(),
            'duplicate_support': lambda a: a['answer_results'][0]['citation_ids'].append('1'),
            'wrong_target_support': lambda a: a['answer_results'][0]['citation_ids'].append('3'),
            'dangling_citation': lambda a: a['answer_results'][0]['citation_ids'].append('99'),
            'missing_card': lambda a: a['citations'].pop(),
            'duplicate_card_id': lambda a: a['citations'][1].update(id='1'),
            'wrong_record_pointer': lambda a: a['citations'][0].update(record_indices=[2]),
            'wrong_fact_id': lambda a: a['citations'][0].update(fact_ids=['f99']),
            'wrong_edge_id': lambda a: a['citations'][0]['scopes'][0].update(edge_id='e99'),
            'wrong_edge_type': lambda a: a['citations'][0]['scopes'][0].update(edge_type='PRODUCES'),
            'wrong_direction': lambda a: a['citations'][0]['scopes'][0].update(source_id='seed', target_id='Method A'),
            'wrong_semantics': lambda a: a['citations'][0]['scopes'][0].update(relationship='Media → Shodhana Method'),
            'wrong_endpoint_label': lambda a: a['citations'][0]['scopes'][0]['entities'][0].update(label='Method B'),
            'cross_study_reference': lambda a: a['citations'][0]['scopes'][0]['identifiers'].update(pubmed_id='222'),
            'invented_doi': lambda a: a['citations'][0]['scopes'][0]['identifiers'].update(doi='10.9999/fake'),
            'cross_study_property': lambda a: a['citations'][0]['scopes'][0]['properties'].update(Duration='7 days'),
            'missing_property': lambda a: a['citations'][0]['scopes'][0]['properties'].pop('Duration'),
            'changed_zero_type': lambda a: a['citations'][0]['scopes'][0]['properties'].update(zero=False),
            'extra_property': lambda a: a['citations'][0]['scopes'][0]['properties'].update(cure='guaranteed'),
            'extra_prose': lambda a: a.update(answer=a['answer'] + '\nThis cures disease.'),
            'clinical_intro': lambda a: a.update(answer_intro='These methods are proven safe.'),
            'false_refusal': lambda a: a.update(status='no_data'),
            'wrong_route': lambda a: a['context']['routing'].update(endpoint='wrong'),
            'wrong_seed': lambda a: a['context']['retrieval']['entity'].update(name='Another medium'),
            'duplicate_retrieval': lambda a: a['context']['retrieval']['results'].__setitem__(2, copy.deepcopy(a['context']['retrieval']['results'][0])),
            'missing_retrieval': lambda a: a['context']['retrieval']['results'].pop(),
        }
        for name, mutate in mutations.items():
            with self.subTest(name=name):
                answer = copy.deepcopy(self.answer)
                mutate(answer)
                self.assertFalse(evaluate(self.case, answer, self.oracle)['passed'], name)

    def test_self_consistent_wrong_answer_cannot_be_its_own_truth(self):
        a = copy.deepcopy(self.answer)
        a['context']['retrieval']['results'][0]['edges'][0]['properties']['Duration'] = '99 days'
        a['citations'][0]['scopes'][0]['properties']['Duration'] = '99 days'
        self.assertFalse(evaluate(self.case, a, self.oracle)['passed'])

    def test_record_order_is_not_ground_truth(self):
        # Reorder full evidence rows and update every pointer consistently.
        a = copy.deepcopy(self.answer)
        a['context']['retrieval']['results'][0:2] = reversed(a['context']['retrieval']['results'][0:2])
        a['citations'][0]['scopes'], a['citations'][1]['scopes'] = a['citations'][1]['scopes'], a['citations'][0]['scopes']
        self.assertTrue(evaluate(self.case, a, self.oracle)['passed'])

    def test_legacy_record_scope_and_losses(self):
        row = {'related_entity': 'Method A', 'properties': {'pubmed_id': '111', 'Duration': '3 days', 'zero': 0, 'observed': False}}
        oracle = {'canonical': False, 'rows': [row], 'paths': [[self.rows[0]]], 'targets': [self.rows[0]['nodes'][-1]]}
        a = copy.deepcopy(self.answer)
        a['context']['retrieval']['results'] = [row]
        a['answer_results'] = [{'id': 'f1', 'label': 'Method A', 'citation_ids': ['1'], 'fact_ids': ['f1'], 'record_indices': [0]}]
        a['answer'] = a['answer_heading'] + '\n\n' + a['answer_intro'] + '\n\n1. Method A [1]'
        a['citations'] = [{'id': '1', 'fact_ids': ['f1'], 'record_indices': [0], 'attribution': 'record',
                          'relationships': ['Shodhana Method → Media'], 'scopes': [{'properties': copy.deepcopy(row['properties']), 'identifiers': {'pubmed_id': '111'}, 'entities': [{'type': 'Shodhana Method', 'label': 'Method A'}], 'relationship': 'Shodhana Method → Media'}]}]
        self.assertTrue(evaluate(self.case, a, oracle)['passed'])
        for mutation in ('edge_id', 'relationships', 'zero'):
            bad = copy.deepcopy(a)
            if mutation == 'edge_id':
                bad['citations'][0]['scopes'][0]['edge_id'] = 'invented'
            elif mutation == 'relationships':
                bad['citations'][0]['relationships'] = []
            else:
                bad['citations'][0]['scopes'][0]['properties'].pop('zero')
            self.assertFalse(evaluate(self.case, bad, oracle)['passed'])

    def test_empty_fields_are_not_facts_but_zero_and_false_are(self):
        self.assertEqual(meaningful({'a': None, 'b': 'Not supplied', 'c': ' ', 'd': 0, 'e': False}), {'d': 0, 'e': False})


if __name__ == '__main__':
    unittest.main()
