import { completedChatResponse } from './support/chat-response';
import { test, expect } from '@playwright/test';
import type { Core } from 'cytoscape';
type Container = Element & { _cyreg?: { cy: Core } };
test.describe('Fragment routing and Compound graph', () => {
  test('edge visibility removes disconnected neighbors but retains the selected seed', async ({ page }) => {
    await page.goto('/explorer/compound/graph?node=%235%3A24&type=Compound&hideEdges=UNDERGOES');
    await expect(page.getByRole('heading', { name: 'Copper', exact: true })).toBeVisible({ timeout: 20000 });
    await expect.poll(() => page.evaluate(() => {
      const el = [...document.querySelectorAll('*')].find(el => (el as Container)._cyreg?.cy) as Container;
      const cy = el?._cyreg?.cy;
      if (!cy?.nodes().length) return false;
      return cy.edges().every(e => e.data('type') !== 'UNDERGOES') && cy.nodes().every(n => n.id() === '#5:24' || n.degree(false) > 0);
    })).toBe(true);
    await page.screenshot({ path: test.info().outputPath('compound-filtered.png') });
  });
  test('new selection never displays the previous graph while its API request is pending', async ({ page }) => {
    await page.goto('/explorer/compound/graph?node=%235%3A24&type=Compound');
    await expect(page.getByRole('heading', { name: 'Copper', exact: true })).toBeVisible({ timeout: 20000 });
    let release!: () => void;
    const gate = new Promise<void>(resolve => { release = resolve; });
    let started!: () => void;
    const pending = new Promise<void>(resolve => { started = resolve; });
    await page.route('**/api/graph.php?**', async route => {
      if (new URL(route.request().url()).searchParams.get('seeds')?.includes('#5:22')) { started(); await gate; }
      await route.continue();
    });
    await page.evaluate(() => { history.pushState({}, '', '/explorer/compound/graph?node=%235%3A22&type=Compound'); dispatchEvent(new PopStateEvent('popstate')); });
    await pending;
    try {
      await expect.poll(() => page.evaluate(() => {
        const el = [...document.querySelectorAll('*')].find(el => (el as Container)._cyreg?.cy) as Container;
        return el?._cyreg?.cy.$id('#5:24').length ?? 0;
      })).toBe(0);
    } finally { release(); }
    await expect(page.getByRole('heading', { name: 'Lead', exact: true })).toBeVisible({ timeout: 20000 });
  });
  for (const entity of ['Lead', 'Copper', 'Sulphur']) test(`fragment requests methods for ${entity}`, async ({ page }) => {
    // Interact with real controls without waiting for decorative iframe assets.
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Open assistant' }).click();
    await page.getByRole('textbox', { name: 'Question', exact: true }).fill(`Shodhana method to purify ${entity}.`);
    await page.getByRole('textbox', { name: 'Question', exact: true }).press('Enter');
    const done = await completedChatResponse(page);
    expect(done.status).toBe('success');
    const context = done.context;
    expect(context.routing.endpoint).toBe('compound_methods');
    expect(context.retrieval.results.length).toBeGreaterThan(0);
    expect(context.retrieval.results.every(r => r.nodes.at(-1).type === 'Shodhana_Method')).toBe(true);
  });
});
