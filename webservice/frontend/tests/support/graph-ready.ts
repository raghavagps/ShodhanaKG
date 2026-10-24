import type { Page } from '@playwright/test';
import type { Core } from 'cytoscape';

/** Data responses and loading badges do not imply that the lazy renderer mounted.
 * Use the enclosing test's existing deadline, then run the original assertions.
 * Require the exact record in a live renderer, never just an arbitrary canvas.
 */
export async function waitForGraphRecord(page: Page, recordId: string) {
  const ready = await page.waitForFunction(id => {
    type Host = Element & { _cyreg?: { cy: Core } };
    return [...document.querySelectorAll('*')].some(element => {
      const cy = (element as Host)._cyreg?.cy;
      if (!cy || cy.destroyed() || !cy.nodes().length) return false;
      const record = cy.getElementById(id);
      return record.length === 1 && record.visible();
    });
  }, recordId);
  await ready.dispose();
}
