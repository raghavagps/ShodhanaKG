// User-owned dev server only. Captures real graphs; never starts/stops services.
const { chromium } = require('playwright');
const fs = require('node:fs');
const out = process.argv[2] || 'docs/accessibility/node-colors';
fs.mkdirSync(out, { recursive: true });
(async () => {
  const browser = await chromium.launch();
  try {
    for (const mode of [false, true]) {
      const page = await browser.newPage({ viewport: { width: 1500, height: 1100 } });
      await page.addInitScript(value => {
        localStorage.setItem('shodhana-colorblind', String(value));
        sessionStorage.setItem('chatbot-greeting-v1', 'true');
        sessionStorage.setItem('chatbot-launcher-noticed', 'true');
      }, mode);
      for (const [domain, id, type, label] of [['disease','#45:124','Disease','Vipadika'], ['compound','#5:24','Compound','Copper'], ['integrated','#5:24','Compound','Copper']]) {
        await page.goto(`http://localhost:8080/explorer/${domain}/graph?node=${encodeURIComponent(id)}&type=${type}&integratedMode=1hop`);
        await page.getByRole('heading', { name: label, exact: true }).waitFor({ timeout: 20000 });
        await page.evaluate(() => { const cy = [...document.querySelectorAll('*')].find(e => e._cyreg?.cy)._cyreg.cy; cy.stop(); });
        const client = await page.context().newCDPSession(page);
        for (const vision of ['none', 'deuteranopia', 'protanopia', 'tritanopia']) {
          await client.send('Emulation.setEmulatedVisionDeficiency', { type: vision });
          await page.screenshot({ path: `${out}/after-${domain}-${mode?'accessible':'normal'}-${vision}.png` });
        }
        await client.send('Emulation.setEmulatedVisionDeficiency', { type: 'none' });
        await client.detach();
      }
      await page.close();
    }
    console.log('Saved 24 real-graph screenshots: 3 Explorers x 2 modes x 4 vision conditions.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
