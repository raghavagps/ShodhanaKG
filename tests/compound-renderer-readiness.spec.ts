import { expect, test } from '@playwright/test';

test('Compound renderer loads while graph retrieval is pending', async ({ page }) => {
  let graphRequested = false;
  let rendererRequested = false;
  let releaseGraph!: () => void;
  const graphGate = new Promise<void>(resolve => { releaseGraph = resolve; });
  page.on('request', request => {
    if (new URL(request.url()).pathname === '/src/components/explorer/GraphCanvas.tsx') {
      rendererRequested = true;
    }
  });
  await page.route('**/api/graph.php?**', async route => {
    graphRequested = true;
    await graphGate;
    await route.continue();
  });
  try {
    await page.goto('/explorer/compound/graph?node=%235%3A24&type=Compound');
    await expect.poll(() => graphRequested).toBe(true);
    // The renderer must load independently of the still-pending live KG request.
    await expect.poll(() => rendererRequested).toBe(true);
    await expect(page.getByText('Loading graph…', { exact: true })).toBeVisible();
    expect(await page.evaluate(() => [...document.querySelectorAll('*')].some(element =>
      (element as Element & { _cyreg?: { cy?: { nodes(): { length: number } } } })._cyreg?.cy?.nodes().length
    ))).toBe(false);
  } finally {
    releaseGraph();
  }
  // Releasing the request uses the actual backend response, without synthetic facts.
  await expect.poll(() => page.evaluate(() => {
    const host = [...document.querySelectorAll('*')].find(element =>
      (element as Element & { _cyreg?: unknown })._cyreg
    ) as Element & { _cyreg?: { cy?: { getElementById(id: string): { length: number } } } };
    return host?._cyreg?.cy?.getElementById('#5:24').length;
  })).toBe(1);
});
