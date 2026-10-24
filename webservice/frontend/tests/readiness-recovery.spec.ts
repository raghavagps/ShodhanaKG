import {test, expect} from '@playwright/test';
import {waitForGraphRecord} from './support/graph-ready';

test('Graph renderer retains visible geometry after Cytoscape injects its stylesheet', async ({page}) => {
  await page.goto('/explorer/compound/graph?node=%235%3A24&type=Compound', {waitUntil:'domcontentloaded'});
  await waitForGraphRecord(page, '#5:24');
  const host = page.locator('.__________cytoscape_container');
  await expect(host).toHaveCSS('position', 'absolute');
  await expect(page.locator('canvas').first()).toBeVisible();
  const geometry = await host.evaluate(element => ({
    height: element.getBoundingClientRect().height,
    parentHeight: element.parentElement!.getBoundingClientRect().height,
  }));
  expect(geometry.height).toBeGreaterThan(100);
  expect(geometry.height).toBeCloseTo(geometry.parentHeight, 0);
});

test('Chat opening waits for visibility instead of a fixed focus delay', async ({page}) => {
  await page.goto('/', {waitUntil:'domcontentloaded'});
  const blocker = await page.addStyleTag({content:'.kg-chat-panel[data-chat-open=true] { visibility: hidden !important; }'});
  await page.getByRole('button',{name:'Open assistant',exact:true}).click();
  // This test deliberately hides the panel; hidden controls are excluded by default.
  const input = page.getByRole('textbox',{name:'Question',exact:true,includeHidden:true});
  await page.waitForTimeout(650); // Exceeds the former 320 ms one-shot attempt.
  await expect(input).not.toBeFocused();
  await blocker.evaluate(element => element.remove());
  await expect(input).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(page.getByRole('button',{name:'Open assistant',exact:true})).toBeFocused();
});

test('Chat delayed focus never steals a user-selected navigation control', async ({page}) => {
  await page.goto('/', {waitUntil:'domcontentloaded'});
  const blocker = await page.addStyleTag({content:'.kg-chat-panel[data-chat-open=true] { visibility: hidden !important; }'});
  await page.getByRole('button',{name:'Open assistant',exact:true}).click();
  const target=page.locator('header.sticky a').first();
  await target.focus();
  await page.waitForTimeout(100);
  await blocker.evaluate(element=>element.remove());
  await page.waitForTimeout(400);
  await expect(target).toBeFocused();
});

test('Search does not load unrelated page modules before resolving a result', async ({page}) => {
  const unrelated:string[]=[];
  page.on('request',request=>{if (/\/src\/pages\/(?:HowToUse|Team|Contact|explorers\/\w+TableView)\.tsx/.test(request.url())) unrelated.push(request.url());});
  await page.goto('/search?q=Marana&type=Process', {waitUntil:'domcontentloaded'});
  await expect(page.locator('main a[href*="/graph?node="]').first()).toBeVisible();
  expect(unrelated).toEqual([]);
});
