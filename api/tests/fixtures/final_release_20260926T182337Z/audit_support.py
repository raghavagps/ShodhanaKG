"""Audit-only orchestration. Does not edit project source or reference fixtures."""
import ast
import json
import os
import re
import subprocess
import sys
import time
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[3]
PHP = r'C:\xampp\php\php.exe'

def save(name, value):
    (OUT / name).write_text(json.dumps(value, indent=2, ensure_ascii=False), encoding='utf-8')

def inventory():
    tree = ast.parse((ROOT / 'api/tests/run_release_checks.py').read_text(encoding='utf-8'))
    maintained = next(ast.literal_eval(n.value) for n in tree.body if isinstance(n, ast.Assign) and any(isinstance(t, ast.Name) and t.id == 'PHP_TESTS' for t in n.targets))
    files = sorted(set((ROOT / 'api/tests').glob('test*.php')) | set((ROOT / 'api/tests').glob('*.test.php')))
    rows = []
    for p in files:
        text = p.read_text(encoding='utf-8-sig')
        scope = 'maintained_release' if p.name in maintained else 'excluded_server_managing' if 'php -S' in text else 'supplemental_read_only'
        rows.append({'file': p.relative_to(ROOT).as_posix(), 'scope': scope,
                     'reason': 'Existing release inventory' if scope == 'maintained_release' else 'Starts background servers; some also rewrite a shared mock fixture' if scope == 'excluded_server_managing' else 'Additional standalone test; run unchanged and inspect its assertions and exit semantics'})
    save('test_inventory.json', {'php': rows, 'python': 'unittest discover -s api/tests -p test*.py', 'frontend': 'npm test', 'browser': 'all configured projects and tests, external-server configuration'})
    return rows

def preflight():
    runtime = {'captured_at': datetime.now(timezone.utc).isoformat(), 'python': sys.version, 'services': []}
    for name, command in [('node', ['node', '--version']), ('npm', ['npm.cmd', '--version']), ('php', [PHP, '-v'])]:
        p = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
        runtime[name] = {'exit_code': p.returncode, 'version': p.stdout.splitlines()[0] if p.stdout else p.stderr.strip()}
    for url in ['http://localhost:8080', 'http://127.0.0.1:8081/api/chatbot_health.php', 'http://127.0.0.1:11434/api/tags']:
        item = {'url': url}
        try:
            request = urllib.request.Request(url, headers={'Origin': 'http://localhost:8080'})
            with urllib.request.urlopen(request, timeout=10) as response:
                item.update(status=response.status, content_type=response.headers.get('Content-Type'), cors=response.headers.get('Access-Control-Allow-Origin'))
                if 'json' in (response.headers.get('Content-Type') or ''):
                    item['body'] = json.loads(response.read())
        except Exception as exc:
            item['error'] = str(exc)
        runtime['services'].append(item)
    save('environment.json', runtime)
    inventory()
    files = [ROOT / p for p in json.loads((OUT / 'protected_before.json').read_text())['sha256'] if p.endswith('.php')]
    results = []
    for p in files:
        run = subprocess.run([PHP, '-l', str(p)], cwd=ROOT, capture_output=True, text=True, timeout=30)
        results.append({'file': p.relative_to(ROOT).as_posix(), 'exit_code': run.returncode, 'output': run.stdout + run.stderr})
    save('php_syntax.json', {'total': len(results), 'passed': sum(r['exit_code'] == 0 for r in results), 'results': results})
    print('PHP syntax:', len(results), 'checked;', sum(r['exit_code'] != 0 for r in results), 'failed', flush=True)

def supplemental():
    rows = inventory()
    (OUT / 'supplemental').mkdir(exist_ok=True)
    results = []
    env = os.environ.copy()
    env['PATH'] = str(Path(PHP).parent) + os.pathsep + env.get('PATH', '')
    for row in rows:
        if row['scope'] != 'supplemental_read_only':
            continue
        path = ROOT / row['file']
        log = OUT / 'supplemental' / (path.name + '.log')
        print('START', path.name, flush=True)
        start = time.monotonic()
        with log.open('w', encoding='utf-8') as output:
            try:
                p = subprocess.run([PHP, str(path)], cwd=ROOT, env=env, stdout=output, stderr=subprocess.STDOUT, timeout=600)
                code = p.returncode
            except subprocess.TimeoutExpired:
                code = 'timeout'
        text = log.read_text(encoding='utf-8', errors='replace')
        # Diagnostic detection only; final classifications require reading each failure.
        failure_lines = [line for line in text.splitlines() if re.search(r'\[FAIL\]|^FAIL\b|Fatal error|^FAILED|UNEXPECTED_FAILURE\s*[:=]\s*[1-9]', line, re.I)]
        results.append({**row, 'exit_code': code, 'seconds': round(time.monotonic() - start, 3), 'failure_lines': failure_lines, 'log': log.relative_to(OUT).as_posix()})
        save('supplemental_php.json', {'complete': False, 'results': results})
        print('END', path.name, code, 'failure markers:', len(failure_lines), flush=True)
    save('supplemental_php.json', {'complete': True, 'results': results})

if __name__ == '__main__':
    {'preflight': preflight, 'supplemental': supplemental}[sys.argv[1]]()
