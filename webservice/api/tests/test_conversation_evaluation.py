import copy
import json
import unittest
from evaluate_conversations import FIXTURES, parse_sse, score, ui_history, semantic_payload


class ConversationEvaluationTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.refs = {c['id']: c for c in json.loads((FIXTURES / 'expanded_factual_cases_v4.json').read_text(encoding='utf-8'))}
        tables = json.loads((FIXTURES / 'factual_graph_snapshot_expanded.json').read_text(encoding='utf-8'))['tables']
        cls.records = {r['@rid']: r for rows in tables.values() for r in rows}
        cls.sample = json.loads((FIXTURES / 'conversation_sample.json').read_text(encoding='utf-8'))

    def evaluate(self, response):
        return score(self.sample['expectation'], response, self.refs, self.records)

    def test_positive_is_factually_checked(self):
        response = copy.deepcopy(self.sample['response'])
        self.assertEqual(self.evaluate(response), [])
        response['context']['retrieval']['results'][0]['symptom'] = 'Invented symptom'
        self.assertIn('factual_or_attribution_mismatch', self.evaluate(response))

    def test_semantic_parity_excludes_only_attempt_diagnostics(self):
        left = copy.deepcopy(self.sample['response'])
        right = copy.deepcopy(left)
        left['context']['understanding'] = {'mode': 'model_fallback', 'result': 'unavailable'}
        right['context']['understanding'] = {'mode': 'model_fallback', 'result': 'rejected'}
        self.assertEqual(semantic_payload(left), semantic_payload(right))
        self.assertIn('understanding', left['context'])
        for field, value in [('routing', {'entity': 'Invented'}), ('reason', 'changed'), ('retrieval', {'results': []})]:
            changed = copy.deepcopy(right)
            changed['context'][field] = value
            self.assertNotEqual(semantic_payload(left), semantic_payload(changed))
        changed = copy.deepcopy(right); changed['conversation_context'] = {'version': 2, 'subject': {'name': 'Invented'}}
        self.assertNotEqual(semantic_payload(left), semantic_payload(changed))

    def test_false_refusal_and_infrastructure_are_distinct(self):
        for status in ('unsupported', 'entity_not_found', 'no_data', 'needs_clarification'):
            self.assertIn('false_refusal', self.evaluate({'status': status}))
        self.assertEqual(self.evaluate({'status': 'transport_error'}), ['infrastructure_or_generation_error'])

    def test_success_on_wrong_topic_fails(self):
        response = copy.deepcopy(self.sample['response'])
        response['context']['routing']['entity'] = 'Copper'
        self.assertIn('wrong_entity', self.evaluate(response))

    def test_incomplete_display_or_misattributed_scope_fails(self):
        response = copy.deepcopy(self.sample['response'])
        response['answer_results'].pop()
        self.assertIn('incomplete_result_access', self.evaluate(response))
        response = copy.deepcopy(self.sample['response'])
        response['citations'][0]['scopes'][0]['source_id'] = '#0:999'
        self.assertIn('changed_edge_scope', self.evaluate(response))

    def test_unsupported_must_not_include_facts(self):
        turn = {'acceptable_statuses': ['unsupported']}
        self.assertIn('false_acceptance', score(turn, self.sample['response'], self.refs, self.records))
        self.assertEqual(score(turn, {'status': 'unsupported', 'answer': 'Outside supported queries.'}, self.refs, self.records), [])

    def test_actual_ui_history_filter_and_window(self):
        messages = [{'role': 'user', 'text': str(i)} for i in range(7)]
        messages += [{'role': 'bot', 'status': 'needs_clarification', 'text': 'Clarify'},
                     {'role': 'bot', 'status': 'success', 'text': 'Actual answer'}]
        history = ui_history(messages)
        self.assertEqual(len(history), 9)
        self.assertEqual(history[0]['text'], '0')
        self.assertEqual(history[-1], {'role': 'bot', 'text': 'Actual answer'})
        self.assertIn('Clarify', [m['text'] for m in history])
        self.assertEqual(len(ui_history(messages * 4)), 20)

    def test_sse_requires_terminal_event_and_matching_text(self):
        stream = 'event: context\ndata: {"status":"success"}\n\ndata: {"chunk":"Hello"}\n\nevent: done\ndata: {"status":"success","answer":"Hello"}\n\n'
        self.assertEqual(parse_sse(stream)['context']['status'], 'success')
        with self.assertRaises(ValueError):
            parse_sse('data: {"chunk":"Hello"}\n\n')
        with self.assertRaises(ValueError):
            parse_sse(stream.replace('"answer":"Hello"', '"answer":"Different"'))

    def test_fixture_spans_requested_behaviors(self):
        suite = json.loads((FIXTURES / 'conversation_evaluation_cases_v1.json').read_text(encoding='utf-8'))
        categories = {t['category'] for s in suite['scenarios'] for t in s['turns']}
        self.assertTrue({'paraphrase', 'typo_or_formatting', 'ambiguous_name', 'pronoun_followup',
                         'elliptical_followup', 'topic_change', 'unsupported_request', 'true_no_data', 'recovery'} <= categories)


if __name__ == '__main__':
    unittest.main()
