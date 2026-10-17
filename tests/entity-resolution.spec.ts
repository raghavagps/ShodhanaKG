import { test, expect } from '@playwright/test';

test.describe('Entity resolution', () => {
  test('typo asks for methods and displays the correction', async ({page}) => {
    await page.goto('/');
    await page.getByRole('button',{name:'Open assistant'}).click();
    const input=page.getByRole('textbox', { name: 'Question', exact: true });
    await input.fill('What are the methods to purify coper?');
    await input.press('Enter');
    await expect(page.getByText('Interpreted “coper” as Copper.')).toBeVisible({timeout:20000});
    const results=page.getByRole('list',{name:'Retrieved results'});
    await expect(results).toContainText('Swedana');
    await expect(results).toContainText('Nirvapa');
    await expect(results).not.toContainText('Shodhita Tamra');
  });
  test('Cancer alternative keeps Indication and returns supported purified compounds', async ({page}) => {
    await page.goto('/');
    await page.getByRole('button',{name:'Open assistant'}).click();
    const input=page.getByRole('textbox', { name: 'Question', exact: true });
    await input.fill('What are the therapies of cancer?');
    await input.press('Enter');
    await page.getByRole('button',{name:/Cancer.*Indication.*Explore purified compound/i}).click({timeout:20000});
    await expect(page.getByRole('list',{name:'Retrieved results'})).toBeVisible({timeout:20000});
    await expect(page.getByRole('list',{name:'Retrieved results'})).not.toBeEmpty();
  });
  test('Explorer typo suggestions preserve canonical identity', async ({request,page}) => {
    const data=await (await request.get('/api/search.php?domain=compound&q=coper')).json();
    const copper=data.nodes.find(n=>n.label==='Copper');
    expect(copper.id).toBe('#5:24');
    expect(copper.match.match_method).toBe('spelling');
    await page.goto('/explorer/compound/graph?seeds='+encodeURIComponent(copper.id));
    await expect(page.locator('canvas').first()).toBeVisible({timeout:20000});
  });
});
