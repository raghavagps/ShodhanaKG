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

  // Target directory for help screenshots
  const targetDir = 'D:/ShodhanaUI/shodhanakg-explorer-suite-main - final-copy/public/images/help';
  console.log(`Screenshots will be saved to: ${targetDir}`);

  // 1. Homepage
  console.log("Capturing Homepage...");
  await page.goto('http://localhost:8080/');
  await wait(3000);
  await page.screenshot({ path: path.join(targetDir, '01-homepage.png'), fullPage: true });
  console.log("Captured 01-homepage.png");

  /*
  // 2. Disease Graph (Rheumatoid Arthritis pre-loaded)
  console.log("Capturing Disease Graph...");
  await page.goto('http://localhost:8080/explorer/disease/graph?filter=Disease:699');
  await wait(5000); // Allow cytoscape layout to finish
  await page.screenshot({ path: path.join(targetDir, '08-disease-graph.png') });
  console.log("Captured 08-disease-graph.png");

  // 3. Disease Table
  console.log("Capturing Disease Table...");
  await page.goto('http://localhost:8080/explorer/disease/table');
  await wait(3000);
  await page.screenshot({ path: path.join(targetDir, '05-disease-table.png') });
  console.log("Captured 05-disease-table.png");

  // 4. Compound Graph (Aconitum ferox pre-loaded)
  console.log("Capturing Compound Graph...");
  await page.goto('http://localhost:8080/explorer/compound/graph?filter=Compound:%2333%3A2');
  await wait(5000);
  await page.screenshot({ path: path.join(targetDir, '06-compound-graph.png') });
  console.log("Captured 06-compound-graph.png");

  // 5. Integrated Graph (Rheumatoid Arthritis pre-loaded)
  console.log("Capturing Integrated Graph...");
  await page.goto('http://localhost:8080/explorer/integrated/graph?filter=Disease:699');
  await wait(5000);
  await page.screenshot({ path: path.join(targetDir, '07-integrated-graph.png') });
  console.log("Captured 07-integrated-graph.png");
  */

  await browser.close();
  console.log("All screenshots captured and saved successfully!");
}

capture().catch(e => {
  console.error("Error during screenshot capture:", e);
  process.exit(1);
});
