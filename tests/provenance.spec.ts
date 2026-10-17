import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const snapshot = JSON.parse(readFileSync('api/tests/fixtures/factual_graph_snapshot_expanded.json', 'utf8'));
const records = Object.fromEntries(Object.values(snapshot.tables).flat().map((r: Record<string, unknown>) => [r['@rid'], r]));
const seed = (type: string, name: string) => (snapshot.tables[type] as Array<Record<string, string>>).find(r => r.name === name)!['@rid'];
const disease = encodeURIComponent(seed('Disease', 'Vipadika'));
const copper = encodeURIComponent(seed('Compound', 'Copper'));

test.describe('Graph provenance', () => {
  for (const [endpoint, param, id] of [['provenance-subgraph.php', 'diseaseId', disease], ['compound-provenance.php', 'compoundId', copper]]) {
    test(`${endpoint} preserves exact stored edges and references`, async ({ request }) => {
      const response = await request.get(`/api/${endpoint}?${param}=${id}&limit=5000`);
      expect(response.ok()).toBeTruthy();
      const data = await response.json();
      expect(data.paths.length).toBeGreaterThan(0);
      for (const path of data.paths) for (const edge of [path.edge1, path.edge2]) {
        const raw = records[edge.id];
        expect([edge.source, edge.target, edge.raw_type]).toEqual([raw['@out'], raw['@in'], raw['@type']]);
        expect(edge.properties).toEqual(Object.fromEntries(Object.entries(raw).filter(([k]) => !k.startsWith('@'))));
        expect(edge.references.some((r: {raw: string}) => r.raw === raw.ref)).toBeTruthy();
      }
    });
  }
  test('AYUSH evidence remains AYUSH', async ({ request }) => {
    const data = await (await request.get(`/api/provenance-subgraph.php?diseaseId=${disease}`)).json();
    const citation = data.paths.flatMap(p => p.edge1.references).find(r => r.namespace === 'AYUSH');
    expect(citation).toBeDefined();
    for (const path of data.paths) expect(path.edge1.references).toEqual(path.edge2.references);
  });
  test('missing disease gives 404', async ({ request }) => {
    expect((await request.get('/api/provenance-subgraph.php?diseaseId=__missing__')).status()).toBe(404);
  });
  test('missing input gives 400', async ({ request }) => {
    expect((await request.get('/api/provenance-subgraph.php')).status()).toBe(400);
  });
  test('wrong node type cannot masquerade as a disease', async ({ request }) => {
    expect((await request.get(`/api/provenance-subgraph.php?diseaseId=${copper}`)).status()).toBe(404);
  });
  test('disease graph renders with canonical seed ID', async ({ page }) => {
    await page.goto(`/explorer/disease/graph?seeds=${disease}`);
    await expect(page.locator('canvas').first()).toBeVisible({ timeout: 20000 });
  });
  test('Integrated Explorer exposes copper treatment paths and exact sources', async ({ page }) => {
    await page.goto(`/explorer/integrated/graph?seeds=${copper}`);
    const summary = page.locator('summary').filter({ hasText: /^Evidence paths/ });
    await expect(summary).toBeVisible({ timeout: 20000 });
    await summary.click();
    const path = page.locator('summary').filter({ hasText: /Copper.*Eczema/ }).first();
    await expect(path).toBeVisible();
    await path.click();
    await expect(page.getByText('Technical details', { exact: true }).first()).toBeVisible();
  });
  test('Integrated table graph links contain canonical record IDs', async ({ page }) => {
    await page.goto('/explorer/integrated/table');
    const link = page.getByRole('link', { name: /Open graph/ }).first();
    await expect(link).toBeVisible({ timeout: 20000 });
    const url = new URL(await link.getAttribute('href'), page.url());
    const ids = url.searchParams.get('seeds')?.split(',') ?? [];
    expect(ids.length).toBeGreaterThan(0);
    for (const id of ids) {
      expect(id).toMatch(/^#\d+:\d+$/);
      expect(records[id]).toBeDefined();
    }
  });
});
