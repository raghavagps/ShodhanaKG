const { chromium } = require('@playwright/test');

async function getCoords() {
  const browser = await chromium.launch();
  // Set viewport to the approximate size of the screenshot
  const context = await browser.newContext({ viewport: { width: 1440, height: 810 } });
  const page = await context.newPage();
  
  await page.goto('http://localhost:8080/explorer/integrated');
  await page.waitForTimeout(2000); // Wait for load

  const getPercent = async (selectorText) => {
    try {
      const locator = page.locator(`text="${selectorText}"`).first();
      const rect = await locator.boundingBox();
      if (!rect) return null;
      
      const x = rect.x + rect.width / 2;
      const y = rect.y + rect.height / 2;
      
      const width = 1440;
      const height = 810;
      
      return {
        x: Math.round((x / width) * 100),
        y: Math.round((y / height) * 100)
      };
    } catch (e) {
      return null;
    }
  };

  const graphViewToggle = await getPercent('Graph View');
  const propertiesPanel = await getPercent('Properties & evidence');
  const hopSelection = await getPercent('1-hop');
  const exportBtn = await getPercent('Export');

  console.log("Graph View Toggle:", graphViewToggle);
  console.log("Properties Panel:", propertiesPanel);
  console.log("Hop Selection:", hopSelection);
  console.log("Export Button:", exportBtn);
  
  await browser.close();
}

getCoords().catch(e => console.error(e));
