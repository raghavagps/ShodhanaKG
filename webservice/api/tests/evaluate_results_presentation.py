"""Independent results/citation evaluation against frozen graph references.

No production renderer, traversal metadata, or live retrieval is an oracle.
Capture files and reports are write-once. Scientific truth is outside this score.
"""
import argparse
from collections import Counter
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import re
import time
import urllib.request

from reviewed_reference_oracle import ROOT, identifiers, reference_paths
from build_legacy_ground_truth import DEFINITIONS_LEGACY, project


def key(value):
    return json.dumps(value, sort_keys=True, ensure_ascii=False)


def meaningful(value):
    if value is None or (isinstance(value, str) and value.strip().lower() in ('', 'null', 'not supplied')):
        return None
    if isinstance(value, dict):
        return {k: v for k, item in value.items() if (v := meaningful(item)) is not None} or None
    if isinstance(value, list):
        return [v for item in value if (v := meaningful(item)) is not None] or None
    return value


def subset(expected, actual):
    if isinstance(expected, dict):
        return isinstance(actual, dict) and all(k in actual and subset(v, actual[k]) for k, v in expected.items())
    if isinstance(expected, list):
        return isinstance(actual, list) and len(expected) == len(actual) and all(subset(a, b) for a, b in zip(expected, actual))
    return type(expected) is type(actual) and expected == actual


def human(kind):
    return kind.replace('_', ' ')


def relation(path, edge):
    nodes = {n['id']: n for n in path['nodes']}
    return human(nodes[edge['source']]['type']) + ' → ' + human(nodes[edge['target']]['type'])


def reference(case, tables):
    rows = case['expected_results']
    if 'nodes' in rows[0]:
        paths = [[r] for r in rows]
        canonical = True
    else:
        canonical = False
        accepted, _ = reference_paths(tables, DEFINITIONS_LEGACY[case['expected_endpoint']])
        grouped = {}
        for path in accepted:
            grouped.setdefault(key(project(case['expected_endpoint'], path, classify=identifiers)), []).append(path)
        paths = [grouped[key(r)] for r in rows]
        for i, candidates in enumerate(paths):
            assert Counter(key([e['id'] for e in p['edges']]) for p in candidates) == Counter(
                key(p) for p in case['expected_result_source_paths'][i]), 'Legacy audit paths differ from snapshot'
    targets = []
    for candidates in paths:
        # A lossy flat row must not silently pick one of several target identities.
        unique = {key(p['nodes'][-1]): p['nodes'][-1] for p in candidates}
        if len(unique) != 1:
            raise ValueError('Ambiguous reference target identity')
        targets.append(next(iter(unique.values())))
    return {'rows': rows, 'paths': paths, 'targets': targets, 'canonical': canonical}


def evaluate(case, response, oracle):
    issues = []
    def require(ok, category, detail):
        if not ok:
            issues.append({'category': category, 'detail': detail})
    context = response.get('context') or {}
    retrieval = context.get('retrieval') or {}
    require(response.get('status') == 'success', 'status', str(response.get('status')))
    require((context.get('routing') or {}).get('endpoint', '').removesuffix('.php') == case['expected_endpoint'], 'routing', 'Wrong endpoint')
    require((retrieval.get('entity') or {}).get('name') == case['expected_entity'], 'retrieval', 'Wrong seed')
    actual_rows = retrieval.get('results') or []
    expected_rows = oracle['rows']
    # Match against independent reference rows, not live row order.
    unmatched = set(range(len(expected_rows)))
    mapping = {}
    for i, row in enumerate(actual_rows):
        matches = [j for j in unmatched if subset(expected_rows[j], row)]
        if len(matches) == 1:
            mapping[i] = matches[0]
            unmatched.remove(matches[0])
        else:
            require(False, 'retrieval', f'Row {i}: unexpected, duplicate or ambiguous')
    require(not unmatched, 'retrieval', f'{len(unmatched)} reference rows missing')
    require(len(actual_rows) == len(expected_rows), 'retrieval', 'Evidence count differs')
    citations = response.get('citations') or []
    by_id = {c.get('id'): c for c in citations}
    require(len(by_id) == len(citations), 'citation_mapping', 'Duplicate citation IDs')
    require(len(citations) == len(expected_rows), 'citation_mapping', 'Missing or extra citations')
    all_indices = []
    for citation in citations:
        indices = citation.get('record_indices', [])
        all_indices.extend(indices)
        if len(indices) != 1 or indices[0] not in mapping:
            require(False, 'citation_mapping', f'Citation {citation.get("id")}: invalid record pointer')
            continue
        i = indices[0]
        j = mapping[i]
        require(citation.get('fact_ids') == [f'f{i + 1}'], 'citation_mapping', f'Citation {citation.get("id")}: fact ID mismatch')
        scopes = citation.get('scopes') or []
        paths = oracle['paths'][j]
        path = paths[0]
        prefix = f'Citation {citation.get("id")}'
        if oracle['canonical'] or citation.get('attribution') == 'per_edge':
            require(citation.get('attribution') == 'per_edge', 'attribution', prefix)
            # Canonical rows and enriched legacy rows both use independently
            # joined raw edges, never the live evidence_paths as the oracle.
            supported = {e['id']: (p, e) for p in paths for e in p['edges']}
            edges = [e for p, e in supported.values()]
            require(len(scopes) == len(edges), 'properties', prefix + ': missing/extra edge scope')
            require(Counter(s.get('edge_id') for s in scopes) == Counter(supported.keys()), 'attribution', prefix + ': missing/extra/duplicate edge IDs')
            for scope in scopes:
                if scope.get('edge_id') not in supported:
                    continue
                path, edge = supported[scope['edge_id']]
                require(all(scope.get(k) == edge[v] for k, v in [('edge_id', 'id'), ('edge_type', 'type'), ('source_id', 'source'), ('target_id', 'target')]), 'attribution', prefix + ': edge identity/direction')
                require(scope.get('relationship') == relation(path, edge), 'relationships', prefix + ': semantic label')
                nodes = {n['id']: n for n in path['nodes']}
                expected_entities = [{'type': human(nodes[r]['type']), 'label': nodes[r]['label']} for r in (edge['source'], edge['target'])]
                require(scope.get('entities') == expected_entities, 'entities', prefix + ': edge endpoint labels')
                require(key(scope.get('properties')) == key(edge['properties']), 'properties', prefix + ': raw edge properties differ')
                expected_ids = identifiers(dict(edge['properties'], id=edge['id']))
                require(meaningful({k: scope.get('identifiers', {}).get(k) for k in expected_ids}) == meaningful(expected_ids), 'references', prefix + ': reference mismatch')
                require(not any(meaningful(v) is not None for k, v in scope.get('identifiers', {}).items() if k in ('pubmed_id', 'doi', 'ayush_id', 'unclassified_reference') and k not in expected_ids), 'references', prefix + ': invented reference')
        else:
            require(citation.get('attribution') == 'record', 'attribution', prefix + ': fabricated per-edge precision')
            expected_props = expected_rows[j]['properties']
            require(len(scopes) == 1, 'properties', prefix + ': expected one projected record scope')
            wanted_relations = list(dict.fromkeys(relation(path, e) for e in path['edges']))
            require(citation.get('relationships') == wanted_relations, 'relationships', prefix + ': incomplete/wrong directed relationships')
            for scope in scopes:
                require(not any(scope.get(k) for k in ('edge_id', 'edge_type', 'source_id', 'target_id')), 'attribution', prefix + ': invented flat edge metadata')
                require(key(meaningful(scope.get('properties'))) == key(meaningful(expected_props)), 'properties', prefix + ': projected properties differ')
                expected_ids = identifiers(expected_props)
                actual_ids = {k: scope.get('identifiers', {}).get(k) for k in ('pubmed_id', 'doi', 'ayush_id', 'unclassified_reference')}
                require(meaningful(actual_ids) == meaningful(expected_ids), 'references', prefix + ': reference mismatch')
                if scope.get('relationship'):
                    require(len(wanted_relations) == 1 and scope['relationship'] == wanted_relations[0], 'relationships', prefix + ': misleading scope relationship')
                labels = [v for k, v in expected_rows[j].items() if k not in ('properties', 'relationship') and isinstance(v, str)]
                wanted_entities = Counter((human(n['type']), n['label']) for n in path['nodes'] if n['label'] in labels)
                require(Counter((e.get('type'), e.get('label')) for e in scope.get('entities', [])) == wanted_entities, 'entities', prefix + ': record entity labels')
    require(Counter(all_indices) == Counter(range(len(actual_rows))), 'citation_mapping', 'Every evidence record must have exactly one citation')
    results = response.get('answer_results') or []
    result_targets = []
    linked_ids = []
    for n, result in enumerate(results):
        ids = result.get('citation_ids') or []
        linked_ids.extend(ids)
        indices = []
        for cid in ids:
            require(cid in by_id, 'citation_mapping', f'Result {n}: dangling citation {cid}')
            indices.extend(by_id.get(cid, {}).get('record_indices', []))
        require(result.get('record_indices') == indices, 'citation_mapping', f'Result {n}: indices disagree with citations')
        require(result.get('fact_ids') == [f'f{i + 1}' for i in indices], 'citation_mapping', f'Result {n}: fact IDs disagree')
        targets = {key(oracle['targets'][mapping[i]]): oracle['targets'][mapping[i]] for i in indices if i in mapping}
        require(len(targets) == 1, 'grouping', f'Result {n}: missing/mixed target identities')
        if len(targets) == 1:
            target_key, target = next(iter(targets.items()))
            result_targets.append(target_key)
            require(result.get('label') == target['label'], 'entities', f'Result {n}: incorrect entity label')
    expected_targets = set(key(t) for t in oracle['targets'])
    require(Counter(result_targets) == Counter(expected_targets), 'grouping', 'Missing/extra/duplicate entity groups')
    require(Counter(linked_ids) == Counter(list(by_id)), 'citation_mapping', 'Every citation must be linked exactly once')
    require(len({r.get('id') for r in results}) == len(results), 'grouping', 'Duplicate result IDs')
    # This is a constrained template check, not an LLM judgement of free prose.
    kinds = {human(t['type']) for t in oracle['targets']}
    kind = next(iter(kinds)) if len(kinds) == 1 else 'Result'
    plural = {'Therapy': 'Therapies', 'Process': 'Processes', 'Media': 'Media'}.get(kind, kind + 's')
    heading = plural + ' associated with ' + case['expected_entity']
    intro = 'The available records associate ' + case['expected_entity'] + ' with the following ' + plural.lower() + ':'
    require(response.get('answer_heading') == heading and response.get('answer_intro') == intro, 'introduction', 'Heading/introduction differs from neutral result template')
    expected_text = heading + '\n\n' + intro + '\n\n' + '\n'.join(f'{i + 1}. {r.get("label")} [{", ".join(r.get("citation_ids", []))}]' for i, r in enumerate(results))
    require(response.get('answer') == expected_text and not response.get('answer_blocks'), 'text_consistency', 'Plain text differs from structured results or adds prose')
    require((response.get('generation') or {}).get('mode') == 'deterministic', 'generation', 'Unexpected generation mode')
    return {'id': case['id'], 'passed': not issues, 'expected_unique_entities': len(expected_targets),
            'expected_entities': [json.loads(t) for t in sorted(expected_targets)],
            'actual_result_count': len(results), 'expected_evidence_records': len(expected_rows),
            'actual_citations': len(citations), 'issue_counts': dict(Counter(x['category'] for x in issues)),
            'issues': issues, 'attribution_scope': 'per_edge' if citations and all(c.get('attribution') == 'per_edge' for c in citations) else 'record'}


def load_references(fixture=None):
    snapshot_bytes = (ROOT / 'factual_graph_snapshot.json').read_bytes().replace(b'\r\n', b'\n')
    digest = hashlib.sha256(snapshot_bytes).hexdigest()
    tables = json.loads(snapshot_bytes)['tables']
    cases = []
    hashes = {}
    for name in ([fixture] if fixture else ('factual_evaluation_cases_v3.json', 'legacy_factual_evaluation_cases_v3.json')):
        raw = (ROOT / name).read_bytes()
        hashes[name] = hashlib.sha256(raw).hexdigest()
        cases.extend(json.loads(raw))
    if len({c['id'] for c in cases}) != len(cases) or any(c['ground_truth_source']['sha256'] != digest for c in cases):
        raise ValueError('Duplicate case IDs or snapshot hash mismatch')
    return cases, tables, {'snapshot_sha256': digest, 'fixture_sha256': hashes}


def capture(cases, url, destination, provenance):
    with destination.open('x', encoding='utf-8') as output:
        rows = []
        for case in cases:
            start = time.monotonic()
            request = urllib.request.Request(url, data=json.dumps({'question': case['question'], 'history': case.get('history', [])}).encode(), headers={'Content-Type': 'application/json'})
            try:
                with urllib.request.urlopen(request, timeout=120) as reply:
                    response = json.load(reply)
                    code = reply.status
                row = {'id': case['id'], 'http_status': code, 'response': response}
            except Exception as error:
                row = {'id': case['id'], 'http_status': None, 'response': {'status': 'capture_error'}, 'error': str(error)}
            row['latency_ms'] = round((time.monotonic() - start) * 1000)
            rows.append(row)
            output.seek(0)
            json.dump({'captured_at': datetime.now(timezone.utc).isoformat(), 'url': url,
                       'provenance': provenance, 'server_version': 'not reported', 'rows': rows}, output, indent=2, ensure_ascii=False)
            output.truncate()
            output.flush()
            print(case['id'], row['response'].get('status'), flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--capture', action='store_true')
    parser.add_argument('--url', default='http://127.0.0.1:8081/api/chatbot_response.php')
    parser.add_argument('--answers', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--fixture', help='Versioned filename under fixtures; defaults preserve historical v3')
    args = parser.parse_args()
    if args.output.exists() or (args.capture and args.answers.exists()):
        parser.error('Use new output paths; historical artifacts must not be overwritten')
    cases, tables, provenance = load_references(args.fixture)
    oracles = {c['id']: reference(c, tables) for c in cases}
    if args.capture:
        capture(cases, args.url, args.answers, provenance)
    raw = args.answers.read_bytes()
    data = json.loads(raw)
    if data['provenance'] != provenance:
        raise ValueError('Capture/reference provenance mismatch')
    rows = data['rows']
    ids = [r['id'] for r in rows]
    expected_ids = {c['id'] for c in cases}
    capture_issues = {'missing_ids': sorted(expected_ids - set(ids)), 'unexpected_ids': sorted(set(ids) - expected_ids),
                      'duplicate_ids': sorted(i for i, count in Counter(ids).items() if count > 1)}
    by_id = {r['id']: r for r in rows}
    reports = []
    for case in cases:
        row = by_id.get(case['id'], {'response': {'status': 'missing_capture'}})
        try:
            report = evaluate(case, row['response'], oracles[case['id']])
        except (KeyError, TypeError, ValueError, AttributeError) as error:
            report = {'id': case['id'], 'passed': False, 'issue_counts': {'malformed_response': 1}, 'issues': [{'category': 'malformed_response', 'detail': str(error)}]}
        report['latency_ms'] = row.get('latency_ms')
        reports.append(report)
    totals = {'cases': len(cases), 'passed': sum(r['passed'] for r in reports), 'failed': sum(not r['passed'] for r in reports)}
    report = {'evaluated_at': datetime.now(timezone.utc).isoformat(), 'scope': 'Independent frozen-graph fidelity of structured results; not scientific truth, DOM visibility, or all seeds/endpoints',
              'provenance': provenance, 'answers_sha256': hashlib.sha256(raw).hexdigest(),
              'evaluator_sha256': hashlib.sha256(Path(__file__).read_bytes()).hexdigest(),
              'capture_issues': capture_issues, 'summary': totals, 'results': reports}
    with args.output.open('x', encoding='utf-8') as output:
        json.dump(report, output, indent=2, ensure_ascii=False)
    print(json.dumps(totals))
    return 1 if totals['failed'] or any(capture_issues.values()) else 0


if __name__ == '__main__':
    raise SystemExit(main())
