"""Check legacy attribution against frozen raw graph records, never endpoint-derived labels."""
import argparse
from collections import Counter
import hashlib
import json
from pathlib import Path
from reviewed_reference_oracle import identifiers

ROOT = Path(__file__).parent / 'fixtures'


def contains(actual, expected):
    if isinstance(expected, dict):
        return isinstance(actual, dict) and all(k in actual and contains(actual[k], v) for k, v in expected.items())
    if isinstance(expected, list):
        return isinstance(actual, list) and len(actual) == len(expected) and all(contains(a, e) for a, e in zip(actual, expected))
    return type(actual) is type(expected) and actual == expected


def evaluate(case, actual, records):
    issues = []
    rows = actual.get('results', [])
    expected = case['expected_results']
    if not expected and not rows and actual.get('status') == case['expected_status'] and actual.get('query_plan'):
        return []
    if expected and 'nodes' in expected[0]:
        # Canonical replacements retain the same independently specified edge paths.
        if actual.get('status') != case['expected_status'] or len(rows) != len(expected):
            issues.append('status/count mismatch')
        used = set()
        for row in rows:
            match = next((j for j, ref in enumerate(expected) if j not in used and contains(row, ref)), None)
            if match is None: issues.append('unsupported/duplicate canonical path')
            else: used.add(match)
            for edge in row.get('edges', []):
                raw = records.get(edge.get('id'), {})
                if edge.get('properties') != {k: v for k, v in raw.items() if not k.startswith('@')}: issues.append('canonical raw properties')
        return issues
    if actual.get('status') != case['expected_status'] or len(rows) != len(expected):
        issues.append('status/count mismatch')
    entries = actual.get('evidence_paths')
    if not isinstance(entries, list):
        return issues + ['missing evidence_paths']
    if Counter(e.get('result_index') for e in entries) != Counter(range(len(rows))):
        return issues + ['missing/duplicate/invalid result mapping']
    by_index = {e['result_index']: e['paths'] for e in entries}
    used = set()
    for i, row in enumerate(rows):
        match = next((j for j, ref in enumerate(expected) if j not in used and contains(row, ref)), None)
        if match is None:
            issues.append(f'row {i}: unsupported/duplicate row')
            continue
        used.add(match)
        wanted = {tuple(ids) for ids in case['expected_result_source_paths'][match]}
        found = []
        for path in by_index[i]:
            wanted_correlation = 'cross_study_association' if case['expected_endpoint'] == 'compound_therapeutic_use' else 'shared_study'
            if path.get('correlation') != wanted_correlation:
                issues.append(f'row {i}: wrong correlation contract')
            edges, nodes = path.get('edges', []), path.get('nodes', [])
            ids = tuple(e.get('id') for e in edges)
            primary = ids
            # Legacy validation selects one safety edge, independently checked below.
            safety = case['expected_endpoint'] == 'compound_validation' and edges and edges[-1].get('type') == 'CONFIRMS_SAFETY_OF'
            if safety:
                primary = ids[:-1]
            found.append(primary)
            if primary not in wanted:
                issues.append(f'row {i}: wrong supporting edge path')
            if len(nodes) != len(edges) + 1 or not edges:
                issues.append(f'row {i}: malformed path')
                continue
            if nodes[0].get('label') != case['expected_entity'] or nodes[0].get('type') != case['expected_entity_type']:
                issues.append(f'row {i}: wrong seed')
            for n in nodes:
                raw = records.get(n.get('id'), {})
                if n != {'id': raw.get('@rid'), 'type': raw.get('@type'), 'label': raw.get('name')}:
                    issues.append(f'row {i}: wrong node identity/type/label')
            for j, edge in enumerate(edges):
                raw = records.get(edge.get('id'), {})
                props = {k: v for k, v in raw.items() if not k.startswith('@')}
                for k, rk in [('id', '@rid'), ('type', '@type'), ('source', '@out'), ('target', '@in')]:
                    if edge.get(k) != raw.get(rk) or rk not in raw:
                        issues.append(f'row {i}: wrong edge {k}')
                if json.dumps(edge.get('properties'), sort_keys=True) != json.dumps(props, sort_keys=True):
                    issues.append(f'row {i}: missing/changed raw properties')
                expected_ids = identifiers(raw)
                if any(edge.get('evidence', {}).get(k, '') != v for k, v in expected_ids.items()):
                    issues.append(f'row {i}: wrong citation identifiers')
                if {edge.get('source'), edge.get('target')} != {nodes[j].get('id'), nodes[j + 1].get('id')}:
                    issues.append(f'row {i}: disconnected edge')
            if case['expected_endpoint'] == 'compound_validation':
                selected = row.get('safety_evidence')
                candidates = case['expected_safety_candidates'][match]
                if bool(candidates) != bool(safety) or bool(selected) != bool(safety):
                    issues.append(f'row {i}: missing/unexpected selected safety attribution')
                if safety:
                    raw = records.get(ids[-1], {})
                    ids_ref = identifiers(raw)
                    value = {'target_entity': nodes[-1]['label'], 'result': raw.get('Result') or raw.get('result') or '',
                             'properties': {k: ids_ref.get(k, '') for k in ('pubmed_id', 'doi', 'ayush_id')}}
                    if not any(contains(value, c) for c in candidates) or selected != value:
                        issues.append(f'row {i}: wrong selected safety edge')
        if set(found) != wanted:
            issues.append(f'row {i}: incomplete supporting paths')
        if len(found) != len(set(found)):
            issues.append(f'row {i}: duplicate supporting paths')
    return issues


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--answers', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--fixture', type=Path, default=ROOT / 'expanded_factual_cases_v3.json')
    args = parser.parse_args()
    if args.output.exists():
        parser.error('Use a new report path; preserve historical results')
    fixture = args.fixture
    snapshot = ROOT / 'factual_graph_snapshot_expanded.json'
    cases = json.loads(fixture.read_text(encoding='utf-8'))
    answers = json.loads(args.answers.read_text(encoding='utf-8'))
    if answers['fixture_sha256'] != hashlib.sha256(fixture.read_bytes()).hexdigest():
        raise ValueError('Answer/reference hash mismatch')
    if any(c['ground_truth_source']['sha256'] != hashlib.sha256(snapshot.read_bytes()).hexdigest() for c in cases):
        raise ValueError('Snapshot/reference hash mismatch')
    raw = json.loads(snapshot.read_text(encoding='utf-8'))
    records = {r['@rid']: r for table in raw['tables'].values() for r in table}
    legacy = {c['expected_endpoint'] for c in cases if c['expected_results'] and 'nodes' not in c['expected_results'][0]}
    legacy.update({'integrated_compound_disease', 'integrated_disease_compound'})
    captured = {c['id']: c['actual'] for c in answers['cases']}
    reports = []
    for case in cases:
        if case['expected_endpoint'] not in legacy:
            continue
        try:
            issues = evaluate(case, captured.get(case['id'], {}), records)
        except (KeyError, TypeError, ValueError, IndexError, AttributeError) as error:
            issues = ['malformed evidence response: ' + str(error)]
        reports.append({'id': case['id'], 'endpoint': case['expected_endpoint'], 'passed': not issues, 'issues': issues})
    summary = {'endpoints': len(legacy), 'cases': len(reports), 'passed': sum(r['passed'] for r in reports),
               'failed': sum(not r['passed'] for r in reports)}
    report = {'scope': 'Exact per-row edge attribution and complete raw properties against frozen KG; not scientific verification',
              'fixture_sha256': hashlib.sha256(fixture.read_bytes()).hexdigest(),
              'snapshot_sha256': hashlib.sha256(snapshot.read_bytes()).hexdigest(),
              'answers_sha256': hashlib.sha256(args.answers.read_bytes()).hexdigest(),
              'evaluator_sha256': hashlib.sha256(Path(__file__).read_bytes()).hexdigest(),
              'summary': summary, 'cases': reports}
    args.output.write_text(json.dumps(report, indent=2), encoding='utf-8')
    print(json.dumps(summary))
    for r in reports:
        if not r['passed']:
            print(r['id'], Counter(r['issues']).most_common(3))
    return bool(summary['failed'])


if __name__ == '__main__':
    raise SystemExit(main())
