import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const answers = JSON.parse(readFileSync(new URL('../api/tests/fixtures/constructed_answers.json', import.meta.url), 'utf8'));
const pilot = JSON.parse(readFileSync(new URL('../api/tests/fixtures/conversational_pilot_v7.json', import.meta.url), 'utf8'));
const results = JSON.parse(readFileSync(new URL('../api/tests/fixtures/results_first_psoriasis.json', import.meta.url), 'utf8').replace(/^\uFEFF/, ''));

test('Results-first answer groups symptoms and preserves every citation after reload', async ({ page }) => {
  await page.route('**/api/chatbot_health.php', route => route.fulfill({ json: { ok: true } }));
  await page.addInitScript(sample => {
    if (!sessionStorage.getItem('chatbot-messages')) sessionStorage.setItem('chatbot-messages', JSON.stringify([
      { ...sample, role: 'bot', text: sample.answer },
    ]));
  }, results);
  await page.goto('/');
  await page.getByRole('button', { name: 'Open assistant' }).click();
  await expect(page.getByRole('list', { name: 'Retrieved results' }).getByRole('listitem')).toHaveCount(5);
  await page.getByRole('button', { name: 'Show all 19' }).click();
  await expect(page.getByRole('list', { name: 'Retrieved results' }).getByRole('listitem')).toHaveCount(19);
  await expect(page.getByRole('button', { name: /^View source / })).toHaveCount(38);
  await page.getByRole('button', { name: 'View source 38', exact: true }).click();
  const card = page.getByRole('article', { name: 'Evidence record 38', exact: true });
  await expect(card).toBeFocused();
  await expect(card.getByText('Relationship: Disease → Symptom', { exact: true })).toBeVisible();
  await expect(card.locator('pre')).not.toBeVisible();
  await expect(card.getByText('Technical details')).toHaveCount(0);
  expect(await page.evaluate(() => JSON.parse(sessionStorage.getItem('chatbot-messages')!).at(-1).citations)).toEqual(results.citations);
  await page.reload();
  await page.getByRole('button', { name: 'Open assistant' }).click();
  await expect(page.getByRole('button', { name: 'Show all 19' })).toBeVisible();
});

test('Conversational answer opens descriptive citations and restores them after reload', async ({ page }) => {
  const sample = pilot.rows[0];
  expect(sample.context.retrieval.results).toHaveLength(11);
  await page.route('**/api/chatbot_health.php', route => route.fulfill({ json: { ok: true } }));
  await page.addInitScript(sample => {
    if (!sessionStorage.getItem('chatbot-messages')) sessionStorage.setItem('chatbot-messages', JSON.stringify([
      { role: 'user', text: sample.question },
      { role: 'bot', text: sample.response.answer, status: 'success', context: sample.context,
        answer_intro: sample.response.answer_intro, answer_blocks: sample.response.answer_blocks, citations: sample.response.citations },
    ]));
  }, sample);
  await page.goto('/');
  await page.getByRole('button', { name: 'Open assistant' }).click();
  await expect(page.getByText('Graph results for Vipadika', { exact: false })).toHaveCount(0);
  await expect(page.getByRole('button', { name: /^View source / })).toHaveCount(4);
  await page.getByRole('button', { name: 'View source 1', exact: true }).click();
  const first = page.getByRole('article', { name: 'Evidence record 1', exact: true });
  await expect(first.getByText(/Formulation: Sutshekhar Rasa/).first()).toBeVisible();
  await expect(first.getByText(/is recorded as/)).toHaveCount(0);
  // Historical saved answers predate namespace metadata: never invent a PMID link.
  await expect(first.getByText('Reference: 253965', { exact: true })).toBeVisible();
  await expect(first.getByRole('link', { name: /^Open ID/ })).toHaveCount(0);
  await page.getByRole('spinbutton', { name: 'Start at evidence record' }).fill('11');
  await expect(page.getByRole('article', { name: 'Evidence record 11', exact: true })).toBeVisible();
  await page.reload();
  await page.getByRole('button', { name: 'Open assistant' }).click();
  await page.getByRole('button', { name: 'View source 1', exact: true }).click();
  await expect(page.getByRole('article', { name: 'Evidence record 1', exact: true }).getByText(/Formulation: Sutshekhar Rasa/).first()).toBeVisible();
});

test('Chat evidence preserves access to the last record', async ({ page }) => {
  const sample = answers[0];
  await page.route('**/api/chatbot_health.php', route => route.fulfill({ json: { ok: true } }));
  await page.addInitScript(sample => {
    sessionStorage.setItem('chatbot-messages', JSON.stringify([
      { role: 'user', text: sample.question },
      { role: 'bot', text: sample.answer, status: 'success', context: {
        status: 'success', question: sample.question, retrieval: sample.retrieval,
      } },
    ]));
  }, sample);
  await page.goto('/');
  await page.getByRole('button', { name: 'Open assistant' }).click();
  await page.getByRole('button', { name: 'Evidence / Retrieved Knowledge' }).click();
  await expect(page.getByRole('article', { name: 'Evidence record 1', exact: true })).toBeVisible();
  const count = sample.retrieval.results.length;
  await page.getByRole('spinbutton', { name: 'Start at evidence record' }).fill(String(count));
  const last = page.getByRole('article', { name: `Evidence record ${count}`, exact: true });
  await expect(last).toBeVisible();
  await expect(last.getByText('Recorded properties')).toBeVisible();
  await expect(last.getByText('Raw evidence record')).toHaveCount(0);
  expect(await page.evaluate(() => JSON.parse(sessionStorage.getItem('chatbot-messages')!).at(-1).context.retrieval.results)).toEqual(sample.retrieval.results);
  await expect(page.getByRole('button', { name: 'Next records' })).toBeDisabled();
});
