import { test, expect } from '@playwright/test';
import type { Core } from 'cytoscape';
type Container = Element & { _cyreg?: { cy: Core }; removals?: number };
test.describe('Compound interaction stability', () => {
  for (const scenario of [
    { name: 'Copper', id: '#5:24', suffix: '' },
    { name: 'Lead', id: '#5:22', suffix: '' },
    { name: 'filtered Copper', id: '#5:24', suffix: '&hideEdges=UNDERGOES' },
  ]) test(`keeps elements stable and allows real dragging: ${scenario.name}`, async ({ page }) => {
    await page.goto(`/explorer/compound/graph?node=${encodeURIComponent(scenario.id)}&type=Compound${scenario.suffix}`);
    await expect(page.getByRole('heading', { name: scenario.name.replace('filtered ', ''), exact: true })).toBeVisible({ timeout: 20000 });
    const before = await page.evaluate(id => {
      const el = [...document.querySelectorAll('*')].find(el => (el as Container)._cyreg?.cy) as Container;
      const cy = el._cyreg!.cy; const n = cy.$id(id);
      cy.stop(); cy.zoom(1); cy.center(n);
      el.removals = 0; cy.on('remove', 'node', () => { el.removals!++; });
      const rect = el.getBoundingClientRect(); const p = n.renderedPosition();
      return { x: rect.x + p.x, y: rect.y + p.y, position: n.position(), ids: cy.elements().map(e => e.id()).sort() };
    }, scenario.id);
    await page.mouse.move(before.x, before.y);
    await page.mouse.down();
    await page.mouse.move(before.x + 100, before.y + 45, { steps: 15 });
    await page.mouse.up();
    await page.waitForTimeout(500);
    const after = await page.evaluate(id => {
      const el = [...document.querySelectorAll('*')].find(el => (el as Container)._cyreg?.cy) as Container;
      const cy = el._cyreg!.cy;
      return { position: cy.$id(id).position(), removals: el.removals, ids: cy.elements().map(e => e.id()).sort() };
    }, scenario.id);
    expect(after.removals).toBe(0);
    expect(after.ids).toEqual(before.ids);
    expect(Math.hypot(after.position.x-before.position.x,after.position.y-before.position.y)).toBeGreaterThan(40);
  });
  test('node and edge details use matching minimalist typography and rows', async ({ page }) => {
    const graphResponse = page.waitForResponse(r => r.url().includes('/api/graph.php') && r.ok());
    await page.goto('/explorer/compound/graph?node=%2313%3A0&type=Shodhana_Method');
    await expect(page.getByRole('heading', { name: 'Bhavana', exact: true })).toBeVisible({ timeout: 20000 });
    const styles = () => page.locator('dt').last().evaluate(el => {
      const value = el.nextElementSibling!;
      const a=getComputedStyle(el),b=getComputedStyle(value),row=getComputedStyle(el.parentElement!);
      return { labelFont:a.fontFamily,labelSize:a.fontSize,valueFont:b.fontFamily,valueSize:b.fontSize,padding:row.padding,columns:row.gridTemplateColumns,border:row.borderBottom };
    });
    const graph = await (await graphResponse).json();
    const evidenceEdge = graph.edges.find(e => e.display_properties?.length || e.treatment_phase);
    expect(evidenceEdge).toBeTruthy();
    await page.evaluate(() => {
      const host = [...document.querySelectorAll('*')].find(el => (el as Container)._cyreg?.cy) as Container;
      const compound = host._cyreg!.cy.nodes().filter(n => n.data('type') === 'Compound').first();
      if (!compound.length) throw new Error('Expected connected compound');
      compound.emit('tap');
    });
    const node = await styles();
    const endpoints = await page.evaluate(edgeId => {
      const el = [...document.querySelectorAll('*')].find(el => (el as Container)._cyreg?.cy) as Container;
      const edge = el._cyreg!.cy.getElementById(edgeId);
      edge.emit('tap');
      return [edge.source().data('label'), edge.target().data('label')];
    }, evidenceEdge.id);
    for (const label of endpoints) await expect(page.locator('main aside').last().getByText(label, { exact: true }).first()).toBeVisible();
    expect(await styles()).toEqual(node);
    await expect(page.getByRole('link', { name: /Open ID/ }).first()).toBeVisible();
    await page.screenshot({ path: test.info().outputPath('minimalist-edge-panel.png') });
  });
});
