import { test, expect } from '@playwright/test';
import { waitForGraphRecord } from './support/graph-ready';
import type { Core } from 'cytoscape';
import { readFileSync } from 'node:fs';
const styles: Record<string, { normal: string; accessible: string }> = JSON.parse(readFileSync('src/data/nodeStyles.json', 'utf8'));
type Container = Element & { _cyreg?: { cy: Core } };
const rgb = (color: string) => {
  if (color.startsWith('#')) return `rgb(${color.slice(1).match(/../g)!.map(s => parseInt(s, 16)).join(',')})`;
  const [h, s, l] = color.match(/[\d.]+/g)!.map(Number);
  const a = s / 100 * Math.min(l / 100, 1 - l / 100);
  const f = (n: number) => { const k = (n + h / 30) % 12; return Math.round(255 * (l / 100 - a * Math.max(-1, Math.min(k - 3, 9 - k, 1)))); };
  return `rgb(${f(0)},${f(8)},${f(4)})`;
};

test.describe('Node category accessibility', () => {
  for (const domain of ['integrated', 'compound']) test(`${domain} supported categories switch together without rebuilding the graph or losing identity`, async ({ page }) => {
    const count = domain === 'integrated' ? 15 : 10;
    // Synthetic rendering fixture, not factual KG ground truth or a retrieval test.
    const nodes = Object.keys(styles).map((type, i) => ({ id: type === 'Compound' ? '#5:24' : `ui-${i}`, type, label: `UI fixture: ${type}`, description: '' }));
    const edges = nodes.filter(n => n.id !== '#5:24').map((n, i) => ({ id: `ui-edge-${i}`, source: '#5:24', target: n.id, type: 'UNDERGOES' }));
    await page.route('**/api/graph.php?**', route => route.fulfill({ json: { ok: true, nodes, edges, meta: {} } }));
    await page.goto(`/explorer/${domain}/graph?node=%235%3A24&type=Compound&integratedMode=1hop`);
    await expect.poll(() => page.evaluate(() => {
      const cy = ([...document.querySelectorAll('*')].find(e => (e as Container)._cyreg?.cy) as Container)?._cyreg?.cy;
      return cy?.nodes().length;
    })).toBe(count);
    await page.evaluate(() => {
      const cy = ([...document.querySelectorAll('*')].find(e => (e as Container)._cyreg?.cy) as Container)._cyreg!.cy;
      cy.stop(); cy.elements().removeClass('faded'); cy.$id('#5:24').select();
      (window as unknown as { removals: number }).removals = 0;
      cy.on('remove', () => { (window as unknown as { removals: number }).removals++; });
    });
    const snapshot = () => page.evaluate(() => {
      const cy = ([...document.querySelectorAll('*')].find(e => (e as Container)._cyreg?.cy) as Container)._cyreg!.cy;
      return { nodes: cy.nodes().map(n => ({ id: n.id(), type: n.data('type'), position: n.position(), fill: n.style('background-color'), shape: n.style('shape'), border: n.style('border-style'), selected: n.selected(), width: n.style('width'), height: n.style('height'), font: n.style('font-size'), borderWidth: n.style('border-width'), label: n.style('label') })), edges: cy.edges().map(e => e.data()), zoom: cy.zoom(), pan: cy.pan(), removals: (window as unknown as { removals: number }).removals };
    });
    // GraphCanvas transitions selected sizes and highlighted borders for 150ms.
    // Wait for all geometry to settle together across successive samples;
    // checking one property can catch another transition between frames.
    let settledSince = 0;
    await expect.poll(async () => {
      const settled = await page.evaluate(() => {
        const cy = ([...document.querySelectorAll('*')].find(e => (e as Container)._cyreg?.cy) as Container)._cyreg!.cy;
        return cy.$id('#5:24').selected() && !cy.animated() && cy.nodes().every(n =>
          !n.animated() && n.numericStyle('width') === (n.selected() ? 40 : 34)
          && n.numericStyle('height') === (n.selected() ? 40 : 34)
          && n.numericStyle('border-width') === (n.selected() || n.hasClass('highlighted') ? 3 : 2));
      });
      if (!settled) settledSince = 0;
      else if (!settledSince) settledSince = Date.now();
      return settled && Date.now() - settledSince >= 300;
    }).toBe(true);
    const before = await snapshot();
    if (domain === 'integrated') {
      expect(before.nodes.map(n => n.type).sort()).toEqual(Object.keys(styles).filter(t => !['Indication', 'AyurvedicFormulation'].includes(t)).sort());
    } else {
      expect(before.nodes.some(n => n.type === 'Indication')).toBe(true);
      expect(before.nodes.some(n => n.type === 'AyurvedicFormulation')).toBe(true);
    }
    for (const n of before.nodes) {
      // Cytoscape and browser HSL conversion may differ by one RGB rounding unit.
      const expected = rgb(styles[n.type as keyof typeof styles].normal).match(/\d+/g)!.map(Number);
      n.fill.match(/\d+/g)!.map(Number).forEach((channel, i) => expect(Math.abs(channel - expected[i])).toBeLessThanOrEqual(1));
      expect(n.shape).toBe('ellipse');
      expect(n.border).toBe('solid');
    }
    await page.screenshot({ path: test.info().outputPath('supported-categories-normal.png') });
    await page.getByRole('button', { name: /Colorblind Mode|Color Blind Mode/i }).click();
    await expect.poll(async () => (await snapshot()).nodes.find(n => n.type === 'Compound')?.fill).toBe(rgb(styles.Compound.accessible));
    const after = await snapshot();
    expect(after.removals).toBe(0);
    expect(after.edges).toEqual(before.edges);
    expect(after.zoom).toBe(before.zoom); expect(after.pan).toEqual(before.pan);
    for (const n of after.nodes) {
      const previous = before.nodes.find(v => v.id === n.id)!;
      expect({ ...n, fill: previous.fill }).toEqual(previous);
      expect(n.fill).toBe(rgb(styles[n.type as keyof typeof styles].accessible));

    }
    await page.screenshot({ path: test.info().outputPath('supported-categories-accessible.png') });
    await page.reload();
    await expect.poll(() => page.evaluate(() => ([...document.querySelectorAll('*')].find(e => (e as Container)._cyreg?.cy) as Container)?._cyreg?.cy.nodes().length)).toBe(count);
    await expect.poll(async () => (await snapshot()).nodes.find(n => n.type === 'Compound')?.fill).toBe(rgb(styles.Compound.accessible));
  });

  for (const [domain, id, type] of [['disease','#45:124','Disease'], ['compound','#5:24','Compound']]) {
    test(`${domain} real graph keeps shapes and fills on selection and hover`, async ({ page }) => {
      await page.goto(`/explorer/${domain}/graph?node=${encodeURIComponent(id)}&type=${type}`, { waitUntil: 'domcontentloaded' });
      await waitForGraphRecord(page, id);
      await expect.poll(() => page.evaluate(() => !!([...document.querySelectorAll('*')].find(e => (e as Container)._cyreg?.cy) as Container)?._cyreg?.cy.nodes().length)).toBe(true);
      const result = await page.evaluate(() => {
        const cy = ([...document.querySelectorAll('*')].find(e => (e as Container)._cyreg?.cy) as Container)._cyreg!.cy;
        const changed: string[] = [];
        for (const n of cy.nodes().toArray()) {
          const before = [n.style('background-color'), n.style('shape'), n.style('border-style')];
          n.emit('tap'); n.emit('mouseover'); n.select();
          const after = [n.style('background-color'), n.style('shape'), n.style('border-style')];
          if (JSON.stringify(before) !== JSON.stringify(after)) changed.push(n.id());
          n.emit('mouseout'); n.unselect();
        }
        return changed;
      });
      expect(result).toEqual([]);
      await page.getByRole('button', { name: /Colorblind Mode|Color Blind Mode/i }).click();
      await page.screenshot({ path: test.info().outputPath(`${domain}-accessible.png`) });
    });
  }
});
