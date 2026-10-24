import copy
import unittest
from evaluate_claims import evaluate, review_packet, unique_rows


class ClaimEvaluationTests(unittest.TestCase):
    def setUp(self):
        # Independent synthetic oracle: two studies must never be merged.
        self.ref = {'id': 'a', 'question': 'q', 'expected_status': 'success', 'expected_endpoint': 'endpoint',
                    'expected_results': [{'edges': [
                        {'source': 'Copper', 'target': 'M', 'type': 'UNDERGOES', 'evidence': {'pubmed_id': '111'}, 'properties': {}},
                        {'source': 'M', 'target': 'Cow Milk', 'type': 'USES_MEDIA', 'evidence': {'pubmed_id': '111'}, 'properties': {'Duration': '3 Days'}}]},
                        {'edges': [
                        {'source': 'Zinc', 'target': 'M', 'type': 'UNDERGOES', 'evidence': {'pubmed_id': '222'}, 'properties': {}},
                        {'source': 'M', 'target': 'Goat Milk', 'type': 'USES_MEDIA', 'evidence': {'pubmed_id': '222'}, 'properties': {'Duration': '8 Days'}}]}]}
        self.answer = {'id': 'a', 'question': 'q', 'status': 'success', 'endpoint': 'endpoint', 'answer': 'Copper uses Cow Milk.'}
        self.review = review_packet(self.answer, self.ref)
        self.review.update(review_status='reviewed', reviewer='test reviewer', all_factual_claims_mapped=True,
                           claims=[{'start': 0, 'end': len(self.answer['answer']), 'quote': self.answer['answer'],
                                    'kind': 'relationship', 'edges': [
                                        {k: e[k] for k in ('source', 'target', 'type')} for e in self.ref['expected_results'][0]['edges']],
                                    'citation': {'type': 'pubmed_id', 'value': '111'}}])

    def verdict(self):
        return evaluate(self.answer, self.ref, self.review)

    def test_supported_and_pending(self):
        self.assertEqual(self.verdict()['supported_fraction'], 1)
        self.assertIsNone(evaluate(self.answer, self.ref)['supported_fraction'])

    def test_cross_study_join(self):
        self.review['claims'][0]['edges'][1]['target'] = 'Goat Milk'
        self.assertEqual(self.verdict()['claims'], ['unsupported_relationship'])

    def test_citation_from_other_study(self):
        self.review['claims'][0]['citation']['value'] = '222'
        self.assertEqual(self.verdict()['claims'], ['unsupported_citation'])

    def test_wrong_quantity_and_direction(self):
        self.review['claims'][0]['properties'] = [{'edge_index': 1, 'key': 'Duration', 'value': '8 Days'}]
        self.assertEqual(self.verdict()['claims'], ['unsupported_property'])
        self.review['claims'][0]['edges'][0].update(source='M', target='Copper')
        self.assertEqual(self.verdict()['claims'], ['unsupported_relationship'])

    def test_stale_review_and_fabricated_quote(self):
        self.answer['answer'] += ' Extra claim.'
        with self.assertRaises(ValueError):
            self.verdict()
        self.answer['answer'] = 'Copper uses Cow Milk.'
        self.review['claims'][0]['quote'] = 'invented'
        with self.assertRaises(ValueError):
            self.verdict()

    def test_unresolved_and_partial_are_unscored(self):
        self.review['all_factual_claims_mapped'] = False
        self.assertIsNone(self.verdict()['supported_fraction'])
        self.review['all_factual_claims_mapped'] = True
        self.review['claims'][0]['kind'] = 'unresolved'
        self.assertEqual(self.verdict()['outcome'], 'incomplete_review')

    def test_failure_refusal_and_expected_failure_separate(self):
        self.answer['status'] = 'no_data'
        self.assertEqual(self.verdict()['outcome'], 'false_refusal')
        self.answer['status'] = 'grounding_error'
        self.ref['expected_final_status'] = 'grounding_error'
        self.assertTrue(self.verdict()['status_matches_expectation'])
        self.assertEqual(self.verdict()['outcome'], 'execution_or_generation_failure')
        self.assertIsNone(self.verdict()['supported_fraction'])

    def test_duplicate_ids_rejected(self):
        with self.assertRaises(ValueError):
            unique_rows([self.answer, copy.deepcopy(self.answer)])

    def test_missing_property_is_not_explicit_null(self):
        self.review['claims'][0]['properties'] = [{'edge_index': 0, 'key': 'missing', 'value': None}]
        self.assertEqual(self.verdict()['claims'], ['unsupported_property'])

    def test_cited_graph_record_id_must_match(self):
        self.review['claims'][0]['edges'][0]['id'] = '#invented:0'
        self.assertEqual(self.verdict()['claims'], ['unsupported_relationship'])

    def test_individually_supported_paths_do_not_prove_shared_study(self):
        from evaluate_claims import claim_verdict
        claim = self.review['claims'][0].copy()
        claim.pop('edges')
        claim.pop('citation')
        claim['kind'] = 'shared_study'
        claim['paths'] = [[{k: e[k] for k in ('source', 'target', 'type')} for e in p['edges']] for p in self.ref['expected_results']]
        for p, value in zip(self.ref['expected_results'], ['111', '222']):
            p['matched_evidence'] = {'type': 'pubmed_id', 'value': value}
        self.assertEqual(claim_verdict(claim, self.answer['answer'], self.ref), 'unsupported_shared_study')
        self.ref['expected_results'][1]['matched_evidence']['value'] = '111'
        for edge in self.ref['expected_results'][1]['edges']:
            edge['evidence']['pubmed_id'] = '111'
        self.assertEqual(claim_verdict(claim, self.answer['answer'], self.ref), 'supported_by_graph')

    def test_reviewed_prose_refusal_overrides_success_status(self):
        self.answer['answer'] = 'No information is available.'
        self.review = review_packet(self.answer, self.ref)
        self.review.update(review_status='reviewed', reviewer='test reviewer', answer_behavior='refusal')
        result = self.verdict()
        self.assertEqual(result['outcome'], 'false_refusal')
        self.assertTrue(result['status_matches_expectation'])
        self.assertIsNone(result['supported_fraction'])

    def test_unresolved_claim_does_not_hide_known_failure(self):
        self.review['claims'][0]['citation']['value'] = 'wrong'
        unresolved = {k: self.review['claims'][0][k] for k in ('start', 'end', 'quote')}
        unresolved['kind'] = 'unresolved'
        self.review['claims'].append(unresolved)
        result = self.verdict()
        self.assertEqual(result['outcome'], 'unsupported_claims')
        self.assertIsNone(result['supported_fraction'])


if __name__ == '__main__':
    unittest.main()
