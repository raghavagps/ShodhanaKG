import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const snapshot = JSON.parse(readFileSync('api/tests/fixtures/factual_graph_snapshot_expanded.json', 'utf8'));
const audit = JSON.parse(readFileSync('docs/chatbot/audits/identifier_rules_2026_09_24_v1/audit.json', 'utf8'));
const records = Object.values(snapshot.tables).flat() as Array<Record<string, string>>;
const expected = new Map<string, string>(audit.rows.flatMap(row => row.edge_ids.map(id => [id, row.assessed_type])));

for (const [reference, namespace] of [['76448', 'AYUSH'], ['39210691', 'PMID'], ['10.4103/joa.joa_262_22', 'DOI']]) {
  test(`Reference classification: live Explorer ${namespace}`, async ({ request }) => {
    const raw = records.find(r => r['@cat'] === 'e' && r.ref === reference)!;
    const response = await request.get('http://127.0.0.1:8081/api/graph.php', {
      params: { domain: 'integrated', seeds: raw['@out'], limitNodes: 500, limitEdges: 2000 },
    });
    expect(response.ok()).toBeTruthy();
    const body = await response.json();
    const edge = body.edges.find(e => e.id === raw['@rid']);
    expect(edge).toBeTruthy();
    expect(edge.references[0].namespace).toBe(namespace);
    expect(edge.references[0].raw).toBe(reference);
    if (namespace !== 'PMID') expect(edge.pubmedId).toBeUndefined();
    for (const item of body.edges) {
      expect(item.references[0].namespace).toBe(expected.get(item.id));
      expect(item.references[0].status).toBe('verified');
    }
  });
}

test('Reference classification: chatbot citation uses live backend metadata', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('button', { name: /open assistant/i }).click();
  const input = page.getByRole('textbox', { name: 'Question', exact: true });
  await input.fill('What media is used for Copper?');
  await input.press('Enter');
  const source = page.getByRole('button', { name: /^View source / }).first();
  await expect(source).toBeVisible({ timeout: 30000 });
  await source.click();
  const card = page.getByRole('article', { name: 'Evidence record 1', exact: true });
  await expect(card).toBeVisible();
  const link = card.getByRole('link').first();
  await expect(link).toHaveAttribute('aria-label', /^Open ID: (AYUSH ID|PMID|DOI) /);
  const label = (await link.getAttribute('aria-label'))!.replace('Open ID: ', '');
  await expect(link).toHaveAttribute('href', label!.startsWith('AYUSH') ? /arp\.ayush\.gov\.in/ : label!.startsWith('PMID') ? /pubmed\.ncbi\.nlm\.nih\.gov/ : /doi\.org/);
});
