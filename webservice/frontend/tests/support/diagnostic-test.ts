import { test as base, expect } from '@playwright/test';

/** Opt-in observation only: no request interception, retries, or timeout changes. */
export const test = base.extend<{ browserDiagnostics: void }>({
  browserDiagnostics: [async ({ page }, use, testInfo) => {
    if (process.env.SHODHANA_BROWSER_DIAGNOSTICS !== '1') { await use(); return; }
    const started = Date.now();
    const events: Record<string, unknown>[] = [];
    const record = (kind: string, data: Record<string, unknown>) => events.push({ elapsed_ms: Date.now() - started, kind, ...data });
    page.on('console', message => record('console', { level: message.type(), text: message.text() }));
    page.on('pageerror', error => record('pageerror', { message: error.message, stack: error.stack }));
    page.on('crash', () => record('crash', {}));
    page.on('requestfailed', request => record('requestfailed', { url: request.url(), method: request.method(), failure: request.failure() }));
    page.on('response', response => record('response', { url: response.url(), status: response.status() }));
    page.on('requestfinished', request => record('requestfinished', { url: request.url(), timing: request.timing() }));
    page.on('framenavigated', frame => { if (frame === page.mainFrame()) record('navigation', { url: frame.url() }); });
    try { await use(); }
    finally {
      record('test_end', { status: testInfo.status, url: page.isClosed() ? 'closed' : page.url() });
      await testInfo.attach('browser-diagnostics', { body: Buffer.from(JSON.stringify(events, null, 2)), contentType: 'application/json' });
    }
  }, { auto: true }],
});

export { expect };
