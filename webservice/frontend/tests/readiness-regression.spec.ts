import { test, expect } from '@playwright/test';
import { waitForGraphRecord } from './support/graph-ready';
import { completedChatResponse } from './support/chat-response';

test('Graph readiness waits for the exact record beyond the loading badge', async ({ page }) => {
  let release!: () => void;
  const gate = new Promise<void>(resolve => { release = resolve; });
  await page.route('**/api/graph.php?**', async route => {
    await gate;
    await route.continue();
  });
  await page.goto('/explorer/disease/graph?node=%2345%3A124&type=Disease', { waitUntil: 'domcontentloaded' });
  await expect(page.getByText(/Nodes \d+\/\d+.*Edges/)).toBeVisible();
  let ready = false;
  const pending = waitForGraphRecord(page, '#45:124').then(() => { ready = true; });
  try {
    // Deliberately exceed the old incidental five-second graph poll. This delay
    // simulates slow data; the response and all KG records remain unmodified.
    await page.waitForTimeout(5500);
    expect(ready).toBe(false);
  } finally { release(); }
  await pending;
  expect(ready).toBe(true);
  await expect(page.locator('main aside').last().getByRole('heading', { name: 'Vipadika', exact: true })).toBeVisible();
});

test('Chat and search work while the decorative homepage iframe is pending', async ({ page }) => {
  let release!: () => void;
  const gate = new Promise<void>(resolve => { release = resolve; });
  let started!: () => void;
  const requested = new Promise<void>(resolve => { started = resolve; });
  await page.route('**/graph_animation.html', async route => {
    started();
    await gate;
    await route.continue();
  });
  try {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await requested;
    await page.getByRole('button', { name: 'Open assistant' }).click();
    const question = page.getByRole('textbox', { name: 'Question', exact: true });
    await question.fill('Shodhana method to purify Sulphur.');
    await question.press('Enter');
    const answer = await completedChatResponse(page);
    expect(answer.status).toBe('success');
    expect(answer.context.routing.endpoint).toBe('compound_methods');
    expect(answer.context.retrieval.results.length).toBeGreaterThan(0);
    expect(answer.context.retrieval.results.every(r => r.nodes.at(-1).type === 'Shodhana_Method')).toBe(true);
    await page.keyboard.press('Escape');
    for (const name of ['Total Nodes', 'Total Relations', 'Total Studies']) {
      await expect(page.getByText(name, { exact: true })).toBeVisible();
    }
    await page.getByRole('link', { name: 'Advanced Search', exact: true }).click();
    await page.getByRole('button', { name: 'Next', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Page 2', exact: true })).toHaveAttribute('aria-current', 'page');
  } finally { release(); }
});
