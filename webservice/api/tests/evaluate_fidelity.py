"""Offline evaluation of exported answers. No model calls; no safety guarantees.
Report coverage and failures separately. Entity mention recall is not factual correctness.
"""
import argparse
import csv
import json
import re
from collections import Counter
from pathlib import Path

ABSTENTIONS = {'unsupported', 'entity_not_found', 'needs_clarification', 'no_data'}
ERRORS = {'error', 'export_error', 'network_error', 'http_error', 'llm_error', 'grounding_error'}
ENTITY_FIELDS = {'name', 'label', 'method', 'process', 'media', 'compound', 'disease',
                 'therapy', 'formulation', 'symptom', 'related_entity', 'study',
                 'toxic_substance', 'indication', 'purified_compound'}


def extract_entities_from_context(context_str):
    entities = set()
    def visit(item):
        if isinstance(item, dict):
            for key, value in item.items():
                if key in ENTITY_FIELDS and isinstance(value, str) and value.strip():
                    entities.add(value.strip().lower())
                elif isinstance(value, (dict, list)):
                    visit(value)
        elif isinstance(item, list):
            for child in item:
                visit(child)
    try:
        data = json.loads(context_str)
        # Legacy exports included the orchestration envelope.
        visit(data.get('retrieval', data) if isinstance(data, dict) else data)
    except (ValueError, TypeError):
        pass
    return entities


def evaluate_row(row):
    status, expected = row.get('status', 'unknown'), row.get('expected_status')
    result = {'id': row.get('id', ''), 'question': row['question'], 'status': status,
              'expected_status': expected, 'outcome': 'unlabeled',
              'entity_mention_recall': '', 'entity_basis': '', 'routing_correct': '',
              'status_matches_expectation': status == row.get('expected_final_status', expected) if expected is not None else ''}
    if status in ERRORS or row.get('answer', '').startswith('Error generating'):
        result['outcome'] = 'execution_or_generation_failure'
    elif expected is not None:
        if status == expected:
            result['outcome'] = 'correct_abstention' if expected in ABSTENTIONS else 'expected_status'
        elif status in ABSTENTIONS and expected == 'success':
            result['outcome'] = 'false_refusal'
        else:
            result['outcome'] = 'unexpected_status'
    if 'expected_endpoint' in row:
        norm = lambda value: value.removesuffix('.php') if isinstance(value, str) else value
        result['routing_correct'] = norm(row.get('endpoint')) == norm(row['expected_endpoint'])
        if not result['routing_correct'] and result['outcome'] != 'execution_or_generation_failure':
            result['outcome'] = 'routing_failure'
    if status == 'success':
        entities = {e.strip().lower() for e in row.get('expected_entities', []) if isinstance(e, str) and e.strip()}
        result['entity_basis'] = 'annotated' if entities else 'retrieved_context_proxy'
        if not entities:
            # Parse each context independently; concatenated JSON is invalid JSON.
            for context in row.get('contexts', []):
                entities.update(extract_entities_from_context(context))
        if entities:
            answer = row.get('answer', '').lower()
            found = sum(bool(re.search(r'(?<!\w)' + re.escape(e) + r'(?!\w)', answer)) for e in entities)
            result['entity_mention_recall'] = found / len(entities)
    return result


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--input', type=Path, default=Path(__file__).parent / 'fixtures/ragas_dataset.json')
    parser.add_argument('--output', type=Path, default=Path(__file__).parent / 'fixtures/fidelity_results.csv')
    args = parser.parse_args()
    rows = json.loads(args.input.read_text(encoding='utf-8-sig'))
    results = [evaluate_row(row) for row in rows]
    with args.output.open('w', newline='', encoding='utf-8') as handle:
        fields = ['id', 'question', 'status', 'expected_status', 'outcome', 'entity_mention_recall', 'entity_basis', 'routing_correct', 'status_matches_expectation']
        writer = csv.DictWriter(handle, fieldnames=fields)
        writer.writeheader()
        writer.writerows(results)
    summary = {'total': len(results), 'outcomes': dict(Counter(r['outcome'] for r in results)),
               'entity_scored': sum(r['entity_mention_recall'] != '' for r in results),
               'note': 'Mention recall is a proxy; unlabeled refusals are not scored as correct.'}
    print(json.dumps(summary, indent=2))


if __name__ == '__main__':
    main()
