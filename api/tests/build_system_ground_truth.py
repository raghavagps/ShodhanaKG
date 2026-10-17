"""Versioned contract migration from independent raw KG joins; never endpoint output."""
import copy
import json
from pathlib import Path
from evaluate_system_consistency import Oracle
from reviewed_reference_oracle import identifiers

ROOT = Path(__file__).parent / 'fixtures'

def canonical(oracle, nodes, edges):
    groups = []; anchor = None
    for i, edge in enumerate(edges):
        ref = oracle.ref(edge)
        if not i or ref != anchor:
            groups.append({'edge_ids': [], 'reference': {'type': ref[0], 'value': ref[1]} if ref else None})
            anchor = ref
        groups[-1]['edge_ids'].append(edge['@rid'])
    return {'nodes': [{'id': rid, 'type': oracle.records[rid]['@type'], 'label': oracle.records[rid]['name']} for rid in nodes],
        'edges': [{'id': e['@rid'], 'type': e['@type'], 'source': e['@out'], 'target': e['@in'], 'properties': {k: v for k, v in e.items() if not k.startswith('@')}, 'evidence': identifiers(e)} for e in edges],
        'related_entity': oracle.records[nodes[-1]]['name'], 'related_type': oracle.records[nodes[-1]]['@type'],
        'evidence_policy': 'separate_treatment', 'evidence_groups': groups}

def main():
    oracle = Oracle(json.loads((ROOT / 'factual_graph_snapshot_expanded.json').read_text(encoding='utf-8'))['tables'])
    cases = json.loads((ROOT / 'expanded_factual_cases_v3.json').read_text(encoding='utf-8'))
    for c in cases:
        if c['expected_endpoint'] not in ('integrated_compound_disease', 'integrated_disease_compound'): continue
        source = c['expected_entity_type']; target = 'Disease' if source == 'Compound' else 'Compound'
        paths = oracle.paths(source, c['expected_entity'], target)
        c['expected_results'] = [canonical(oracle, *paths[k]) for k in sorted(paths)]
        c['expected_result_source_paths'] = [[list(k)] for k in sorted(paths)]
        c['expected_result_count'] = len(paths)
        c['expected_status'] = 'success' if paths else 'no_data'
        c['forbidden_results'] = []
        c['ground_truth_source'] = dict(c['ground_truth_source'], basis='Independent bounded raw-record joins, five hops; separate references only at purified-product/treatment boundary', accepted_path_count=len(paths))
    routing = json.loads((ROOT / 'chatbot_evaluation_cases.json').read_text(encoding='utf-8'))
    for c in routing:
        if c['id'] == 'eval_007': c['expected_endpoint'] = 'compound_methods'
    presentation = []
    for filename in ['factual_evaluation_cases_v3.json', 'legacy_factual_evaluation_cases_v3.json']:
        presentation += json.loads((ROOT / filename).read_text(encoding='utf-8'))
    small = Oracle(json.loads((ROOT / 'factual_graph_snapshot.json').read_text(encoding='utf-8'))['tables'])
    for c in presentation:
        if c['expected_endpoint'] != 'compound_purification': continue
        paths = small.paths('Compound', c['expected_entity'], 'Shodhana_Method')
        c['expected_endpoint'] = 'compound_methods'
        c['expected_results'] = [canonical(small, *paths[k]) for k in sorted(paths)]
        c['expected_result_source_paths'] = [[list(k)] for k in sorted(paths)]
        c['expected_result_count'] = len(paths)
        c['forbidden_results'] = []
        c['ground_truth_source'] = dict(c['ground_truth_source'], basis='Independent raw UNDERGOES edges; requested result type is Shodhana_Method', accepted_path_count=len(paths))
    for filename, data in [('expanded_factual_cases_v4.json', cases), ('chatbot_evaluation_cases_v4.json', routing), ('presentation_cases_v4.json', presentation)]:
        if (ROOT / filename).exists():
            if json.loads((ROOT / filename).read_text(encoding='utf-8')) != data: raise ValueError('Refusing to alter an existing fixture: ' + filename)
            continue
        with (ROOT / filename).open('x', encoding='utf-8') as out: json.dump(data, out, indent=2, ensure_ascii=False)

if __name__ == '__main__': main()
