"""Independent frozen-KG joins and per-edge audits. Never uses an LLM as an oracle.

Reads an independently captured raw graph and the reviewed namespace audit. Does
not import production routing, traversal definitions, or presentation code.
Historical evaluation fixtures remain immutable.
"""
import argparse
from collections import defaultdict, Counter
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import re
import time
import urllib.parse
import urllib.request
from reviewed_reference_oracle import identifiers, reference_paths
from evaluate_conversations import parse_sse

BASE = 'http://127.0.0.1:8081/api/'
PHASE = {'TREATED_BY': 'treated', 'PRE_TREATED_BY': 'pre-treated', 'POST_TREATED_BY': 'post-treated'}
ALLOWED = set(PHASE) | {'USES_FORMULATION', 'UNDERGOES', 'FOLLOWED_BY', 'PRODUCES', 'USES_MEDIA', 'DETOXIFIES', 'USED_FOR_TREATMENT', 'TREATS', 'USED_IN_FORMULATION'}

def meaningful(v):
    if v is None: return False
    if isinstance(v, str): return v.strip().lower() not in ('', 'null', 'not supplied')
    if isinstance(v, (list, dict)): return any(meaningful(x) for x in (v.values() if isinstance(v, dict) else v))
    return True

def display(raw):
    return {k: v for k, v in raw.items() if not k.startswith('@') and not re.fullmatch(r'ref|references|reference|pubmed[_ ]?id|doi|ayush[_ ]?id|unclassified_reference|phase', k, re.I) and meaningful(v)}

def request(endpoint, params=None, body=None):
    url = BASE + endpoint + (('?' + urllib.parse.urlencode(params)) if params else '')
    req = urllib.request.Request(url, json.dumps(body).encode() if body else None, {'Content-Type': 'application/json'})
    with urllib.request.urlopen(req, timeout=120) as response:
        raw = response.read().decode()
    return parse_sse(raw) if body and body.get('stream') else json.loads(raw)

class Oracle:
    def __init__(self, tables):
        self.records = {r['@rid']: r for rows in tables.values() for r in rows}
        self.adj = defaultdict(list)
        for r in self.records.values():
            if r.get('@type') in ALLOWED and '@out' in r:
                self.adj[r['@out']].append((r, r['@in']))
                self.adj[r['@in']].append((r, r['@out']))

    def ref(self, edge):
        values = identifiers(edge)
        return next(((k, values[k]) for k in ('pubmed_id', 'doi', 'ayush_id') if values[k]), None)

    def paths(self, source, name, target, constraints=()):
        seed, = [r for r in self.records.values() if r.get('@type') == source and r.get('name') == name]
        constraints = {c['type']: c['name'] for c in constraints}
        result = {}
        def walk(nodes, edges, anchor, visits):
            current = self.records[nodes[-1]]
            if edges and current['@type'] == target:
                if all(any(self.records[n]['@type'] == k and self.records[n]['name'] == v for n in nodes[1:]) for k, v in constraints.items()):
                    result[tuple(e['@rid'] for e in edges)] = (nodes, edges)
                return
            if len(edges) == 5: return
            for edge, rid in self.adj[nodes[-1]]:
                node = self.records[rid]; kind = node['@type']
                if rid in nodes or visits[kind] >= (2 if kind == 'Process' else 1): continue
                if kind in constraints and node['name'] != constraints[kind]: continue
                if edges and edge['@type'] == edges[-1]['@type']:
                    previous_forward = edges[-1]['@out'] == nodes[-2]
                    if previous_forward != (edge['@out'] == nodes[-1]): continue
                ref = self.ref(edge)
                split = bool(edges and current['@type'] == 'Purified_Compound' and
                    'PRODUCES' in (edges[-1]['@type'], edge['@type']) and
                    ({edges[-1]['@type'], edge['@type']} & {'TREATS', 'USED_FOR_TREATMENT'}))
                if edges and (ref is None or anchor is None or ref != anchor) and not (split and ref and anchor): continue
                walk(nodes + [rid], edges + [edge], ref if not edges or split else anchor, visits + Counter([kind]))
        walk([seed['@rid']], [], None, Counter([source]))
        return result

    def path_issues(self, path):
        errors = []; nodes = path.get('nodes', []); edges = path.get('edges', [])
        if len(nodes) != len(edges) + 1: return ['path length']
        for node in nodes:
            raw = self.records.get(node.get('id'), {})
            if node != {'id': raw.get('@rid'), 'label': raw.get('name'), 'type': raw.get('@type')}: errors.append('node identity')
        for i, edge in enumerate(edges):
            raw = self.records.get(edge.get('id'), {})
            for key, field in [('id', '@rid'), ('type', '@type'), ('source', '@out'), ('target', '@in')]:
                if edge.get(key) != raw.get(field): errors.append('edge ' + key)
            if edge.get('properties') != {k: v for k, v in raw.items() if not k.startswith('@')}: errors.append('raw properties')
            if {edge.get('source'), edge.get('target')} != {nodes[i]['id'], nodes[i+1]['id']}: errors.append('connectivity')
            if any(edge.get('evidence', {}).get(k, '') != v for k, v in identifiers(raw).items()): errors.append('reference namespace/identity')
        return errors

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--snapshot', type=Path, required=True)
    parser.add_argument('--properties', type=Path, required=True)
    parser.add_argument('--facts', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    tables = json.loads(args.snapshot.read_text(encoding='utf-8'))['tables']
    oracle = Oracle(tables)
    report = {'captured_at': datetime.now(timezone.utc).isoformat(), 'scope': 'KG fidelity, not publication validity or clinical efficacy',
              'snapshot_sha256': hashlib.sha256(args.snapshot.read_bytes()).hexdigest(), 'checks': []}
    def check(name, errors, **data):
        report['checks'].append({'id': name, 'passed': not errors, 'issues': errors, **data})
        args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding='utf-8')
        print(('PASS ' if not errors else 'FAIL ') + name + (': ' + str(errors[:3]) if errors else ''), flush=True)
    props = json.loads(args.properties.read_text(encoding='utf-8'))
    errors = []
    raw_edges = {k: r for k, r in oracle.records.items() if '@out' in r}
    if set(raw_edges) != set(props['edges']): errors.append('incomplete edge capture')
    for rid, raw in raw_edges.items():
        actual = props['edges'].get(rid, {})
        if actual.get('properties') != {k: v for k, v in raw.items() if not k.startswith('@')}: errors.append(rid + ': raw properties')
        if {p['key']: p['value'] for p in actual.get('display_properties', [])} != display(raw): errors.append(rid + ': display properties')
        if actual.get('treatment_phase') != PHASE.get(raw['@type']): errors.append(rid + ': phase')
    check('all_stored_edge_properties', errors, edges=len(raw_edges), phase_edges=sum(r['@type'] in PHASE for r in raw_edges.values()))
    for kind, actual in props['synthetic'].items():
        values = {p['key']: p['value'] for p in actual['display_properties']}
        errors = []
        if actual['treatment_phase'] != PHASE.get(kind): errors.append('phase inferred from property/name')
        if any(k not in values for k in ['Dose', 'Route', 'Frequency', 'Future_Property', 'Zero', 'False']): errors.append('meaningful values dropped')
        if any(k in values for k in ['phase', 'Empty', 'Missing', 'Placeholder', '@rid']): errors.append('empty/internal values shown')
        check('synthetic_' + kind, errors)
    facts = json.loads(args.facts.read_text(encoding='utf-8'))['cases']
    for case in facts:
        actual = case['actual']
        if actual.get('status') != 'success': continue
        answer = props['answers'][case['id']]; errors = []
        if answer.get('status') != 'success': errors.append('presentation rejected valid retrieval')
        attributed = {p['result_index']: p['paths'] for p in actual.get('evidence_paths', [])}
        for index, row in enumerate(actual.get('results', [])):
            paths = [row] if 'edges' in row else attributed.get(index, [])
            expected = {e['id'] for p in paths for e in p['edges']}
            scopes = answer.get('citations', [])[index]['scopes'] if len(answer.get('citations', [])) > index else []
            if expected != {s.get('edge_id') for s in scopes}: errors.append('missing/detached citation edges')
            for scope in scopes:
                raw = oracle.records.get(scope.get('edge_id'), {})
                if {p['key']: p['value'] for p in scope.get('display_properties', [])} != display(raw): errors.append('display properties')
                if scope.get('treatment_phase') != PHASE.get(raw.get('@type')): errors.append('phase')
                if scope.get('source_id') != raw.get('@out') or scope.get('target_id') != raw.get('@in'): errors.append('citation direction')
        check('answer_properties_' + case['id'], errors, endpoint=case['endpoint'])

    def semantic(source, name, target, constraints=()):
        expected = oracle.paths(source, name, target, constraints); rows = []; offset = 0
        while True:
            response = request('semantic_query.php', {'source_type': source, 'entity': name, 'result_type': target, 'constraints': json.dumps(constraints), 'offset': offset, 'limit': 7})
            rows += response.get('results', [])
            if not response.get('pagination', {}).get('has_more'): break
            new_offset = response['pagination']['next_offset']
            if new_offset <= offset: raise AssertionError('pagination does not advance')
            offset = new_offset
        errors = []
        keys = [tuple(e['id'] for e in row['edges']) for row in rows]
        if set(keys) != set(expected) or len(keys) != len(set(keys)): errors.append('missing/extra/duplicate paths')
        for row in rows: errors += oracle.path_issues(row)
        check('path_' + '_'.join([source, name, target]), errors, expected_paths=len(expected), actual_paths=len(rows))
        return expected
    for seed in ['Copper', 'Lead', 'Abrus precatorius Linn.']:
        for target in ['Shodhana_Method', 'Disease']:
            semantic('Compound', seed, target)
    for source, name, target in [('Shodhana_Method', 'Nirvapa', 'Compound'), ('Purified_Compound', 'Shodhita Tamra', 'Disease'), ('Disease', 'Eczema', 'Compound'), ('Disease', 'Psoriasis', 'Compound')]:
        semantic(source, name, target)
    constrained = semantic('Disease', 'Vipadika', 'Formulation', [{'type': 'Therapy', 'name': 'Sarvanga Abhyanga'}])
    questions = [
        ('Which formulation is used in Vipadika in Sarvanga Abhyanga?', 'Disease', 'Vipadika', 'Formulation', [{'type': 'Therapy', 'name': 'Sarvanga Abhyanga'}]),
        ('Which formulations are used with Sarvanga Abhyanga for Vipadika?', 'Disease', 'Vipadika', 'Formulation', [{'type': 'Therapy', 'name': 'Sarvanga Abhyanga'}]),
        ('For Vipadika, which medicine is used during Sarvanga Abhyanga?', 'Disease', 'Vipadika', 'Formulation', [{'type': 'Therapy', 'name': 'Sarvanga Abhyanga'}]),
        ('What Shodhana method is used to purify lead?', 'Compound', 'Lead', 'Shodhana_Method', []),
        ('Which purification methods are used for copper?', 'Compound', 'Copper', 'Shodhana_Method', []),
        ('Copper after purification helps to treat which disease?', 'Compound', 'Copper', 'Disease', []),
        ('Which diseases are associated with purified copper?', 'Compound', 'Copper', 'Disease', [])]
    for question, source, name, target, constraints in questions:
        wanted = oracle.paths(source, name, target, constraints); responses = []
        for stream in [False, True]:
            start = time.monotonic(); response = request('chatbot_response.php', body={'question': question, 'stream': stream})
            errors = []; actual = response.get('context', {}).get('retrieval', {}).get('results', [])
            keys = {tuple(e['id'] for e in r.get('edges', [])) for r in actual}
            if keys != set(wanted): errors.append('routing/path completeness')
            if response.get('status') != ('success' if wanted else 'no_data'): errors.append('status')
            expected_labels = {oracle.records[n[-1]]['name'] for n, e in wanted.values()}
            if {r['label'] for r in response.get('answer_results', [])} != expected_labels: errors.append('result entity type/direction')
            check(('sse_' if stream else 'json_') + question, errors, latency_ms=round((time.monotonic()-start)*1000))
            responses.append(response)
        for key in ['answer_results', 'citations', 'conversation_context', 'pagination', 'status']:
            check('parity_' + key + '_' + question, [] if responses[0].get(key) == responses[1].get(key) else ['JSON/SSE mismatch'])

    # Active subject, explicit switch, clarification, constrained follow-up, unsupported turn.
    scenarios = [
        [('What is used to purify copper?', 'Copper'), ('And which diseases is it used for?', 'Copper'), ('And what methods are used for it?', 'Copper')],
        [('What is used to purify copper?', 'Copper'), ('What symptoms are associated with Psoriasis?', 'Psoriasis'), ('And which therapies treat it?', 'Psoriasis')],
        [('Which methods are used for Copper and Lead?', None), ('Lead', 'Lead'), ('And which diseases is it used for?', 'Lead')],
        [('Which formulation is used in Vipadika in Sarvanga Abhyanga?', 'Vipadika'), ('And which formulations are used for it?', 'Vipadika')],
        [('What is used to purify copper?', 'Copper'), ('Write me a poem', 'Copper'), ('And which diseases is it used for?', 'Copper')]]
    for i, scenario in enumerate(scenarios):
        for stream in [False, True]:
            state = {'version': 1}
            for j, (question, subject) in enumerate(scenario):
                response = request('chatbot_response.php', body={'question': question, 'conversation_context': state, 'stream': stream})
                state = response.get('conversation_context', {}); errors = []
                if state.get('subject', {}).get('name') != subject: errors.append('active subject')
                if subject and question != 'Write me a poem' and response.get('status') not in ('success', 'no_data'): errors.append('false refusal')
                if question == 'Write me a poem' and response.get('status') == 'success': errors.append('false acceptance')
                for path in response.get('context', {}).get('retrieval', {}).get('results', []):
                    if 'edges' in path: errors += oracle.path_issues(path)
                check(f'memory_{i+1}_{j+1}_{stream}', errors, status=response.get('status'))

    # Graph output is checked against raw records, including stored direction.
    for source, name in [('Compound', 'Copper'), ('Disease', 'Eczema'), ('Disease', 'Vipadika')]:
        node, = [r for r in oracle.records.values() if r.get('@type') == source and r.get('name') == name]
        for domain in ['integrated', 'compound' if source == 'Compound' else 'disease']:
            graph = request('graph.php', {'domain': domain, 'seeds': node['@rid'], 'integratedMode': 'paths', 'depth': 2, 'limitNodes': 500, 'limitEdges': 2000})
            errors = []
            for edge in graph.get('edges', []):
                raw = oracle.records.get(edge['id'], {})
                if (edge.get('source'), edge.get('target'), edge.get('raw_type')) != (raw.get('@out'), raw.get('@in'), raw.get('@type')): errors.append('direction/type/identity')
                if edge.get('properties') != {k: v for k, v in raw.items() if not k.startswith('@')}: errors.append('graph edge properties')
            for path in graph.get('meta', {}).get('evidencePaths', []): errors += oracle.path_issues(path)
            if domain == 'integrated' and name == 'Copper' and not any(n['label'] == 'Eczema' for n in graph['nodes']): errors.append('missing copper disease path')
            check('graph_' + domain + '_' + name, errors, nodes=len(graph.get('nodes', [])), edges=len(graph.get('edges', [])))
        details = request('node.php', {'domain': 'integrated', 'id': node['@rid'], 'neighborLimit': 200})
        errors = []
        incident = {r['@rid']: r for r in oracle.records.values() if r.get('@out') == node['@rid'] or r.get('@in') == node['@rid']}
        if details['node']['id'] != node['@rid']: errors.append('node identity')
        if not details['meta']['truncated'] and {e['id'] for e in details['edges']} != set(incident): errors.append('node context incomplete edges')
        if {e['edgeId'] for e in details['evidence']} != {e['id'] for e in details['edges']}: errors.append('node evidence mapping')
        for edge in details['edges']:
            raw = incident[edge['id']]
            if (edge['source'], edge['target']) != (raw['@out'], raw['@in']): errors.append('node edge direction')
            if edge['properties'] != {k: v for k, v in raw.items() if not k.startswith('@')}: errors.append('node properties')
        check('node_context_' + name, errors, edges=len(details['edges']))
    definitions = [
        ('provenance_disease_therapy_formulation', 'Disease', 'Vipadika', [(list(PHASE), '@out', ['Therapy']), (['USES_FORMULATION'], '@out', ['Formulation'])]),
        ('provenance_formulation_therapy_disease', 'Formulation', 'Nalpamaradi Keram', [(['USES_FORMULATION'], '@in', ['Therapy']), (list(PHASE), '@in', ['Disease'])]),
        ('provenance_compound_method_media', 'Compound', 'Copper', [(['UNDERGOES'], '@out', ['Shodhana_Method']), (['USES_MEDIA'], '@out', ['Media'])]),
        ('provenance_media_method_compound', 'Media', 'Cow Urine', [(['USES_MEDIA'], '@in', ['Shodhana_Method']), (['UNDERGOES'], '@in', ['Compound'])])]
    for endpoint, kind, name, steps in definitions:
        expected, _ = reference_paths(tables, (kind, name, steps))
        response = request(endpoint + '.php', {'entity': name})
        rows = response.get('results', []); errors = []
        if {tuple(e['id'] for e in r['edges']) for r in rows} != {tuple(e['id'] for e in r['edges']) for r in expected}: errors.append('provenance completeness')
        for row in rows: errors += oracle.path_issues(row)
        check('provenance_' + endpoint, errors, paths=len(rows))
        if kind not in ('Disease', 'Compound'): continue
        explorer = request('provenance-subgraph.php' if kind == 'Disease' else 'compound-provenance.php', {'diseaseId' if kind == 'Disease' else 'compoundId': name, 'limit': 5000})
        paths = explorer.get('paths', []); errors = []
        found = {(p['edge1']['id'], p['edge2']['id']) for p in paths}
        if found != {tuple(e['id'] for e in r['edges']) for r in expected}: errors.append('explorer path completeness')
        for path in paths:
            for edge in [path['edge1'], path['edge2']]:
                raw = oracle.records[edge['id']]
                if (edge.get('source'), edge.get('target'), edge.get('raw_type')) != (raw['@out'], raw['@in'], raw['@type']): errors.append('explorer direction/identity')
                if edge.get('properties') != {k: v for k, v in raw.items() if not k.startswith('@')}: errors.append('explorer properties')
                if {p['key']: p['value'] for p in edge.get('display_properties', [])} != display(raw): errors.append('explorer display properties')
        check('explorer_provenance_' + kind, errors, paths=len(paths))
    bridge = request('bridge-stats.php', {'limit': 200})
    errors = []
    expected_products = {r['@out'] for r in tables['USED_FOR_TREATMENT']}
    if {r['purifiedId'] for r in bridge['rows']} != expected_products: errors.append('missing/extra bridge products')
    for row in bridge['rows']:
        rid = row['purifiedId']
        raw_edges = [r for table in ('DERIEVED_FROM', 'USED_FOR_TREATMENT', 'USED_IN_FORMULATION') for r in tables[table] if r['@out'] == rid]
        if {e['id'] for e in row['edges']} != {e['@rid'] for e in raw_edges}: errors.append('missing/extra bridge edge')
        if set(row['compoundIds']) != {e['@in'] for e in raw_edges if e['@type'] == 'DERIEVED_FROM'}: errors.append('names/numeric IDs substituted for compound RIDs')
        if row['diseaseCount'] != len({e['@in'] for e in raw_edges if e['@type'] == 'USED_FOR_TREATMENT'}): errors.append('counting evidence rows instead of diseases')
        if row['studies'] != len({oracle.ref(e) for e in raw_edges if oracle.ref(e)}): errors.append('distinct namespace-aware study count')
        for edge in row['edges']:
            raw = oracle.records[edge['id']]
            if edge['properties'] != {k: v for k, v in raw.items() if not k.startswith('@')}: errors.append('bridge properties')
    check('integrated_table_identity_counts_properties', errors, products=len(bridge['rows']))
    report['summary'] = {'total': len(report['checks']), 'passed': sum(r['passed'] for r in report['checks'])}
    report['summary']['failed'] = report['summary']['total'] - report['summary']['passed']
    args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding='utf-8')
    print(report['summary'])
    return bool(report['summary']['failed'])

if __name__ == '__main__': raise SystemExit(main())
