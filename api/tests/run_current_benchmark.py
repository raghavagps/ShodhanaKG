"""Fresh, versioned benchmark. Graph fidelity is distinct from scientific truth."""
import argparse
from collections import Counter
from datetime import datetime, timezone
import hashlib
import json
import math
from pathlib import Path
import subprocess
import sys
import time
import platform
import os

REPO = Path(__file__).resolve().parents[2]
TESTS = REPO / 'api/tests'
FIX = TESTS / 'fixtures'


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def sources():
    paths = list((REPO / 'src').rglob('*')) + list((REPO / 'api/core').rglob('*')) + list((REPO / 'api').glob('*.php'))
    paths += list(TESTS.glob('*.py')) + list(TESTS.glob('*.php')) + list((REPO / 'tests').rglob('*.ts')) + list((REPO / 'tests').rglob('*.cjs'))
    paths += list((TESTS / 'contracts').glob('*'))
    paths += [REPO / p for p in ('package.json', 'package-lock.json', 'vite.config.ts', 'vitest.config.ts', 'eslint.config.js', 'tsconfig.json', 'tsconfig.app.json', 'tsconfig.node.json', 'playwright.config.ts', 'playwright.external.config.ts', '.github/workflows/playwright.yml')]
    return {str(p.relative_to(REPO)): digest(p) for p in sorted(set(paths)) if p.is_file() and '__pycache__' not in p.parts}



def supplemental_summary(report):
    if isinstance(report.get('summary'), dict):
        return report['summary']
    return {key: report[key] for key in ('total', 'passed', 'failed') if key in report}


def latency(rows):
    values = sorted(r['latency_ms'] for r in rows if isinstance(r.get('latency_ms'), (int, float)))
    if not values:
        return {'n': 0, 'missing': len(rows)}
    return {'n': len(values), 'missing': len(rows) - len(values),
            'p50_ms': values[math.ceil(len(values) * .5) - 1],
            'p95_ms': values[math.ceil(len(values) * .95) - 1],
            'max_ms': max(values), 'mean_ms': round(sum(values) / len(values), 2)}


def refusal_metrics(runs, refs):
    groups = {}
    for transport in sorted({r['transport'] for r in runs}):
        rows = [r for r in runs if r['transport'] == transport]
        answerable = [r for r in rows if r['expectation'].get('reference_id') in refs
                      and refs[r['expectation']['reference_id']]['expected_status'] == 'success'
                      or 'node_reference' in r['expectation']]
        abstentions = [r for r in rows if 'acceptable_statuses' in r['expectation']]
        groups[transport] = {
            'answerable_turns': len(answerable),
            'false_refusals': sum(r['response'].get('status') in {'unsupported', 'needs_clarification', 'entity_not_found', 'no_data'} for r in answerable),
            'answerable_non_success': sum(r['response'].get('status') != 'success' for r in answerable),
            'expected_abstention_turns': len(abstentions),
            'correct_abstentions': sum(r['passed'] for r in abstentions),
            'false_acceptances': sum(r['response'].get('status') == 'success' for r in abstentions),
            'infrastructure_or_generation_errors': sum('infrastructure_or_generation_error' in r['issues'] for r in rows)}
    return groups


def namespace_findings(captures, overrides):
    findings = []
    def walk(value, path, capture):
        if isinstance(value, dict):
            for k, v in value.items():
                if k in {'pubmed_id', 'pubmedId', 'pmid'} and str(v) in overrides:
                    findings.append({'capture': capture, 'path': path + '/' + k, 'value': str(v), 'expected_namespace': 'ayush_id'})
                walk(v, path + '/' + str(k), capture)
        elif isinstance(value, list):
            for i, v in enumerate(value):
                walk(v, path + '/' + str(i), capture)
    for name, data in captures.items():
        walk(data, '', name)
    return findings


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output-dir', type=Path, required=True)
    parser.add_argument('--php', default='php')
    parser.add_argument('--contract-version', choices=['3', '4', '5', '6', '7', '8'], default='8')
    args = parser.parse_args()
    out = args.output_dir.resolve()
    out.mkdir(exist_ok=False)
    started = datetime.now(timezone.utc).isoformat()
    before = sources()
    factual_fixture = FIX / ('expanded_factual_cases_v' + ('4' if args.contract_version in ['5', '6', '7', '8'] else args.contract_version) + '.json')
    jobs = [
        ('snapshot', [args.php, str(TESTS / 'capture_expanded_snapshot.php'), str(out / 'snapshot.json')]),
        ('factual', [args.php, str(TESTS / 'run_expanded_factual.php'), str(factual_fixture), str(out / 'factual.json')]),
        ('attribution', [sys.executable, str(TESTS / 'evaluate_legacy_evidence.py'), '--fixture', str(factual_fixture), '--answers', str(out / 'factual.json'), '--output', str(out / 'attribution.json')]),
        ('presentation', [sys.executable, str(TESTS / 'evaluate_results_presentation.py'), '--capture', '--answers', str(out / 'answers.json'), '--output', str(out / 'presentation.json')]),
        ('conversation', [sys.executable, str(TESTS / 'evaluate_conversations.py'), '--output', str(out / 'conversation.json')]),
        ('routing', [args.php, str(TESTS / 'run_evaluation_cases.php'), '--http'])]
    if args.contract_version in ['4', '5', '6', '7', '8']:
        jobs[3][1].extend(['--fixture', 'presentation_cases_v' + ('5' if args.contract_version in ['6', '7', '8'] else args.contract_version) + '.json'])
        jobs[5][1].append('--fixture=' + str((TESTS / 'contracts' if args.contract_version == '8' else FIX) / ('chatbot_evaluation_cases_v' + args.contract_version + '.json')))
    if args.contract_version in ['5', '6', '7', '8']:
        jobs[4][1].extend(['--fixture', ('../contracts/conversation_evaluation_cases_v4.json' if args.contract_version == '8' else 'conversation_evaluation_cases_v3.json' if args.contract_version in ['6', '7', '8'] else 'conversation_evaluation_cases_v2.json')])
    jobs += [('property_capture', [args.php, str(TESTS / 'capture_system_properties.php'), str(out / 'snapshot.json'), str(out / 'factual.json'), str(out / 'properties.json')])]
    for name, script, options in [
        ('structured_context', 'evaluate_structured_context_v2.py', ['--output']),
        ('entity_resolution', 'evaluate_entity_resolution.py', ['--output']),
        ('resolution_context', 'evaluate_resolution_context.py', ['--output']),
        ('node_properties', 'evaluate_node_properties.py', []),
        ('terminology', 'evaluate_terminology_expansion.py', []),
        ('method_results', 'evaluate_method_results.py', ['--output']),
        ('fragment_methods', 'evaluate_fragment_methods.py', ['--output'])]:
        jobs.append((name, [sys.executable, '-B', str(TESTS / script), *options, str(out / (name + '.json'))]))
    jobs.append(('system_consistency', [sys.executable, '-B', str(TESTS / 'evaluate_system_consistency.py'),
        '--snapshot', str(out / 'snapshot.json'), '--properties', str(out / 'properties.json'),
        '--facts', str(out / 'factual.json'), '--output', str(out / 'system_consistency.json')]))
    commands = []
    for name, command in jobs:
        print('START', name, flush=True)
        start = time.monotonic()
        with (out / (name + '.log')).open('x', encoding='utf-8') as log:
            result = subprocess.run(command, cwd=REPO, stdout=log, stderr=subprocess.STDOUT)
        commands.append({'suite': name, 'command': command, 'exit_code': result.returncode,
                         'elapsed_seconds': round(time.monotonic() - start, 2)})
        (out / 'progress.json').write_text(json.dumps(commands, indent=2), encoding='utf-8')
        print('END', name, result.returncode, flush=True)
    def read(name):
        return json.loads((out / name).read_bytes())
    required = ['factual.json', 'attribution.json', 'answers.json', 'presentation.json', 'conversation.json', 'snapshot.json']
    missing = [p for p in required if not (out / p).exists()]
    supplemental_files = [name + '.json' for name in ('properties', 'structured_context', 'entity_resolution', 'resolution_context', 'node_properties', 'terminology', 'method_results', 'fragment_methods', 'system_consistency')]
    missing += [name for name in supplemental_files if not (out / name).exists()]
    if missing:
        raise RuntimeError('Benchmark incomplete; missing artifacts: ' + ', '.join(missing))
    factual, attribution, answers, presentation, conversation, snapshot = [read(p) for p in required]
    reference = json.loads((FIX / 'factual_graph_snapshot_expanded.json').read_bytes())
    records = lambda s: {r['@rid']: r for rows in s['tables'].values() for r in rows}
    actual, expected = records(snapshot), records(reference)
    drift = {'added': sorted(actual.keys() - expected.keys()), 'removed': sorted(expected.keys() - actual.keys()),
             'changed': sorted(k for k in actual.keys() & expected.keys() if actual[k] != expected[k])}
    refs = {r['id']: r for r in json.loads((factual_fixture).read_bytes())}
    audit = json.loads((REPO / 'docs/chatbot/audits/identifier_rules_2026_09_24_v1/audit.json').read_bytes())
    overrides = {r['Reference']: 'AYUSH' for r in audit['rows'] if r['assessed_type'] == 'AYUSH'}
    # Scan only actual outputs, not fixture expectations, for known namespace errors.
    namespace = namespace_findings({'factual': [r['actual'] for r in factual['cases']],
                                    'answers': [r['response'] for r in answers['rows']],
                                    'conversation': [r['response'] for r in conversation['runs']]}, overrides)
    after = sources()
    routing = json.loads((out / 'routing.log').read_text(encoding='utf-8').strip().splitlines()[-1])
    summary = {
        'version': 3, 'contract_version': args.contract_version, 'started_at': started, 'completed_at': datetime.now(timezone.utc).isoformat(),
        'scope': 'Current local sequential benchmark. Exact KG agreement is not clinical/scientific accuracy. Suites overlap; do not sum their pass counts.',
        'runtime': {'python': sys.version, 'platform': platform.platform(), 'php': subprocess.check_output([args.php, '-v'], text=True).splitlines()[0], 'model': os.environ.get('OLLAMA_MODEL', 'llama3.2'), 'environment_scope': 'CLI environment; does not attest HTTP server deployment or loaded model digest'},
        'supplemental': {name: supplemental_summary(read(name + '.json')) for name in ('structured_context', 'entity_resolution', 'resolution_context', 'node_properties', 'terminology', 'method_results', 'fragment_methods', 'system_consistency') if (out / (name + '.json')).exists()},
        'commands': commands, 'source_sha256': before, 'source_changed_during_run': before != after,
        'graph_drift': drift, 'factual': factual['summary'], 'attribution': attribution['summary'],
        'presentation': presentation['summary'], 'conversation': conversation['summary'],
        'transport_parity_failures': conversation['parity_failures'],
        'model_diagnostic_differences': conversation.get('model_diagnostic_differences', []),
        'parity_scope': 'Answers, routing, retrieval, references, citation mapping and structured context must match. Per-attempt optional model availability/rejection diagnostics are recorded separately; client byte-stream metadata preservation has independent tests.', 'routing_status': routing,
        'refusals': refusal_metrics(conversation['runs'], refs),
        'latency': {'method': 'Nearest-rank percentiles; sequential single-client local requests, no warmup removal. Includes failed requests. End-to-end HTTP body receipt; not first-token latency or load testing.',
                    'direct_endpoint_not_http': latency(factual['cases']),
                    'results_first_http_json': latency(answers['rows']),
                    'conversation_http_json': latency([r for r in conversation['runs'] if r['transport'] == 'json']),
                    'conversation_http_sse': latency([r for r in conversation['runs'] if r['transport'] == 'sse'])},
        'presentation_issue_counts': dict(Counter(i['category'] for r in presentation['results'] for i in r['issues'])),
        'known_namespace_errors': {'occurrences': len(namespace), 'distinct_identifiers': sorted({r['value'] for r in namespace}),
                                  'findings': namespace, 'caveat': 'Nested/repeated occurrences are not unique claims. Production uses the edge-bound audited catalog; this scan checks all 147 AYUSH values.'},
        'scientific_accuracy': {'status': 'not_established', 'expert_approved_claims': 0,
                                'reason': 'Graph references are not independent clinical ground truth; publication review remains partial.'},
        'artifacts_sha256': {p.name: digest(p) for p in sorted(out.iterdir()) if p.is_file()},
        'evaluation_source_sha256': {str(p.relative_to(REPO)): digest(p) for p in sorted(TESTS.iterdir()) if p.suffix in {'.py', '.php'}},
        'reference_sha256': {p.name: digest(p) for p in [factual_fixture, FIX / (('../contracts/conversation_evaluation_cases_v4.json' if args.contract_version == '8' else 'conversation_evaluation_cases_v3.json' if args.contract_version in ['6', '7', '8'] else 'conversation_evaluation_cases_v2.json') if args.contract_version in ['5', '6', '7', '8'] else 'conversation_evaluation_cases_v1.json'), FIX / 'publication_namespace_overrides_v2.json']}}
    (out / 'benchmark.json').write_text(json.dumps(summary, indent=2), encoding='utf-8')
    print(json.dumps({k: summary[k] for k in ('factual', 'attribution', 'presentation', 'refusals')}), flush=True)
    return int(any(c['exit_code'] for c in commands) or bool(namespace) or any(drift.values()) or before != after)


if __name__ == '__main__':
    raise SystemExit(main())
