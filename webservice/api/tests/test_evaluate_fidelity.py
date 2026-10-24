import unittest
from evaluate_fidelity import evaluate_row, extract_entities_from_context


class FidelityTests(unittest.TestCase):
    def test_errors_and_false_refusals_are_not_correct_rejections(self):
        for status in ('error', 'grounding_error', 'llm_error', 'export_error'):
            self.assertEqual(evaluate_row({'question': 'q', 'status': status, 'expected_status': 'no_data'})['outcome'], 'execution_or_generation_failure')
        self.assertEqual(evaluate_row({'question': 'q', 'status': 'no_data', 'expected_status': 'success'})['outcome'], 'false_refusal')

    def test_unlabeled_refusal_is_not_scored(self):
        self.assertEqual(evaluate_row({'question': 'q', 'status': 'no_data'})['outcome'], 'unlabeled')

    def test_expected_failure_and_routing_mismatch(self):
        result = evaluate_row({'question': 'q', 'status': 'grounding_error', 'expected_status': 'success', 'expected_final_status': 'grounding_error'})
        self.assertTrue(result['status_matches_expectation'])
        self.assertEqual(result['outcome'], 'execution_or_generation_failure')
        result = evaluate_row({'question': 'q', 'status': 'no_data', 'expected_status': 'no_data', 'endpoint': 'wrong', 'expected_endpoint': 'right'})
        self.assertEqual(result['outcome'], 'routing_failure')

    def test_media_and_method_are_included(self):
        self.assertEqual(extract_entities_from_context('{"results":[{"method":"Swedana","media":"Cow Milk"}]}'), {'swedana', 'cow milk'})

    def test_contexts_parsed_separately(self):
        row = {'question': 'q', 'status': 'success', 'answer': 'Swedana', 'contexts': ['{"method":"Swedana"}', '{"media":"Cow Milk"}']}
        self.assertEqual(evaluate_row(row)['entity_mention_recall'], 0.5)


if __name__ == '__main__':
    unittest.main()
