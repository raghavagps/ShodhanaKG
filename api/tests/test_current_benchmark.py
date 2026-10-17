import unittest
from run_current_benchmark import latency, namespace_findings, refusal_metrics, supplemental_summary


class BenchmarkTests(unittest.TestCase):
    def test_supplemental_counts_are_not_lost_for_flat_reports(self):
        self.assertEqual(supplemental_summary({'total': 23, 'passed': 22, 'checks': []}), {'total': 23, 'passed': 22})
        self.assertEqual(supplemental_summary({'summary': {'total': 30, 'passed': 30}}), {'total': 30, 'passed': 30})

    def test_latency_keeps_slow_failures_and_reports_missing(self):
        self.assertEqual(latency([{'latency_ms': 10}, {'latency_ms': 1000, 'passed': False}, {}]),
                         {'n': 2, 'missing': 1, 'p50_ms': 10, 'p95_ms': 1000, 'max_ms': 1000, 'mean_ms': 505.0})

    def test_errors_are_not_correct_refusals(self):
        rows = [
            {'transport': 'json', 'expectation': {'reference_id': 'a'}, 'response': {'status': 'no_data'}, 'passed': False, 'issues': ['false_refusal']},
            {'transport': 'json', 'expectation': {'reference_id': 'a'}, 'response': {'status': 'transport_error'}, 'passed': False, 'issues': ['infrastructure_or_generation_error']},
            {'transport': 'json', 'expectation': {'acceptable_statuses': ['unsupported']}, 'response': {'status': 'error'}, 'passed': False, 'issues': ['infrastructure_or_generation_error']},
            {'transport': 'json', 'expectation': {'acceptable_statuses': ['unsupported']}, 'response': {'status': 'unsupported'}, 'passed': True, 'issues': []}]
        result = refusal_metrics(rows, {'a': {'expected_status': 'success'}})['json']
        self.assertEqual(result['answerable_turns'], 2)
        self.assertEqual(result['false_refusals'], 1)
        self.assertEqual(result['answerable_non_success'], 2)
        self.assertEqual(result['correct_abstentions'], 1)
        self.assertEqual(result['infrastructure_or_generation_errors'], 2)

    def test_namespace_overlay_does_not_approve_numeric_pmids(self):
        rows = {'capture': [{'ref': '76448', 'ayush_id': '76448'}, {'scopes': [{'pubmed_id': '76448'}]}, {'pubmed_id': '39210691'}]}
        result = namespace_findings(rows, {'76448': {'kind': 'ayush_id'}})
        self.assertEqual(len(result), 1)
        self.assertEqual(result[0]['path'], '/1/scopes/0/pubmed_id')


if __name__ == '__main__':
    unittest.main()
