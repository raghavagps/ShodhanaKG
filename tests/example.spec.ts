import { test, expect } from '@playwright/test';
test('local landing page exposes all three Explorer entry points', async ({ page }) => {
  await page.goto('/');
  for (const mode of ['Disease Explorer', 'Compound Explorer', 'Integrated Explorer']) {
    await expect(page.getByRole('heading', { name: mode, exact: true })).toBeVisible();
  }
});
