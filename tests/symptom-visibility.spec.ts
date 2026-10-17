import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import type { Core } from 'cytoscape';
import { ALL_INTEGRATED_EDGE_TYPES as integratedEdges } from '../src/data/integratedGraph';

const snapshot = JSON.parse(readFileSync('api/tests/fixtures/factual_graph_snapshot_expanded.json', 'utf8'));
const disease = snapshot.tables.Disease.find(r => r.name === 'Psoriasis')['@rid'];
const symptomEdges = snapshot.tables.HAS_SYMPTOM.filter(r => r['@out'] === disease);
const symptomIds = [...new Set(symptomEdges.map(r => r['@in']))].sort();

test('Symptom visibility: graph API preserves the symptom relationship contract', async ({ request }) => {
  expect(integratedEdges).toContain('HAS_SYMPTOM');
  for (const domain of ['disease', 'integrated']) {
  const response = await request.get(`/api/graph.php?domain=${domain}&integratedMode=1hop&seeds=${encodeURIComponent(disease)}`);
  expect(response.ok()).toBeTruthy();
  const graph = await response.json();
  expect(graph.nodes.filter(n => n.type === 'Symptom').map(n => n.id).sort()).toEqual(symptomIds);
  expect(graph.edges.filter(e => e.type === 'HAS_SYMPTOM').map(e => e.id).sort())
    .toEqual(symptomEdges.map(e => e['@rid']).sort());
  }
});

test('Symptom visibility: Disease Explorer actually displays connected symptoms', async ({ page }) => {
  await page.goto(`/explorer/disease/graph?seeds=${encodeURIComponent(disease)}`);
  await expect(page.locator('canvas').first()).toBeVisible({ timeout: 20000 });
  await expect.poll(() => page.evaluate(() => {
    const containers = Array.from(document.querySelectorAll('div')) as (HTMLDivElement & { _cyreg?: { cy: Core } })[];
    const cy = containers.find(el => el._cyreg?.cy)?._cyreg?.cy;
    return cy ? cy.nodes().filter(n => n.data('type') === 'Symptom' && n.visible() && n.degree() > 0)
      .map(n => n.id()).sort() : [];
  })).toEqual(symptomIds);
});
