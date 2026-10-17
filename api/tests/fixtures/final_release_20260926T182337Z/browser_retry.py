"""Audit-only unchanged browser suite retry with reduced worker concurrency."""
import json, os, subprocess, time
from pathlib import Path
out = Path(__file__).resolve().parent
root = out.parents[3]
env = os.environ.copy()
helper = root / 'tests/support/webkit-pipe-cleanup.cjs'
env['NODE_OPTIONS'] = (env.get('NODE_OPTIONS', '') + ' --require "' + helper.as_posix() + '"').strip()
env['SHODHANA_WEBKIT_PIPE_CLEANUP'] = '1'
env['PLAYWRIGHT_JSON_OUTPUT_FILE'] = str(out / 'browser_retry.json')
command = ['npx.cmd', 'playwright', 'test', '--config', 'playwright.external.config.ts', '--workers=1', '--reporter=line,json', '--output', str(out / 'browser-retry-artifacts')]
start = time.monotonic()
with (out / 'browser_retry.log').open('w', encoding='utf-8') as log:
    p = subprocess.run(command, cwd=root, env=env, stdout=log, stderr=subprocess.STDOUT)
(out / 'browser_retry_exit.json').write_text(json.dumps({'command': command, 'exit_code': p.returncode, 'seconds': round(time.monotonic()-start, 3), 'reason': 'Initial two-worker run aborted with memory allocation and browser-launch errors; unchanged tests retried with one worker.'}, indent=2))
print('Browser retry exit:', p.returncode, flush=True)
