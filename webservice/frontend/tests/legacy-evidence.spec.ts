import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const capture = JSON.parse(readFileSync(new URL('../../api/tests/fixtures/results_legacy_evidence_answers_v1.json', import.meta.url), 'utf8'));

for (const endpoint of ['compound_media', 'process_compound']) {
  test(`Legacy per-edge evidence stays attributed and raw details remain preserved in message data: ${endpoint}`, async ({ page }) => {
    const sample = capture.rows.find(row => row.response.context.routing.endpoint === endpoint).response;
    expect(sample.citations.every(c => c.attribution === 'per_edge')).toBeTruthy();
    await page.route('**/api/chatbot_health.php', route => route.fulfill({ json: { ok: true } }));
    await page.addInitScript(answer => {
      sessionStorage.setItem('chatbot-messages', JSON.stringify([{ ...answer, role: 'bot', text: answer.answer }]));
    }, sample);
    await page.goto('/');
    await page.getByRole('button', { name: 'Open assistant' }).click();
    await page.getByRole('button', { name: 'View source 1', exact: true }).click();
    const card = page.getByRole('article', { name: 'Evidence record 1', exact: true });
    await expect(card).toBeFocused();
    for (const scope of sample.citations[0].scopes) {
      await expect(card.getByText(`Relationship: ${scope.relationship}`, { exact: true }).first()).toBeVisible();
      for (const entity of scope.entities) {
        await expect(card.getByText(`${entity.type.replace(/_/g, ' ')}: ${entity.label}`, { exact: true }).first()).toBeVisible();
      }
    }
    await expect(card.locator('pre')).not.toBeVisible();
    await expect(card.getByText('Technical details', { exact: true })).toHaveCount(0);
    const saved = await page.evaluate(() => JSON.parse(sessionStorage.getItem('chatbot-messages')!));
    expect(saved.at(-1).citations).toEqual(sample.citations);
    expect(saved.at(-1).context.retrieval.results).toEqual(sample.context.retrieval.results);
    await page.getByRole('button', { name: 'Back to chat' }).click();
    const all = page.getByRole('button', { name: /^Show all [0-9]+$/ });
    if (await all.count()) await all.click();
    const last = sample.citations.length;
    await page.getByRole('button', { name: `View source ${last}`, exact: true }).click();
    await expect(page.getByRole('article', { name: `Evidence record ${last}`, exact: true })).toBeFocused();
  });
}
