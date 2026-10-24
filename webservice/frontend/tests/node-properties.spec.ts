import { test, expect } from '@playwright/test';

test('Node properties: stored description and missing-field follow-up', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: 'Open assistant' }).click();
  const input = page.getByRole('textbox', { name: 'Question', exact: true });
  await input.fill('Describe Psoriasis');
  await input.press('Enter');
  await expect(page.getByText(/Psoriasis is a chronic immune-mediated/)).toBeVisible({ timeout: 20000 });
  await expect(page.getByRole('list', { name: 'Retrieved results' })).toHaveCount(0);
  await input.fill('What is its molecular weight?');
  await input.press('Enter');
  await expect(page.getByText(/No information is available for molecular weight on the Psoriasis node/)).toBeVisible({ timeout: 20000 });
});
