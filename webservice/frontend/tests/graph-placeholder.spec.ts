import { test, expect } from '@playwright/test';
import { waitForGraphRecord } from './support/graph-ready';
import type { Core } from 'cytoscape';
type Host = Element & { _cyreg?: { cy: Core } };
for (const domain of ['disease', 'integrated']) test(`${domain}: new selection never displays the previous graph while loading`, async ({ page }) => {
  let hold = false;
  let waiting = false;
  let release: () => void;
  const paused = new Promise<void>(resolve => { release = resolve; });
  await page.route('**/api/graph.php?**', async route => {
    if (hold) { waiting = true; await paused; }
    await route.continue();
  });
  const count = () => page.evaluate(() => {
    const host = [...document.querySelectorAll('*')].find(e => (e as Host)._cyreg?.cy) as Host;
    return host?._cyreg?.cy.nodes().length ?? 0;
  });
  await page.goto(`/explorer/${domain}/graph?node=%2345%3A124&type=Disease`, { waitUntil: 'domcontentloaded' });
  await waitForGraphRecord(page, '#45:124');
  await expect.poll(count).toBeGreaterThan(0);
  hold = true;
  await page.evaluate(domain => {
    history.pushState({}, '', `/explorer/${domain}/graph?node=%2345%3A82&type=Disease`);
    window.dispatchEvent(new PopStateEvent('popstate'));
  }, domain);
  try {
    await expect.poll(() => waiting).toBe(true);
    await expect.poll(count).toBe(0);
  } finally { release!(); }
  await waitForGraphRecord(page, '#45:82');
  await expect.poll(count).toBeGreaterThan(0);
  await expect(page.getByRole('button', { name: 'Remove Psoriasis', exact: true })).toBeVisible();
});
