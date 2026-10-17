const { chromium } = require('@playwright/test');

async function test() {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  
  await page.goto('http://localhost:8080/help');
  await page.click('button[value="integrated-explorer"]');
  await page.waitForTimeout(1000); // Wait for transition
  await page.screenshot({ path: 'integrated_explorer_test.png', fullPage: true });
  
  await browser.close();
}

test();
