import { waitForGraphRecord } from './support/graph-ready';
import { test, expect } from '@playwright/test';

test('UI fonts and Hindi fallback work with external requests blocked', async ({ page }) => {
  const external: string[] = [];
  await page.route('**/*', route => {
    const url = new URL(route.request().url());
    if (['localhost', '127.0.0.1'].includes(url.hostname)) return route.continue();
    external.push(url.href);
    return route.abort();
  });
  await page.addInitScript(() => localStorage.setItem('shodhana_lang', 'hi'));
  await page.goto('/explorer/compound/graph?node=%235%3A24&type=Compound');
  await waitForGraphRecord(page, '#5:24');
  await expect(page.locator('canvas').first()).toBeVisible();
  expect(await page.evaluate(async () => {
    await document.fonts.load('400 16px Inter');
    await document.fonts.load('600 24px Fraunces');
    return document.fonts.check('400 16px Inter') && document.fonts.check('600 24px Fraunces');
  })).toBe(true);
  await page.goto('/graph_animation.html');
  expect(await page.evaluate(async () => {
    await document.fonts.load('500 16px Manrope');
    return document.fonts.check('500 16px Manrope');
  })).toBe(true);
  expect(external).toEqual([]);
});
