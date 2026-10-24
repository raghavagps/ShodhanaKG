"""Focused HTTP transport/result-contract regressions; existing references stay immutable."""
import os
import unittest
from evaluate_conversations import request

class RemediationHttpTests(unittest.TestCase):
    def test_fixed_queries_and_context_parity(self):
        base = os.environ.get('SHODHANA_TEST_API_BASE', 'http://127.0.0.1:8081/api')
        contexts = {'json': None, 'sse': None}
        cases = [
            ('What are the therapeutic uses of Croton tiglium Linn.?', 'compound_therapeutic_use'),
            ('What diseases are treated by the purified compound from Copper?', 'integrated_compound_disease'),
            ('What methods and media are used to purify Copper?', 'compound_shodhana_process'),
            ('And for Lead?', 'compound_shodhana_process'),
            ('Give me the Shodhana procedure for Copper', 'compound_shodhana_process'),
            ('Show the stored property Shodhana procedure of Copper', 'node_property'),
            ('What methods are used to purify Sulphur?', 'compound_methods'),
        ]
        for question, endpoint in cases:
            responses = {}
            for transport in contexts:
                with self.subTest(question=question, transport=transport):
                    answer = request(base+'/chatbot_response.php', question, [], transport, contexts[transport])
                    self.assertNotIn(answer['status'], ['error','grounding_error'])
                    self.assertEqual(answer['context']['routing']['endpoint'], endpoint)
                    contexts[transport] = answer['conversation_context']
                    responses[transport] = answer
            for field in ['status','answer','answer_results','citations','conversation_context','query_plan']:
                self.assertEqual(responses['json'].get(field), responses['sse'].get(field), (question,field))
            if 'methods and media' in question or question=='And for Lead?':
                self.assertEqual(contexts['json']['requested_result_types'], ['Shodhana_Method','Media'])
                self.assertIn(' → ', responses['json']['answer_results'][0]['label'])

if __name__=='__main__': unittest.main()
