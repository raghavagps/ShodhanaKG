"""Live JSON/SSE conversations with actual UI-shaped history and frozen factual checks."""
import argparse
import copy
from collections import Counter, defaultdict
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import re
import time
import urllib.request
from evaluate_legacy_evidence import evaluate as check_evidence, contains

ROOT = Path(__file__).parent
FIXTURES = ROOT / 'fixtures'


def ui_history(messages):
    return [{'role': m['role'], 'text': m['text']} for m in messages
            if m['role'] == 'user' or m.get('status') in ('success', 'needs_clarification')][-20:]


def parse_sse(text):
    context = None
    done = None
    chunks = []
    for frame in re.split(r'\r?\n\r?\n', text.strip()):
        event = 'message'
        data = []
        for line in frame.splitlines():
            if line.startswith('event:'):
                event = line[6:].strip()
            elif line.startswith('data:'):
                data.append(line[5:].lstrip())
        if not data:
            continue
        value = json.loads('\n'.join(data))
        if event == 'context':
            context = value
        elif event == 'done':
            if done is not None:
                raise ValueError('Duplicate terminal event')
            done = value
        elif event == 'message' and isinstance(value.get('chunk'), str):
            chunks.append(value['chunk'])
    if not done or 'status' not in done:
        raise ValueError('Missing terminal status')
    if ''.join(chunks) != done.get('answer', ''):
        raise ValueError('Streamed answer differs from final answer')
    return {**done, 'context': context}


def request(url, question, history, transport, conversation_context=None, selected_entity=None):
    data = json.dumps({'question': question, 'history': history, 'stream': transport == 'sse', 'conversation_context': conversation_context, 'selected_entity': selected_entity}).encode()
    req = urllib.request.Request(url, data, {'Content-Type': 'application/json'})
    with urllib.request.urlopen(req, timeout=60) as response:
        body = response.read().decode('utf-8')
        content_type = response.headers.get('Content-Type', '')
        if transport == 'sse' and 'text/event-stream' not in content_type:
            raise ValueError('Expected event-stream content type')
    return parse_sse(body) if transport == 'sse' else json.loads(body)


def score(turn, response, references, records):
    issues = []
    status = response.get('status')
    if status in ('transport_error', 'error', 'grounding_error', 'http_error'):
        return ['infrastructure_or_generation_error']
    if 'node_reference' in turn:
        from stored_node_contract import node_issues
        return node_issues(turn['node_reference'], response, records)
    if 'reference_id' not in turn:
        if status not in turn['acceptable_statuses']:
            issues.append('false_acceptance' if status == 'success' else 'wrong_abstention_status')
        if response.get('answer_results') or response.get('citations') or (response.get('context') or {}).get('retrieval', {}).get('results'):
            issues.append('unexpected_factual_answer')
        if not response.get('answer', '').strip():
            issues.append('missing_explanation')
        return issues
    case = references[turn['reference_id']]
    if status != case['expected_status']:
        issues.append('false_refusal' if case['expected_status'] == 'success' and status in
                      ('unsupported', 'needs_clarification', 'entity_not_found', 'no_data') else 'wrong_final_status')
    context = response.get('context') or {}
    route = context.get('routing') or {}
    for key in ('endpoint', 'entity', 'entity_type'):
        if route.get(key) != case['expected_' + key]:
            issues.append('wrong_' + key)
    if issues:
        return issues
    retrieval = context.get('retrieval') or {}
    if context.get('status') != status or retrieval.get('status') != status:
        issues.append('status_disagreement')
    factual_issues = check_evidence(case, retrieval, records)
    if factual_issues:
        issues.append('factual_or_attribution_mismatch')
        return issues
    if status == 'no_data':
        if response.get('answer_results') or response.get('citations'):
            issues.append('invented_results_for_empty_graph')
        return issues
    rows = retrieval['results']
    citations = response.get('citations', [])
    if len(citations) != len(rows) or Counter(i for c in citations for i in c.get('record_indices', [])) != Counter(range(len(rows))):
        return issues + ['incomplete_citation_mapping']
    by_index = {e['result_index']: e['paths'] for e in retrieval['evidence_paths']}
    citation_ids = [c['id'] for c in citations]
    if len(set(citation_ids)) != len(citation_ids):
        issues.append('duplicate_citation_ids')
    for citation in citations:
        indices = citation['record_indices']
        if len(indices) != 1:
            issues.append('wrong_citation_mapping')
            continue
        i = indices[0]
        expected = {e['id']: e for p in by_index[i] for e in p['edges']}
        scopes = citation.get('scopes', [])
        if Counter(s.get('edge_id') for s in scopes) != Counter(expected.keys()):
            issues.append('incomplete_edge_scopes')
        for scope in scopes:
            edge = expected.get(scope.get('edge_id'))
            if not edge or any(scope.get(a) != edge[b] for a, b in [('source_id', 'source'), ('target_id', 'target'), ('properties', 'properties')]):
                issues.append('changed_edge_scope')
    result_indices = []
    linked = []
    labels = []
    wanted_labels = {p['nodes'][-1]['label'] for entry in retrieval['evidence_paths'] for p in entry['paths']}
    for result in response.get('answer_results', []):
        indices = result.get('record_indices', [])
        result_indices.extend(indices)
        linked.extend(result.get('citation_ids', []))
        labels.append(result.get('label'))
        targets = {p['nodes'][-1]['label'] for i in indices for p in by_index.get(i, [])}
        if targets != {result.get('label')}:
            issues.append('wrong_displayed_entity')
    if Counter(result_indices) != Counter(range(len(rows))) or Counter(linked) != Counter(citation_ids):
        issues.append('incomplete_result_access')
    if Counter(labels) != Counter(wanted_labels):
        issues.append('missing_or_duplicate_displayed_entities')
    return sorted(set(issues))


def semantic_payload(response):
    """Compare KG behavior; separately retain per-attempt optional-model diagnostics."""
    payload = {k: copy.deepcopy(response.get(k)) for k in (
        'status', 'answer', 'answer_results', 'citations', 'context', 'conversation_context',
        'clarification', 'resolution', 'node_evidence', 'query_plan')}
    if isinstance(payload.get('context'), dict):
        payload['context'].pop('understanding', None)
    return payload


def summarize(rows):
    categories = defaultdict(lambda: Counter(total=0, passed=0, failed=0))
    for r in rows:
        counts = categories[r['category']]
        counts['total'] += 1
        counts['passed' if r['passed'] else 'failed'] += 1
    return {'turn_runs': len(rows), 'passed': sum(r['passed'] for r in rows),
            'failed': sum(not r['passed'] for r in rows),
            'issues': dict(Counter(i for r in rows for i in r['issues'])),
            'categories': dict(categories)}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--transport', choices=['json', 'sse', 'both'], default='both')
    parser.add_argument('--fixture', default='../contracts/conversation_evaluation_cases_v4.json')
    parser.add_argument('--url', default='http://127.0.0.1:8081/api/chatbot_response.php')
    args = parser.parse_args()
    fixture = FIXTURES / args.fixture
    reference_file = FIXTURES / 'expanded_factual_cases_v4.json'
    snapshot = FIXTURES / 'factual_graph_snapshot_expanded.json'
    suite = json.loads(fixture.read_text(encoding='utf-8'))
    refs = {c['id']: c for c in json.loads(reference_file.read_text(encoding='utf-8'))}
    digest = lambda p: hashlib.sha256(p.read_bytes()).hexdigest()
    if any(c['ground_truth_source']['sha256'] != digest(snapshot) for c in refs.values()):
        raise ValueError('Snapshot/reference hash mismatch')
    records = {r['@rid']: r for rows in json.loads(snapshot.read_text(encoding='utf-8'))['tables'].values() for r in rows}
    ids = [s['id'] for s in suite['scenarios']]
    if len(ids) != len(set(ids)) or not ids:
        raise ValueError('Invalid scenario inventory')
    for scenario in suite['scenarios']:
        for turn in scenario['turns']:
            if not turn.get('question') or not turn.get('category') or sum(key in turn for key in ('reference_id', 'acceptable_statuses', 'node_reference')) != 1:
                raise ValueError('Invalid turn expectation')
            if 'reference_id' in turn and turn['reference_id'] not in refs:
                raise ValueError('Unknown factual reference')
    report = {'captured_at': datetime.now(timezone.utc).isoformat(), 'url': args.url,
              'fixture_sha256': digest(fixture), 'reference_sha256': digest(reference_file), 'snapshot_sha256': digest(snapshot),
              'runner_sha256': digest(Path(__file__)), 'source_sha256': {p.name: digest(p) for p in (ROOT.parent / 'core').glob('*.php')},
              'history_policy': 'Last 20 user/success/clarification turns plus latest structured server context; fresh retrieval every turn', 'scenarios': len(ids), 'runs': [], 'parity_failures': [], 'model_diagnostic_differences': []}
    transports = ['json', 'sse'] if args.transport == 'both' else [args.transport]
    with args.output.open('x', encoding='utf-8') as output:
        for scenario in suite['scenarios']:
            paired = {}
            diagnostics = {}
            for transport in transports:
                messages = []
                preceding_failed = False
                state = None
                for index, turn in enumerate(scenario['turns']):
                    history = ui_history(messages)
                    start = time.monotonic()
                    try:
                        response = request(args.url, turn['question'], history, transport, state, turn.get('selected_entity'))
                        issues = score(turn, response, refs, records)
                    except Exception as error:
                        response = {'status': 'transport_error', 'error': str(error)}
                        issues = ['infrastructure_or_generation_error']
                    row = {'scenario': scenario['id'], 'turn': index + 1, 'transport': transport,
                           'category': turn['category'], 'question': turn['question'], 'expectation': turn,
                           'history_sent': history, 'conversation_context_sent': state, 'selected_entity_sent': turn.get('selected_entity'), 'preceding_turn_failed': preceding_failed,
                           'response': response, 'passed': not issues, 'issues': issues,
                           'latency_ms': round((time.monotonic() - start) * 1000)}
                    report['runs'].append(row)
                    messages += [{'role': 'user', 'text': turn['question']},
                                 {'role': 'bot', 'text': response.get('answer', ''), 'status': response.get('status')}]
                    if response.get('conversation_context') is not None:
                        state = response['conversation_context']
                    preceding_failed = preceding_failed or bool(issues)
                    semantic = semantic_payload(response)
                    diagnostic = (response.get('context') or {}).get('understanding')
                    if transport == 'json':
                        paired[index] = semantic
                        diagnostics[index] = diagnostic
                    elif index in paired and semantic != paired[index]:
                        report['parity_failures'].append({'scenario': scenario['id'], 'turn': index + 1})
                    if transport == 'sse' and index in diagnostics and diagnostic != diagnostics[index]:
                        report['model_diagnostic_differences'].append({'scenario': scenario['id'], 'turn': index + 1, 'json': diagnostics[index], 'sse': diagnostic})
                    report['summary'] = summarize(report['runs'])
                    output.seek(0)
                    json.dump(report, output, indent=2, ensure_ascii=False)
                    output.truncate(); output.flush()
                    print(('PASS' if not issues else 'FAIL'), scenario['id'], index + 1, transport, ', '.join(issues), flush=True)
    print(json.dumps(report['summary']))
    return bool(report['summary']['failed'] or report['parity_failures'])


if __name__ == '__main__':
    raise SystemExit(main())
