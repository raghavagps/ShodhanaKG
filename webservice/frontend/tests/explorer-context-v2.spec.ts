import { test, expect } from './support/diagnostic-test';
import { waitForGraphRecord } from './support/graph-ready';
import { readFileSync } from 'node:fs';
import { entityType, entityGraphUrl } from '../src/lib/entityNavigation';
import type { Core } from 'cytoscape';
type GraphContainer = Element & { _cyreg?: { cy: Core } };

const snapshot: { tables: Record<string, Array<Record<string, string>>> } = JSON.parse(readFileSync('../api/tests/fixtures/factual_graph_snapshot_expanded.json', 'utf8'));
const samples = Object.entries(snapshot.tables).filter(([, rows]) => rows[0]?.['@cat'] === 'v').map(([type, rows]) => ({ type: entityType(type), id: rows[0]['@rid'], name: rows[0].name }));
test.describe('Explorer context redesign', () => {
  for (const type of ['Compound', 'Disease', 'Symptom', 'PurifiedCompound', 'Media']) test(`Integrated typed selection: ${type}`, async ({ page }) => {
    const node = samples.find(n => n.type === type)!;
    await page.goto(entityGraphUrl(node, 'integrated'), { waitUntil: 'domcontentloaded' });
    await waitForGraphRecord(page, node.id);
    await expect(page.getByRole('heading', { name: node.name, exact: true })).toBeVisible({ timeout: 20000 });
  });
  test('same-view navigation and browser back retain the selected identity', async ({ page }) => {
    const a = samples.find(n => n.type === 'Disease')!;
    const b = samples.find(n => n.type === 'Symptom')!;
    await page.goto(entityGraphUrl(a));
    await expect(page.getByRole('heading', { name: a.name, exact: true })).toBeVisible({ timeout: 20000 });
    await page.evaluate(url => { window.history.pushState({}, '', url); window.dispatchEvent(new PopStateEvent('popstate')); }, entityGraphUrl(b));
    await expect(page.getByRole('heading', { name: b.name, exact: true })).toBeVisible({ timeout: 20000 });
    await page.goBack();
    await expect(page.getByRole('heading', { name: a.name, exact: true })).toBeVisible({ timeout: 20000 });
  });
  test('hidden seed retains its KG label in selected filters', async ({ page }) => {
    const node = samples.find(n => n.type === 'Symptom')!;
    await page.goto(entityGraphUrl(node) + '&hideTypes=Symptom');
    await expect(page.getByRole('button', { name: `Remove ${node.name}`, exact: true }).first()).toBeVisible({ timeout: 20000 });
    const symptom = page.getByRole('checkbox', { name: 'Symptom', exact: true });
    await expect(symptom).not.toBeChecked();
    // URL-backed filters commit after navigation; await the actual checked state.
    await symptom.click();
    await expect(symptom).toBeChecked();
    await expect(page.getByRole('heading', { name: node.name, exact: true })).toBeVisible({ timeout: 20000 });
  });
  for (const node of samples) test(`typed search selection: ${node.type}`, async ({ page }) => {
    test.setTimeout(45000);
    await page.goto(`/search?${new URLSearchParams({ q: node.name, type: node.type })}`, { waitUntil: 'domcontentloaded' });
    const link = page.locator(`a[href="${entityGraphUrl(node)}"]`).first();
    await expect(link).toBeVisible({ timeout: 20000 });
    await link.click();
    await expect(page).toHaveURL(entityGraphUrl(node));
    await expect(page.locator('main aside').last().getByRole('heading', { name: node.name, exact: true })).toBeVisible({ timeout: 20000 });
    await waitForGraphRecord(page, node.id);
    await expect.poll(async () => page.evaluate(id => {
      const container = [...document.querySelectorAll('*')].find(el => (el as GraphContainer)._cyreg?.cy) as GraphContainer;
      const cy = container?._cyreg?.cy;
      return !!cy?.getElementById(id)?.visible();
    }, node.id)).toBe(true);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('main aside').last().getByRole('heading', { name: node.name, exact: true })).toBeVisible({ timeout: 20000 });
    await waitForGraphRecord(page, node.id);
  });
  test('connected edges remain visible when provenance changes', async ({ page }) => {
    await page.goto(entityGraphUrl({ id: '#45:124', type: 'Disease' }));
    await expect(page.getByRole('heading', { name: 'Vipadika', exact: true })).toBeVisible({ timeout: 20000 });
    await expect.poll(async () => page.evaluate(() => {
      const container = [...document.querySelectorAll('*')].find(el => (el as GraphContainer)._cyreg?.cy) as GraphContainer;
      const cy = container?._cyreg?.cy;
      const edges = cy?.getElementById('#45:124').connectedEdges();
      return edges?.length > 0 && edges.every(e => e.visible() && Number(e.style('opacity')) === 1);
    })).toBe(true);
  });
  test('edge property cards preserve the selected edge values', async ({ page, request }, testInfo) => {
    const graph = await (await request.get('http://127.0.0.1:8081/api/graph.php?domain=disease&seeds=%2345%3A124&depth=2&nodeLimit=500&edgeLimit=1000')).json();
    const edge = graph.edges.find((e: { display_properties?: unknown[] }) => e.display_properties?.length);
    expect(edge).toBeTruthy();
    await page.goto(entityGraphUrl({ id: '#45:124', type: 'Disease' }));
    await expect(page.getByRole('heading', { name: 'Vipadika', exact: true })).toBeVisible({ timeout: 20000 });
    await page.evaluate(id => {
      const container = [...document.querySelectorAll('*')].find(el => (el as GraphContainer)._cyreg?.cy) as GraphContainer;
      container._cyreg!.cy.getElementById(id).emit('tap');
    }, edge.id);
    for (const property of edge.display_properties) {
      await expect(page.locator('dt').filter({ hasText: property.label + ':' })).toBeVisible();
      const value = typeof property.value === 'object' ? JSON.stringify(property.value, null, 2) : String(property.value);
      await expect(page.locator('dd').filter({ hasText: value })).toBeVisible();
    }
    await page.screenshot({ path: testInfo.outputPath('edge-properties.png'), fullPage: true });
  });
  test('launcher animates once and never auto-opens', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('button', { name: 'Open assistant' })).toBeVisible();
    await expect.poll(() => page.evaluate(() => sessionStorage.getItem('chatbot-launcher-noticed'))).toBe('true');
    await page.reload();
    await expect(page.getByRole('button', { name: 'Open assistant' })).not.toHaveClass(/chat-launcher-entrance/);
  });
  test('assistant remains accessible on graph routes', async ({ page }) => {
    await page.goto(entityGraphUrl(samples.find(n => n.type === 'Disease')!));
    await page.getByRole('button', { name: 'Open assistant' }).click();
    await expect(page.getByRole('textbox', { name: 'Question', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Close assistant', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Open assistant' })).toBeVisible();
  });
  test('reduced motion skips launcher animation', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    await expect(page.getByRole('button', { name: 'Open assistant' })).not.toHaveClass(/chat-launcher-entrance/);
  });
});
