import { test, expect } from '@playwright/test';

test('Explicit topic switch overrides the previous entity in a live conversation', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('button', { name: 'Open assistant' }).click();
  for (const [question, heading] of [
    ['Which media are used by the Shodhana method Swedana?', 'Media associated with Swedana'],
    ['Switch to method Bharjana. What media does it use?', 'Media associated with Bharjana'],
    ['Which compounds are processed by it?', 'Compounds associated with Bharjana'],
  ]) {
    const input = page.getByRole('textbox', { name: 'Question', exact: true });
    await expect(input).toBeEnabled();
    await input.fill(question);
    await input.press('Enter');
    await expect(page.getByText(heading, { exact: true })).toBeVisible();
  }
});

test('Realistic conversation sends actual history through streaming and retains the disease topic', async ({ page }) => {
  const requests: { question: string; history: { role: string; text: string }[]; stream: boolean }[] = [];
  page.on('request', request => {
    if (request.url().includes('/api/chatbot_response.php') && request.method() === 'POST') requests.push(request.postDataJSON());
  });
  await page.goto('/');
  await page.getByRole('button', { name: 'Open assistant' }).click();
  const turns = [
    ['What symptoms are associated with Acute Renal Calculus?', 'Symptoms associated with Acute Renal Calculus'],
    ['What therapies are used for it?', 'Therapies associated with Acute Renal Calculus'],
    ['What formulations are used for it?', 'Formulations associated with Acute Renal Calculus'],
  ];
  for (const [question, heading] of turns) {
    const input = page.getByRole('textbox', { name: 'Question', exact: true });
    await expect(input).toBeEnabled();
    await input.fill(question);
    await input.press('Enter');
    await expect(page.getByText(heading, { exact: true })).toBeVisible();
  }
  expect(requests).toHaveLength(3);
  expect(requests.every(r => r.stream)).toBeTruthy();
  expect(requests[0].history).toEqual([]);
  expect(requests[1].history.map(t => t.role)).toEqual(['user', 'bot']);
  expect(requests[1].history[0].text).toBe(turns[0][0]);
  expect(requests[1].history[1].text).toContain(turns[0][1]);
  expect(requests[2].history.map(t => t.role)).toEqual(['user', 'bot', 'user', 'bot']);
  expect(requests[2].history[3].text).toContain(turns[1][1]);
});
