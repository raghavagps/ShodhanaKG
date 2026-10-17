"""Reviewer-mapped answer claims checked against independent frozen graph references.

No automatic prose-to-fact inference. Unreviewed answers never receive a factuality score.
"""
import argparse
from collections import Counter
import hashlib
import json
from pathlib import Path

ERRORS = {'error', 'export_error', 'network_error', 'http_error', 'llm_error', 'grounding_error'}


def digest(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()


def unique_rows(rows):
    result = {}
    if not isinstance(rows, list) or not rows:
        raise ValueError('Expected nonempty row list')
    for row in rows:
        if not isinstance(row, dict) or not isinstance(row.get('id'), str) or not row['id'] or row['id'] in result:
            raise ValueError('Missing or duplicate case ID')
        result[row['id']] = row
    return result


def review_packet(answer, reference):
    return {'id': answer['id'], 'answer_sha256': digest(answer), 'reference_sha256': digest(reference),
            'question': answer['question'], 'answer': answer['answer'],
            'review_status': 'pending', 'reviewer': '', 'reviewer_kind': 'unspecified', 'all_factual_claims_mapped': False,
            'claims': [], 'notes': ''}


def claim_verdict(claim, answer, reference):
    if not isinstance(claim, dict) or set(claim) - {'start', 'end', 'quote', 'kind', 'edges', 'paths', 'citation', 'properties', 'notes'}:
        raise ValueError('Invalid claim fields; use unresolved for negation or inference')
    start, end = claim.get('start'), claim.get('end')
    if type(start) is not int or type(end) is not int or not 0 <= start < end <= len(answer):
        raise ValueError('Claim needs valid character offsets')
    if answer[start:end] != claim.get('quote'):
        raise ValueError('Claim quote does not match answer span')
    if claim.get('kind') == 'unresolved':
        return 'needs_expert_review'
    if claim.get('kind') == 'shared_study':
        paths = claim.get('paths')
        if not isinstance(paths, list) or len(paths) < 2 or 'properties' in claim or 'edges' in claim:
            raise ValueError('Shared-study claim requires at least two complete paths')
        reference_sets = []
        for edges in paths:
            child = {k: v for k, v in claim.items() if k != 'paths'}
            child.update(kind='relationship', edges=edges)
            verdict = claim_verdict(child, answer, reference)
            if verdict != 'supported_by_graph':
                return verdict
            identifiers = set()
            for path in reference['expected_results']:
                if len(edges) == len(path['edges']) and all(all(actual.get(k) == v for k, v in edge.items()) for edge, actual in zip(edges, path['edges'])):
                    match = path.get('matched_evidence')
                    if match and all(e['evidence'].get(match['type']) == match['value'] for e in path['edges']) and (claim.get('citation') is None or claim['citation'] == match):
                        identifiers.add((match['type'], match['value']))
            reference_sets.append(identifiers)
        return 'supported_by_graph' if set.intersection(*reference_sets) else 'unsupported_shared_study'
    if claim.get('kind') != 'relationship':
        raise ValueError('Claim kind must be relationship or unresolved')
    # The reviewer maps positive assertions only. Negations, efficacy and inferences
    # belong in unresolved; absence from this reference never proves clinical falsity.
    edges = claim.get('edges')
    if not isinstance(edges, list) or not edges:
        raise ValueError('Relationship claim requires directed edges')
    for edge in edges:
        if not {'source', 'target', 'type'} <= set(edge) or set(edge) - {'source', 'target', 'type', 'id'} or not all(isinstance(v, str) and v for v in edge.values()):
            raise ValueError('Invalid claim edge')
    citation = claim.get('citation')
    if citation is not None and (not isinstance(citation, dict) or set(citation) != {'type', 'value'}
            or citation['type'] not in ('pubmed_id', 'doi', 'ayush_id') or not isinstance(citation['value'], str) or not citation['value']):
        raise ValueError('Invalid citation')
    properties = claim.get('properties', [])
    if not isinstance(properties, list):
        raise ValueError('Invalid claim properties')
    for prop in properties:
        if not isinstance(prop, dict) or set(prop) != {'edge_index', 'key', 'value'} or type(prop['edge_index']) is not int or not 0 <= prop['edge_index'] < len(edges) or not isinstance(prop['key'], str):
            raise ValueError('Invalid claim property')
    candidates = []
    for path in reference['expected_results']:
        actual = path['edges']
        for offset in range(len(actual) - len(edges) + 1):
            segment = actual[offset:offset + len(edges)]
            if all(all(a.get(k) == v for k, v in e.items()) for e, a in zip(edges, segment)):
                candidates.append(segment)
    if not candidates:
        return 'unsupported_relationship'
    if citation is not None:
        candidates = [segment for segment in candidates if all(e['evidence'].get(citation['type']) == citation['value'] for e in segment)]
        if not candidates:
            return 'unsupported_citation'
    if properties and not any(all(p['key'] in segment[p['edge_index']]['properties'] and segment[p['edge_index']]['properties'][p['key']] == p['value'] for p in properties) for segment in candidates):
        return 'unsupported_property'
    return 'supported_by_graph'


def evaluate(answer, reference, review=None):
    if answer['question'] != reference['question']:
        raise ValueError('Question differs from independent reference')
    status = answer.get('status', 'unknown')
    expected = reference.get('expected_final_status', reference['expected_status'])
    result = {'id': answer['id'], 'status': status, 'status_matches_expectation': status == expected,
              'routing_correct': answer.get('endpoint', '').removesuffix('.php') == reference['expected_endpoint'].removesuffix('.php') if isinstance(answer.get('endpoint'), str) else False,
              'outcome': 'pending_review', 'claims': [], 'supported_fraction': None}
    if status in ERRORS:
        result['outcome'] = 'execution_or_generation_failure'
        return result
    if status != 'success':
        result['outcome'] = 'false_refusal' if reference['expected_status'] == 'success' else 'abstention'
        return result
    if review is None or review.get('review_status') == 'pending':
        return result
    if review.get('id') != answer['id']:
        raise ValueError('Review case differs from answer')
    if review.get('review_status') != 'reviewed' or not isinstance(review.get('reviewer'), str) or not review['reviewer'].strip():
        raise ValueError('Completed review requires named reviewer')
    if review.get('answer_sha256') != digest(answer) or review.get('reference_sha256') != digest(reference):
        raise ValueError('Stale answer/reference review')
    result['reviewer'] = review['reviewer']
    result['reviewer_kind'] = review.get('reviewer_kind', 'unspecified')
    if review.get('answer_behavior') == 'refusal':
        result['outcome'] = 'false_refusal' if reference['expected_status'] == 'success' else 'reviewed_abstention'
        return result
    claims = review.get('claims')
    if not isinstance(claims, list) or not claims:
        raise ValueError('Reviewed success requires claims (use unresolved when unmappable)')
    verdicts = [claim_verdict(c, answer['answer'], reference) for c in claims]
    result['claims'] = verdicts
    complete = review.get('all_factual_claims_mapped') is True
    if not complete or 'needs_expert_review' in verdicts:
        result['outcome'] = 'incomplete_review'
    else:
        result['supported_fraction'] = verdicts.count('supported_by_graph') / len(verdicts)
        result['outcome'] = 'supported_by_graph' if all(v == 'supported_by_graph' for v in verdicts) else 'unsupported_claims'
    # A known unsupported assertion remains a failure even when another assertion
    # is unresolved. Keep the fraction unscored for the incomplete review.
    if any(v.startswith('unsupported_') for v in verdicts):
        result['outcome'] = 'unsupported_claims'
    if not result['routing_correct']:
        result['outcome'] = 'routing_failure'
    return result


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--answers', type=Path, required=True)
    parser.add_argument('--references', type=Path, default=Path(__file__).parent / 'fixtures/factual_evaluation_cases.json')
    parser.add_argument('--reviews', type=Path)
    parser.add_argument('--prepare', action='store_true')
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    # Never overwrite a source file or an existing review/report accidentally.
    if args.output.exists():
        raise ValueError('Output exists; select a new path')
    answers = unique_rows(json.loads(args.answers.read_text(encoding='utf-8-sig')))
    references = unique_rows(json.loads(args.references.read_text(encoding='utf-8-sig')))
    if set(answers) - set(references):
        raise ValueError('Answer case lacks independent reference')
    reviews = unique_rows(json.loads(args.reviews.read_text(encoding='utf-8-sig'))) if args.reviews else {}
    if set(reviews) - set(answers):
        raise ValueError('Review case lacks answer')
    if args.prepare:
        output = [review_packet(a, references[k]) for k, a in answers.items()]
    else:
        results = [evaluate(a, references[k], reviews.get(k)) for k, a in answers.items()]
        output = {'answer_count': len(answers), 'reference_count': len(references),
                  'missing_answer_ids': sorted(set(references) - set(answers)),
                  'outcomes': dict(Counter(r['outcome'] for r in results)),
                  'scored_answers': sum(r['supported_fraction'] is not None for r in results),
                  'note': 'Graph support conditional on reviewer claim mapping; inspect reviewer_kind. Not scientific truth or automatic prose validation.',
                  'results': results}
    args.output.write_text(json.dumps(output, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
    print(f'Wrote {args.output}')


if __name__ == '__main__':
    main()
