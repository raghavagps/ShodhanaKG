"""Materialize the explicitly authored AI review decisions for this recorded run.

This is not an automatic prose claim extractor. Semantic decisions live in
claim_full_decisions.json; candidate paths only supply reference identifiers.
"""
import json
import re
from pathlib import Path
from evaluate_claims import review_packet

ROOT = Path(__file__).parent / 'fixtures'


def span(text, quote):
    start = text.index(quote)
    return {'start': start, 'end': start + len(quote), 'quote': quote}


def assemble():
    answers = json.loads((ROOT / 'claim_full_answers.json').read_text(encoding='utf-8'))
    references = {r['id']: r for r in json.loads((ROOT / 'factual_evaluation_cases.json').read_text(encoding='utf-8'))}
    decisions = json.loads((ROOT / 'claim_full_decisions.json').read_text(encoding='utf-8'))
    reviews, coverage = [], []
    for answer in answers:
        reference = references[answer['id']]
        decision = decisions[reference['expected_endpoint']]
        review = review_packet(answer, reference)
        review.update(review_status='reviewed', reviewer='Codex AI graph-evidence review', reviewer_kind='ai',
                      all_factual_claims_mapped=True,
                      notes='AI-assisted semantic mapping, not expert scientific certification. Repeated assertions are deduplicated. Existential paths are evidence witnesses, not assertions of an unstated intermediate method. ' + decision.get('notes', ''))
        if 'answer_behavior' in decision:
            review['answer_behavior'] = decision['answer_behavior']
        if 'failure' in decision:
            review.update(review_status='not_applicable', all_factual_claims_mapped=False, notes=decision['failure'])
        paths = []
        node_checks = []
        for label in decision['relations']:
            candidates = [p for p in reference['expected_results'] if p['related_entity'] == label]
            if 'via' in decision:
                candidates = [p for p in candidates if p['nodes'][1]['label'] == decision['via']]
            if 'citation' in decision:
                candidates = [p for p in candidates if p.get('matched_evidence') == decision['citation']]
            if not candidates:
                raise ValueError('Authored mapping has no reference witness: ' + label)
            edges = [{k: e[k] for k in ('source', 'target', 'type')} for e in candidates[0]['edges']]
            paths.append(edges)
            quote = label
            # Some answers cite a node RID alongside its label. Validate this
            # additional assertion against the chosen node; preserve the audit.
            tail = answer['answer'][answer['answer'].index(label) + len(label):]
            node_id = re.match(r' \((?:id: )?(#\d+:\d+)\)', tail)
            if node_id:
                if node_id[1] != candidates[0]['nodes'][-1]['id']:
                    raise ValueError('Wrong cited node ID: ' + label)
                quote += node_id[0]
                node_checks.append({'label': label, 'id': node_id[1], 'matches_reference': True})
            claim = {**span(answer['answer'], quote), 'kind': 'relationship', 'edges': edges,
                     'notes': 'Read in the context of the complete answer heading/question.'}
            if 'citation' in decision:
                claim['citation'] = decision['citation']
                claim['notes'] += ' Citation applied to all listed items by the final sentence.'
            review['claims'].append(claim)
        for rid in decision.get('record_ids', []):
            edge = next(e for p in reference['expected_results'] for e in p['edges'] if e['id'] == rid)
            quote = next(line for line in answer['answer'].splitlines() if line.startswith('* ' + rid + ':'))
            review['claims'].append({**span(answer['answer'], quote), 'kind': 'relationship',
                                     'edges': [{k: edge[k] for k in ('source', 'target', 'type', 'id')}]})
        if 'shared_study' in decision:
            review['claims'].append({**span(answer['answer'], decision['shared_study']), 'kind': 'shared_study', 'paths': paths})
        for quote, note in decision['unresolved']:
            review['claims'].append({**span(answer['answer'], quote), 'kind': 'unresolved', 'notes': note})
        review['node_identifier_checks'] = node_checks
        reviews.append(review)
        expected_labels = sorted({p['related_entity'] for p in reference['expected_results']})
        present = decision['relations']
        coverage.append({'id': answer['id'], 'named_reference_targets': present,
                         'expected_unique_targets': len(expected_labels), 'named_target_count': len(set(present)),
                         'missing_targets': sorted(set(expected_labels) - set(present)),
                         'target_coverage': len(set(present)) / len(expected_labels),
                         'note': 'AI-reviewed named target coverage, not claim recall, study coverage or scientific validity. Failures/refusals retain zero coverage.'})
    return reviews, coverage


if __name__ == '__main__':
    reviews, coverage = assemble()
    for name, content in [('claim_full_reviews.json', reviews), ('claim_full_coverage.json', coverage)]:
        path = ROOT / name
        if path.exists():
            raise SystemExit('Output already exists: ' + str(path))
        path.write_text(json.dumps(content, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
    print(f'Assembled {len(reviews)} explicit reviews; no model-based claim extraction')
