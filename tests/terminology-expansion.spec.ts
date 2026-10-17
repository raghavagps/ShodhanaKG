import { completedChatResponse } from './support/chat-response';
import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

test.describe('Terminology expansion', () => {
  test('broader category preserves the selected disease type', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('button', { name: 'Open assistant' }).click();
    const input = page.getByRole('textbox', { name: 'Question', exact: true });
    await input.fill('What symptoms occur with Autoimmune Disorder?');
    await input.press('Enter');
    await page.getByRole('button', { name: /Rheumatoid Arthritis.*Disease/ }).click({ timeout: 20000 });
    await expect(page.getByRole('list', { name: 'Retrieved results' })).toBeVisible({ timeout: 20000 });
  });
  test('a KG abbreviation does not manufacture a node description', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('button', { name: 'Open assistant' }).click();
    const input = page.getByRole('textbox', { name: 'Question', exact: true });
    await input.fill('Describe NMR');
    await input.press('Enter');
    await page.getByRole('button', { name: /Nuclear Magnetic Resonance.*Confirmation Study/i }).click({ timeout: 20000 });
    const response = await completedChatResponse(page);
    expect(response.status).toBe('success');
    const evidence = response.context.node_answer.node_evidence;
    const tables = JSON.parse(readFileSync('api/tests/fixtures/factual_graph_snapshot_expanded.json', 'utf8')).tables;
    const node = Object.values(tables).flat().find((n: Record<string, unknown>) => n['@rid'] === evidence.node_id) as Record<string, unknown>;
    expect(node).toBeTruthy();
    expect(evidence.node_id).toBe('#29:34');
    expect(node.name).toBe('Nuclear Magnetic Resonance (NMR)');
    expect(evidence.node_type).toBe('Confirmation_Study');
    const properties = Object.fromEntries(Object.entries(node).filter(([key, value]) => !/^[@_]|^(in|out)(_|$)/i.test(key) && value !== null && value !== '' && !(Array.isArray(value) && !value.length)));
    expect(evidence.properties).toEqual(properties);
    expect(evidence.requested_property).toBe('*');
    expect(response.citations).toEqual([]);
  });
});
