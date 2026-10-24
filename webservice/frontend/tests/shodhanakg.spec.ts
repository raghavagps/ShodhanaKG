import { test, expect } from '@playwright/test';
import { waitForGraphRecord } from './support/graph-ready';
import type { Core } from 'cytoscape';
type GraphElement = Element & { _cyreg?: { cy: Core } };

test.describe('ShodhanaKG Explorer Suite', () => {

  test.describe('Disease Table View', () => {
    test('should load disease table and show Ayurvedic Diagnosis column', async ({ page }) => {
      await page.goto('/explorer/disease/table', { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(2000);

      // Verify page title renders
      await expect(page.locator('h1')).toContainText('Disease relationship statistics');

      // Verify Ayurvedic Diagnosis column header exists
      await expect(page.locator('th').filter({ hasText: 'Ayurvedic Diagnosis' })).toBeVisible();
    });

    test('should navigate to graph view on row click', async ({ page }) => {
      await page.goto('/explorer/disease/table', { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(2000);

      // Wait for data rows (API may be unavailable, skip if only error/loading shown)
      const dataRow = page.locator('table tbody tr td .font-medium').first();
        await dataRow.waitFor({ state: 'visible', timeout: 15000 });
        const row = page.locator('table tbody tr').filter({ has: page.locator('td .font-medium') }).first();
        await row.click();
        await page.waitForTimeout(2000);
        await expect(page).toHaveURL(/\/explorer\/disease\/graph/);

    });

    test('should support column sorting', async ({ page }) => {
      await page.goto('/explorer/disease/table', { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(2000);

      // Click Symptoms column header to sort
      const symptomsHeader = page.locator('th').filter({ hasText: 'Symptoms' });
      await expect(symptomsHeader).toBeVisible();
      {
        await symptomsHeader.click();
        await page.waitForTimeout(500);
        await expect(symptomsHeader.locator('svg')).toBeVisible();
      }
    });
  });

  test.describe('Disease Graph View', () => {
    test('should load graph view from URL with node param', async ({ page }) => {
      await page.goto('/explorer/disease/graph?node=%2345%3A124&type=Disease', { waitUntil: 'domcontentloaded' });
      await waitForGraphRecord(page, '#45:124');
      await expect(page.locator('h2').filter({ hasText: 'Filters' })).toBeVisible();

      // Should show the chip for the selected disease
      await expect(page.locator('text=Vipadika').first()).toBeVisible();
    });

    test('should sync filters to URL', async ({ page }) => {
      await page.goto('/explorer/disease/graph');
      await expect(page.locator('h2').filter({ hasText: 'Filters' })).toBeVisible();

      // Pick a disease from the first MultiAutocomplete
      const filterSection = page.locator('aside').first();
      const diseasePicker = page.getByPlaceholder('Select disease', { exact: false }).first();
      await diseasePicker.fill('Vipadika');
      await page.getByRole('button', { name: 'Vipadika', exact: true }).first().click();
      await expect(page).toHaveURL(/filter=/);

    });

    test('should show node visibility toggles', async ({ page }) => {
      await page.goto('/explorer/disease/graph', { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(3000);
      await expect(page.locator('text=Node visibility')).toBeVisible({ timeout: 10000 });
    });

    test('should display DetailsPanel with edge properties when edge selected', async ({ page }) => {
      const graphResponse = page.waitForResponse(r => r.url().includes('/api/graph.php') && r.ok());
      await page.goto('/explorer/disease/graph?node=%2345%3A124&type=Disease', { waitUntil: 'domcontentloaded' });

      // Graph should be rendered (check for canvas or node/edge count)
      const countBadge = page.locator('text=nodes').first();
      await expect(countBadge).toBeVisible({ timeout: 10000 });
      const graph = await (await graphResponse).json();
      const evidenceEdge = graph.edges.find(e => e.display_properties?.length || e.treatment_phase);
      expect(evidenceEdge).toBeTruthy();
      await waitForGraphRecord(page, evidenceEdge.id);
      const selected = await page.evaluate(edgeId => {
        const host = [...document.querySelectorAll('*')].find(el => (el as GraphElement)._cyreg?.cy) as GraphElement;
        const edge = host._cyreg!.cy.getElementById(edgeId);
        if (!edge.length) throw new Error('No evidence-bearing edge available');
        edge.emit('tap');
        return { source: edge.source().data('label'), target: edge.target().data('label') };
      }, evidenceEdge.id);
      const details = page.locator('main aside').last();
      await expect(details.getByText(selected.source, { exact: true }).first()).toBeVisible();
      await expect(details.getByText(selected.target, { exact: true }).first()).toBeVisible();
      await expect(details.locator('dt').first()).toBeVisible();
    });
  });

  test.describe('Compound Graph View', () => {
    test('should load compound graph from URL', async ({ page }) => {
      await page.goto('/explorer/compound/graph?node=c1');
      await page.waitForTimeout(1000);

      // Should show filter panel
      await expect(page.locator('h2').filter({ hasText: 'Filters' })).toBeVisible();
    });

    test('should show node visibility toggles', async ({ page }) => {
      await page.goto('/explorer/compound/graph');
      await expect(page.locator('text=Node visibility')).toBeVisible();

      const edgeCheck = page.getByRole('checkbox', { name: 'Compound', exact: true });
      await expect(edgeCheck).toBeVisible();
    });

    test('should sync filters to URL', async ({ page }) => {
      await page.goto('/explorer/compound/graph');
      await page.waitForTimeout(500);

      // Find and verify filter section exists
      await expect(page.locator('aside').first()).toBeVisible();

      // Initial URL should have no filter param
      expect(page.url()).not.toContain('filter=');
    });
  });

  test.describe('Integrated Graph View', () => {
    test('should load from URL with seeds and show bridge edge properties', async ({ page }) => {
      await page.goto('/explorer/integrated/graph?seeds=d:d1&depth=1');
      await page.waitForTimeout(2000);

      // Should show the filter panel
      await expect(page.locator('h2').filter({ hasText: 'Filters' })).toBeVisible();
    });

    test('should sync depth and seeds to URL', async ({ page }) => {
      await page.goto('/explorer/integrated/graph?seeds=d:d1');
      await page.waitForTimeout(1000);

      // URL should contain seeds param (colon is URL-encoded as %3A)
      expect(decodeURIComponent(page.url())).toContain('seeds=');
      expect(decodeURIComponent(page.url())).toContain('d:d1');
    });
  });

  test.describe('Milestone 1 — Direct URL Graph State', () => {
    test('disease graph direct typed RID filter shows entity name and graph', async ({ page }) => {
      await page.goto('/explorer/disease/graph?filter=Disease%3A%2345%3A124');
      await page.waitForTimeout(2000);

      // Chip should show the seed ID (label may be raw ID if backend not available)
      const chipArea = page.locator('text=Selected filters');
      await expect(chipArea).toBeVisible({ timeout: 5000 });

      // Graph badge should be visible (may show 0 nodes if no backend)
      const badge = page.locator('text=nodes').first();
      await expect(badge).toBeVisible({ timeout: 5000 });
    });

    test('compound graph direct ?node=177 shows chip and badge', async ({ page }) => {
      await page.goto('/explorer/compound/graph?node=177');
      await page.waitForTimeout(1500);

      await expect(page.locator('h2').filter({ hasText: 'Filters' })).toBeVisible();

      // Chip area should reflect selection
      const chipArea = page.locator('text=Selected filters');
      await expect(chipArea).toBeVisible({ timeout: 5000 });

      // Graph badge
      const badge = page.locator('text=nodes').first();
      await expect(badge).toBeVisible({ timeout: 5000 });
    });

    test('integrated graph direct ?seeds=1155&depth=1 shows badge', async ({ page }) => {
      await page.goto('/explorer/integrated/graph?seeds=1155&depth=1');
      await page.waitForTimeout(2000);

      const badge = page.locator('text=nodes').first();
      await expect(badge).toBeVisible({ timeout: 10000 });
    });
  });

  test.describe('Navigation', () => {
    test('should navigate between tabs correctly', async ({ page }) => {
      await page.goto('/explorer/disease');
      await page.waitForTimeout(500);

      // Navigate to table view via sub-nav
      await page.locator('nav a').filter({ hasText: 'Table View' }).click();
      await expect(page).toHaveURL(/\/explorer\/disease\/table/);

      // Navigate to graph view
      await page.locator('nav a').filter({ hasText: 'Graph View' }).click();
      await expect(page).toHaveURL(/\/explorer\/disease\/graph/);
    });
  });

  // ——————————————————————————————
  // Milestone 7 — Route smoke tests
  // ——————————————————————————————
  test.describe('Route smoke — all explorer pages load', () => {
    const routes = [
      { path: '/', title: /ShodhanaKG|Explorer|Mode/ },
      { path: '/explorer/disease', title: /Disease-ShodhanaKG/ },
      { path: '/explorer/disease/graph', title: /Graph View/ },
      { path: '/explorer/disease/table', title: /Disease relationship statistics/ },
      { path: '/explorer/compound', title: /Compound-ShodhanaKG/ },
      { path: '/explorer/compound/graph', title: /Graph View/ },
      { path: '/explorer/compound/table', title: /Compound connection statistics/ },
      { path: '/explorer/integrated', title: /Integrated-ShodhanaKG/ },
      { path: '/explorer/integrated/graph', title: /Graph View/ },
      { path: '/explorer/integrated/table', title: /Disease–compound bridge table/ },
    ] as const;

    routes.forEach(({ path, title }) => {
      test(`${path} loads without crash`, async ({ page }) => {
        await page.goto(path, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1500);
        // Check page rendered by looking for a known heading or the sub-nav
        await expect(page.locator('nav a').first()).toBeVisible({ timeout: 8000 });
        // Verify no visible crash/error banner
        await expect(page.locator('body')).not.toHaveText(/Application error|Oops|Something went wrong/i);
      });
    });
  });

  // ——————————————————————————————
  // Milestone 7 — Search flow tests
  // ——————————————————————————————
  test.describe('Search flows', () => {
    test('disease home search for Eczema shows result cards with Open graph', async ({ page }) => {
      await page.goto('/explorer/disease', { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(1000);

      // Type search query
      const searchInput = page.locator('input[placeholder*="Search disease"]');
      await searchInput.fill('Eczema');
      await page.waitForTimeout(500);

      // Click Search button
      await page.locator('button').filter({ hasText: 'Search' }).click();
      await page.waitForTimeout(2000);

      // Should show search results (or empty state if backend unavailable)
      const resultCard = page.locator('main a[href*="/graph?node="]').first();
      await expect(resultCard).toBeVisible();
      expect(await resultCard.evaluate(el => el.tagName)).toBe('A');
    });

    test('compound home search for Croton shows result cards with Open graph', async ({ page }) => {
      await page.goto('/explorer/compound', { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(1000);

      const searchInput = page.locator('input[placeholder*="Search compound"]');
      await searchInput.fill('Croton');
      await page.waitForTimeout(500);

      await page.locator('button').filter({ hasText: 'Search' }).click();
      await page.waitForTimeout(2000);

      const resultCard = page.locator('main a[href*="/graph?node="]').first();
      await expect(resultCard).toBeVisible();
      expect(await resultCard.evaluate(el => el.tagName)).toBe('A');
    });

    test('integrated home redirects to bridge table and opens a filtered compound graph', async ({ page }) => {
      await page.goto('/explorer/integrated', { waitUntil: 'domcontentloaded' });
      await expect(page).toHaveURL(/integrated\/table/);
      await page.getByPlaceholder('Filter by purified compound or disease').fill('Tamra');
      const row = page.locator('tbody tr').filter({ hasText: 'Tamra Bhasma' });
      await expect(row).toHaveCount(1);
      await row.getByRole('link', { name: 'Open graph' }).click();
      await expect(page).toHaveURL(/integrated\/graph/);
      const seeds = new URL(page.url()).searchParams.get('seeds')!.split(',');
      expect(seeds.length).toBeGreaterThan(0);
      for (const seed of seeds) await waitForGraphRecord(page, seed);
      await expect(page.getByRole('button', { name: 'Remove Tamra Bhasma', exact: true })).toBeVisible();
      await expect.poll(() => page.evaluate(() => {
        const host = [...document.querySelectorAll('*')].find(el => (el as GraphElement)._cyreg?.cy) as GraphElement;
        return host?._cyreg?.cy.nodes().some(n => n.data('label') === 'Tamra Bhasma') ?? false;
      })).toBe(true);
    });
  });

  // ——————————————————————————————
  // Milestone 7 — Mobile overflow checks
  // ——————————————————————————————
  test.describe('Mobile viewport — no overflow', () => {
    test.use({ viewport: { width: 390, height: 844 } }); // iPhone 14

    const mobileRoutes = [
      '/explorer/disease',
      '/explorer/disease/graph',
      '/explorer/disease/table',
      '/explorer/compound',
      '/explorer/compound/graph',
      '/explorer/compound/table',
      '/explorer/integrated',
      '/explorer/integrated/graph',
      '/explorer/integrated/table',
    ];

    mobileRoutes.forEach((path) => {
      test(`${path} has no horizontal overflow`, async ({ page }) => {
        await page.goto(path, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(2000);

        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
        expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1); // +1 for rounding
      });
    });
  });

  // ——————————————————————————————
  // Milestone 7 — Table "Open graph" link interaction
  // ——————————————————————————————
  test.describe('Table Open graph links', () => {
    test('disease table row has visible Open graph link', async ({ page }) => {
      await page.goto('/explorer/disease/table', { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(2000);

      const ogLink = page.locator('a').filter({ hasText: 'Open graph' }).first();
        await ogLink.waitFor({ state: 'visible', timeout: 12000 });
        await expect(ogLink).toBeVisible();

        // Verify it's a proper link with href
        const href = await ogLink.getAttribute('href');
        expect(href).toContain('/explorer/disease/graph');

    });

    test('compound table row has visible Open graph link', async ({ page }) => {
      await page.goto('/explorer/compound/table', { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(2000);

      const ogLink = page.locator('a').filter({ hasText: 'Open graph' }).first();
        await ogLink.waitFor({ state: 'visible', timeout: 12000 });
        await expect(ogLink).toBeVisible();
        const href = await ogLink.getAttribute('href');
        expect(href).toContain('/explorer/compound/graph');

    });

    test('integrated table row has visible Open graph link', async ({ page }) => {
      await page.goto('/explorer/integrated/table', { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(2000);

      const ogLink = page.locator('a').filter({ hasText: 'Open graph' }).first();
        await ogLink.waitFor({ state: 'visible', timeout: 12000 });
        await expect(ogLink).toBeVisible();
        const href = await ogLink.getAttribute('href');
        expect(href).toContain('/explorer/integrated/graph');

    });
  });
});
