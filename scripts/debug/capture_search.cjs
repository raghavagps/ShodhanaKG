const { chromium } = require('@playwright/test');
const path = require('path');

async function capture() {
  console.log("Launching headless browser...");
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 }
  });
  const page = await context.newPage();

  const wait = (ms) => new Promise(resolve => setTimeout(resolve, ms));

  // Navigate to homepage to set localStorage
  console.log("Setting language to English...");
  await page.goto('http://localhost:8080/');
  await page.evaluate(() => {
    localStorage.setItem('shodhana_lang', 'en');
  });

  const targetDir = 'D:/ShodhanaUI/shodhanakg-explorer-suite-main - final-copy/public/images/help';
  console.log(`Screenshots will be saved to: ${targetDir}`);

  // Capture Search Page
  console.log("Capturing Search Page...");
  await page.goto('http://localhost:8080/search?q=eczema');
  await wait(3000);
  await page.screenshot({ path: path.join(targetDir, '02-search-results.png'), fullPage: true });
  console.log("Captured 02-search-results.png");

  await browser.close();
  console.log("Done!");
}

capture().catch(e => {
  console.error("Error during screenshot capture:", e);
  process.exit(1);
});
