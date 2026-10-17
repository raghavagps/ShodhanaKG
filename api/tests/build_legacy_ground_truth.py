"""Independent legacy projections from frozen raw records, never endpoint output.

Flat rows are compared as complete sets with counts. Audit paths retain the raw
edge identities discarded by the legacy response contract. --write is explicit.
"""
import argparse
import hashlib
import json
from build_factual_ground_truth import ROOT, DEFINITIONS, PHASES, identifiers, reference_paths


DEFINITIONS_LEGACY = {
    'formulation_therapy': ('Formulation', 'Laghu Sutshekhar', [(('USES_FORMULATION',), '@in', ('Therapy',))]),
    'process_purified_compound': ('Shodhana_Method', 'Swedana', [(('PRODUCES',), '@out', ('Purified_Compound',))]),
    'toxic_substance_method': ('Toxic_Substance', 'Phorbol Ester Myristate', [(('DETOXIFIES',), '@in', ('Shodhana_Method',))]),
    'process_compound': ('Shodhana_Method', 'Swedana', [(('UNDERGOES',), '@in', ('Compound',))]),
    'process_media': ('Shodhana_Method', 'Swedana', [(('USES_MEDIA',), '@out', ('Media',))]),
    'therapy_disease': ('Therapy', 'Abhyanga', [(PHASES, '@in', ('Disease',))]),
    'compound_purification': ('Compound', 'Copper', [(('UNDERGOES',), '@out', ('Shodhana_Method',)), (('PRODUCES',), '@out', ('Purified_Compound',))]),
    'compound_media': DEFINITIONS['provenance_compound_method_media'],
    'compound_shodhana_process': DEFINITIONS['provenance_compound_method_media'],
    'media_compound': DEFINITIONS['provenance_media_method_compound'],
    'disease_formulation': DEFINITIONS['provenance_disease_therapy_formulation'],
    'formulation_disease': DEFINITIONS['provenance_formulation_therapy_disease'],
}
QUESTIONS = {
    'formulation_therapy': 'What therapies use Laghu Sutshekhar?',
    'process_purified_compound': 'What purified compounds does Swedana produce?',
    'toxic_substance_method': 'Which methods remove Phorbol Ester Myristate?',
    'process_compound': 'What compounds undergo Swedana?',
    'process_media': 'What media is used in Swedana?',
    'therapy_disease': 'What diseases is Abhyanga used for?',
    'compound_purification': 'What shodhana methods does Copper undergo?',
    'compound_media': 'What media is used for Copper?',
    'compound_shodhana_process': 'What is the complete shodhana process for Copper?',
    'media_compound': 'What compounds use Cow Milk as media?',
    'disease_formulation': 'What formulations treat Alopecia?',
    'formulation_disease': 'What diseases use Laghu Sutshekhar?',
}


def properties(edge, classify=identifiers):
    raw = edge['properties']
    return dict(classify(dict(raw, id=edge['id'])), **{field: str(raw.get(field.title()) or raw.get(field) or '')
                                  for field in ('procedure', 'result')})


def project(endpoint, path, classify=identifiers):
    nodes, edges = path['nodes'], path['edges']
    target = nodes[-1]['label']
    if endpoint in ('formulation_therapy', 'process_purified_compound', 'toxic_substance_method'):
        return {'related_entity': target, 'properties': classify(dict(edges[0]['properties'], id=edges[0]['id']))}
    if len(edges) == 1:
        props = properties(edges[0], classify)
        if endpoint == 'process_compound':
            return {'compound': target, 'properties': props}
        if endpoint == 'process_media':
            return {'method': nodes[0]['label'], 'media': target, 'properties': props}
        return {'disease': target, 'relationship': edges[0]['type'], 'properties': props}
    if endpoint == 'compound_purification':
        props = properties(edges[1], classify)
        props['procedure'] = properties(edges[0], classify)['procedure'] or props['procedure']
        return {'shodhana_method': nodes[1]['label'], 'purified_compound': target, 'properties': props}
    preferred_type = 'USES_FORMULATION' if endpoint in ('disease_formulation', 'formulation_disease') else 'USES_MEDIA'
    preferred = next(e for e in edges if e['type'] == preferred_type)
    other = next(e for e in edges if e['type'] != preferred_type)
    props = properties(preferred, classify)
    if preferred_type == 'USES_FORMULATION':
        props['phase'] = next(e['type'] for e in edges if e['type'] in PHASES)
        row = {'therapy': nodes[1]['label'], 'disease' if endpoint == 'formulation_disease' else 'formulation': target}
    else:
        for field in ('procedure', 'result'):
            props[field] = props[field] or properties(other, classify)[field]
        if endpoint != 'compound_media':
            for field in ('temperature', 'duration', 'heating_cycles', 'repetitions', 'quantity', 'sequence'):
                value = preferred['properties'].get(field)
                if value is None:
                    value = other['properties'].get(field)
                if value is not None and value != '':
                    props[field] = value
        row = {'method': nodes[1]['label'], 'compound' if endpoint == 'media_compound' else 'media': target}
    return dict(row, properties=props)


def key(row):
    return json.dumps(row, sort_keys=True, ensure_ascii=False)


def build(snapshot_bytes):
    snapshot = json.loads(snapshot_bytes)
    cases = []
    for endpoint, definition in DEFINITIONS_LEGACY.items():
        accepted, rejected = reference_paths(snapshot['tables'], definition)
        rows, audit = {}, {}
        for path in accepted:
            row_key = key(project(endpoint, path))
            rows[row_key] = project(endpoint, path)
            audit.setdefault(row_key, []).append([e['id'] for e in path['edges']])
        # A rejected path can project to the same row as a valid path. Such a row
        # cannot be forbidden under a flat contract that omits edge identity.
        forbidden = {key(project(endpoint, p)): project(endpoint, p) for p in rejected}
        forbidden = [forbidden[k] for k in sorted(forbidden) if k not in rows]
        if not rows:
            raise ValueError(f'No positive reference rows for {endpoint}')
        cases.append({
            'id': 'eval_legacy_factual_' + endpoint, 'question': QUESTIONS[endpoint],
            'expected_endpoint': endpoint, 'expected_status': 'success',
            'expected_entity': definition[1], 'expected_entity_type': definition[0],
            'expected_results': [rows[k] for k in sorted(rows)], 'expected_result_count': len(rows),
            'forbidden_results': forbidden[:3],
            'ground_truth_source': {'snapshot': 'factual_graph_snapshot.json',
                'sha256': hashlib.sha256(snapshot_bytes).hexdigest(), 'captured_at': snapshot['captured_at'],
                'basis': 'independent raw-record joins and documented legacy field projection', 'identifier_version': 2,
                'review_status': 'graph_verified_expert_review_pending',
                'accepted_path_count': len(accepted), 'rejected_candidate_count': len(rejected)},
            'expected_result_source_paths': [audit[k] for k in sorted(rows)],
            'notes': 'Exact flat row set; raw edge IDs are audit metadata, not asserted response fields. Scientific review pending.'})
    return cases


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--write', action='store_true')
    args = parser.parse_args()
    cases = build((ROOT / 'factual_graph_snapshot.json').read_bytes())
    target = ROOT / 'legacy_factual_evaluation_cases_v2.json'
    if args.write:
        target.write_text(json.dumps(cases, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
    elif json.loads(target.read_text(encoding='utf-8')) != cases:
        raise SystemExit('Reference mismatch: inspect labels; do not auto-refresh')
    print(f'PASS {len(cases)} cases, {sum(len(c["expected_results"]) for c in cases)} rows, '
          f'{sum(len(c["forbidden_results"]) for c in cases)} forbidden rows')
