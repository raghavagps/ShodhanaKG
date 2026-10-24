"""Independent offline relational joins over a frozen raw-graph snapshot.

No production traversal catalog, resolver, correlator, or endpoint output is used.
Default --check verifies committed labels; --write explicitly creates candidate labels.
"""
import argparse
import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).parent / 'fixtures'
PHASES = ('TREATED_BY', 'PRE_TREATED_BY', 'POST_TREATED_BY')
# Hand-specified directed relationships: edge types, current side, target types.
DEFINITIONS = {
    'therapy_formulation': ('Therapy', 'Abhyanga', [(('USES_FORMULATION',), '@out', ('Formulation',))]),
    'media_process': ('Media', 'Cow Milk', [(('USES_MEDIA',), '@in', ('Shodhana_Method', 'Process'))]),
    'shodhana_method_process': ('Shodhana_Method', 'Bhavana', [(('FOLLOWED_BY',), '@out', ('Process',))]),
    'process_shodhana_method': ('Process', 'Marana', [(('FOLLOWED_BY',), '@in', ('Shodhana_Method',))]),
    'purified_compound_process': ('Purified_Compound', 'Shodhita Jaypala', [(('PRODUCES',), '@in', ('Shodhana_Method', 'Process'))]),
    'method_toxic_substance': ('Shodhana_Method', 'Swedana', [(('DETOXIFIES',), '@out', ('Toxic_Substance',))]),
    'process_next_process': ('Process', 'Marana', [(('FOLLOWED_BY',), '@out', ('Process',))]),
    'next_process_process': ('Process', 'Marana', [(('FOLLOWED_BY',), '@in', ('Process',))]),
    'provenance_compound_method_media': ('Compound', 'Copper', [(('UNDERGOES',), '@out', ('Shodhana_Method',)), (('USES_MEDIA',), '@out', ('Media',))]),
    'provenance_media_method_compound': ('Media', 'Cow Milk', [(('USES_MEDIA',), '@in', ('Shodhana_Method',)), (('UNDERGOES',), '@in', ('Compound',))]),
    'provenance_disease_therapy_formulation': ('Disease', 'Alopecia', [(PHASES, '@out', ('Therapy',)), (('USES_FORMULATION',), '@out', ('Formulation',))]),
    'provenance_formulation_therapy_disease': ('Formulation', 'Laghu Sutshekhar', [(('USES_FORMULATION',), '@in', ('Therapy',)), (PHASES, '@in', ('Disease',))]),
}


def identifiers(edge):
    # Version 2 historical specification; current evaluators use reviewed_reference_oracle.
    result = {'pubmed_id': '', 'doi': '', 'ayush_id': ''}
    unknown = []
    for field in ('doi', 'DOI', 'pubmedId', 'pubmed_id', 'Pubmed ID', 'ref'):
        value = str(edge.get(field) or '').strip()
        if not value:
            continue
        doi = re.sub(r'^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)', '', value, flags=re.I)
        if re.fullmatch(r'10\.[0-9]{4,9}/\S+', doi) or field in ('doi', 'DOI'):
            result['doi'] = result['doi'] or doi.lower()
            continue
        pmid = re.sub(r'^(?:https?://pubmed\.ncbi\.nlm\.nih\.gov/|PMID:\s*)', '', value, flags=re.I).rstrip('/')
        if re.fullmatch(r'[0-9]+', pmid):
            result['pubmed_id'] = result['pubmed_id'] or pmid
        elif value not in unknown:
            unknown.append(value)
    result['ayush_id'] = next((str(edge[a]) for a in ('ayush_id', 'Ayush_ID', 'ayushId') if edge.get(a)), '')
    if unknown:
        result['unclassified_reference'] = '; '.join(unknown)
    return result


def shared_reference(left, right):
    a, b = identifiers(left), identifiers(right)
    for key in ('pubmed_id', 'doi', 'ayush_id'):
        if a[key] and b[key]:
            return {'type': key, 'value': a[key]} if a[key] == b[key] else None
    return None


def reference_paths(tables, definition):
    kind, name, steps = definition
    nodes = {r['@rid']: r for rows in tables.values() for r in rows if r.get('@cat') == 'v'}
    seeds = [n for n in nodes.values() if n['@type'] == kind and n.get('name') == name]
    if len(seeds) != 1:
        raise ValueError(f'Expected one exact seed: {kind}/{name}')
    paths = [([seeds[0]], [])]
    for edge_types, side, target_types in steps:
        joined = []
        for vertices, edges in paths:
            for edge_type in edge_types:
                for edge in tables[edge_type]:
                    if edge['@type'] != edge_type or edge[side] != vertices[-1]['@rid']:
                        continue
                    target = nodes.get(edge['@in' if side == '@out' else '@out'])
                    if target and target['@type'] in target_types:
                        joined.append((vertices + [target], edges + [edge]))
        paths = joined
    accepted, rejected = {}, {}
    for vertices, edges in paths:
        key = tuple(e['@rid'] for e in edges)
        row = {'nodes': [{'id': n['@rid'], 'type': n['@type'], 'label': n['name']} for n in vertices],
               'edges': [{'id': e['@rid'], 'type': e['@type'], 'source': e['@out'], 'target': e['@in'],
                          'properties': {k: v for k, v in e.items() if not k.startswith('@')},
                          'evidence': identifiers(e)} for e in edges],
               'related_entity': vertices[-1]['name'], 'related_type': vertices[-1]['@type']}
        if len(edges) == 2:
            match = shared_reference(*edges)
            if match is None:
                rejected[key] = row
                continue
            row['matched_evidence'] = match
        for edge in edges:
            if edge['@type'] in PHASES:
                row['phase'] = edge['@type']
        accepted[key] = row
    return [accepted[k] for k in sorted(accepted)], [rejected[k] for k in sorted(rejected)]


def build(snapshot_bytes, cases):
    snapshot = json.loads(snapshot_bytes)
    source = {c['expected_endpoint'].removesuffix('.php'): c for c in cases if c.get('expected_endpoint') and c['id'].startswith('eval_traversal_')}
    result = []
    for endpoint, definition in DEFINITIONS.items():
        expected, rejected = reference_paths(snapshot['tables'], definition)
        if not expected:
            raise ValueError(f'No positive reference paths for {endpoint}')
        case = dict(source[endpoint])
        case.update(expected_entity=definition[1], expected_results=expected,
                    expected_result_count=len(expected), forbidden_results=rejected[:3],
                    ground_truth_source={'snapshot': 'factual_graph_snapshot.json',
                        'sha256': hashlib.sha256(snapshot_bytes).hexdigest(), 'captured_at': snapshot['captured_at'],
                        'basis': 'independent raw-record joins', 'identifier_version': 2, 'review_status': 'graph_verified_expert_review_pending',
                        'rejected_candidate_count': len(rejected)})
        case['notes'] = 'Complete expected path set for this frozen graph. Do not regenerate labels to silence failures.'
        result.append(case)
    return result


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--write', action='store_true', help='Explicitly replace candidate fixture; review its diff')
    args = parser.parse_args()
    expected = build((ROOT / 'factual_graph_snapshot.json').read_bytes(), json.loads((ROOT / 'chatbot_evaluation_cases.json').read_text()))
    target = ROOT / 'factual_evaluation_cases_v2.json'
    if args.write:
        target.write_text(json.dumps(expected, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
    elif json.loads(target.read_text(encoding='utf-8')) != expected:
        raise SystemExit('Reference mismatch: inspect snapshot/labels; do not auto-refresh')
    print(f'PASS {len(expected)} factual cases, {sum(len(c["expected_results"]) for c in expected)} expected paths, '
          f'{sum(len(c["forbidden_results"]) for c in expected)} forbidden paths')


if __name__ == '__main__':
    main()
