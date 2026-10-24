import { completedChatResponse } from './support/chat-response';
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import type { Core } from 'cytoscape';
type Container = Element & { _cyreg?: { cy: Core } };
const snapshot = JSON.parse(readFileSync('../api/tests/fixtures/ui_result_contract_2026_09_25_v1/baseline/snapshot.json', 'utf8'));

test.describe('UI result contract', () => {
  for (const reduced of [false, true]) test(`greeting once without opening panel; reduced motion=${reduced}`, async ({ page }) => {
    if (reduced) await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const greeting = page.getByRole('button', { name: 'Hello! How can I help you?' });
    await expect(greeting).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open assistant' })).toBeVisible();
    await expect(page.getByRole('textbox', { name: 'Question', exact: true })).not.toBeVisible();
    await page.getByRole('button', { name: 'Dismiss greeting' }).click();
    await expect(greeting).not.toBeVisible();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);
    await expect(greeting).not.toBeVisible();
  });
  test('all Disease edges stay opaque after selection and filtering', async ({ page }) => {
    await page.goto('/explorer/disease/graph?node=%2345%3A124&type=Disease');
    await expect(page.getByRole('heading', { name: 'Vipadika', exact: true })).toBeVisible({ timeout: 20000 });
    const opaque = () => page.evaluate(() => {
      const el = [...document.querySelectorAll('*')].find(el => (el as Container)._cyreg?.cy) as Container;
      const edges = el?._cyreg?.cy.edges();
      return !!edges?.length && edges.every(e => Number(e.style('opacity')) === 1 && !e.hasClass('faded') && !e.hasClass('provenance-highlight-dim'));
    });
    await expect.poll(opaque).toBe(true);
    await page.evaluate(() => {
      const el = [...document.querySelectorAll('*')].find(el => (el as Container)._cyreg?.cy) as Container;
      el._cyreg!.cy.nodes().last().emit('tap');
    });
    await expect.poll(opaque).toBe(true);
    const symptom = page.getByRole('checkbox', { name: 'Symptom', exact: true });
    await expect(symptom).toBeChecked();
    await symptom.click();
    await expect(symptom).not.toBeChecked();
    await expect.poll(opaque).toBe(true);
    await symptom.click();
    await expect(symptom).toBeChecked();
    await expect.poll(opaque).toBe(true);
  });
  for (const question of ['What Shodhana method is used to purify Copper?', 'Which purification technique is used for Copper?', 'Name the methods used to purify Copper.']) test(`fresh browser methods: ${question}`, async ({ page }) => {
    await page.goto('/');
    await page.getByRole('button', { name: 'Open assistant' }).click();
    await page.getByRole('textbox', { name: 'Question', exact: true }).fill(question);
    await page.getByRole('textbox', { name: 'Question', exact: true }).press('Enter');
    const done = await completedChatResponse(page);
    expect(done.context.routing.endpoint).toBe('compound_methods');
    const copper = snapshot.tables.Compound.find(n => n.name === 'Copper');
    const ids = new Set(snapshot.tables.UNDERGOES.filter(e => e['@out'] === copper['@rid']).map(e => e['@in']));
    const expected = snapshot.tables.Shodhana_Method.filter(n => ids.has(n['@rid'])).map(n => n.name).sort();
    expect(done.status).toBe('success');
    expect(done.answer_results.map(n => n.label).sort()).toEqual(expected);
    for (const name of expected) await expect(page.getByText(name, { exact: true }).first()).toBeVisible();
  });
  test('schema lists 21 relationships and stored properties', async ({ page }) => {
    await page.goto('/how-to-use');
    await page.getByRole('tab', { name: /Schema/ }).click();
    const panel = page.getByRole('tabpanel');
    await expect(panel.getByText('Edge Interaction Types (21 Relationships)')).toBeVisible();
    await expect(panel.getByText('RESOLVED_BY', { exact: true })).toHaveCount(0);
    await expect(panel.getByText(/KG entity category/)).toHaveCount(0);
    await expect(panel.locator('tbody').nth(1).locator('tr')).toHaveCount(21);
    await expect(panel.locator('tr').filter({ hasText: 'USES_FORMULATION' })).toContainText('Adjuvant, Dose, Duration, ref (Source ID)');
  });
});
