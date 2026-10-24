import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

// Expectations are emitted by the independent frozen-graph evaluator, not by
// the chatbot. Captured responses are inputs to the real production UI.
const captured = JSON.parse(readFileSync(new URL('../../api/tests/fixtures/results_evaluation_answers_v2.json', import.meta.url), 'utf8'));
const report = JSON.parse(readFileSync(new URL('../../api/tests/fixtures/results_evaluation_report_v2.json', import.meta.url), 'utf8'));
const chains: Record<string, string> = {
  eval_legacy_factual_compound_shodhana_process: 'Compound → Shodhana Method; Shodhana Method → Media',
  eval_legacy_factual_media_compound: 'Shodhana Method → Media; Compound → Shodhana Method',
  eval_legacy_factual_disease_formulation: 'Disease → Therapy; Therapy → Formulation',
  eval_legacy_factual_formulation_disease: 'Therapy → Formulation; Disease → Therapy',
};

for (const reference of report.results) {
  test(`Independent results rendering: ${reference.id}`, async ({ page }) => {
    const sample = captured.rows.find(row => row.id === reference.id).response;
    await page.route('**/api/chatbot_health.php', route => route.fulfill({ json: { ok: true } }));
    await page.addInitScript(sample => {
      sessionStorage.setItem('chatbot-messages', JSON.stringify([
        { role: 'user', text: sample.question },
        { ...sample, role: 'bot', text: sample.answer },
      ]));
    }, sample);
    await page.goto('/');
    await page.getByRole('button', { name: 'Open assistant' }).click();
    const list = page.getByRole('list', { name: 'Retrieved results' });
    await expect(list.getByRole('listitem')).toHaveCount(Math.min(5, reference.expected_unique_entities));
    if (reference.expected_unique_entities > 5) {
      await page.getByRole('button', { name: `Show all ${reference.expected_unique_entities}`, exact: true }).click();
    }
    await expect(list.getByRole('listitem')).toHaveCount(reference.expected_unique_entities);
    const labels = await list.locator(':scope > li > button > span:first-child').allTextContents();
    expect(labels.sort()).toEqual(reference.expected_entities.map(entity => entity.label).sort());
    await expect(list.getByRole('button', { name: /^View source / })).toHaveCount(reference.expected_evidence_records);
    await list.getByRole('button', { name: `View source ${reference.expected_evidence_records}`, exact: true }).click();
    const last = page.getByRole('article', { name: `Evidence record ${reference.expected_evidence_records}`, exact: true });
    await expect(last).toBeFocused();
    if (chains[reference.id]) {
      await expect(last.getByText(`Relationship: ${chains[reference.id]}`, { exact: true }).first()).toBeVisible();
    }
    await expect(last.getByText('Technical details')).toHaveCount(0);
    const citation = sample.citations.find(c => c.record_indices.includes(reference.expected_evidence_records - 1));
    for (const scope of citation.scopes) {
      for (const entity of scope.entities ?? []) await expect(last.getByText(`${entity.type.replace(/_/g, ' ')}: ${entity.label}`, { exact: true }).first()).toBeVisible();
    }
    const saved = await page.evaluate(() => JSON.parse(sessionStorage.getItem('chatbot-messages')!));
    expect(saved.at(-1).citations).toEqual(sample.citations);
    expect(saved.at(-1).context.retrieval.results).toEqual(sample.context.retrieval.results);
    await expect(last.locator('pre')).not.toBeVisible();
  });
}
