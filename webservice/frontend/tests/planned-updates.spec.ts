import { waitForGraphRecord } from './support/graph-ready';
import { test, expect } from '@playwright/test';
import type { Core } from 'cytoscape';
test('Advanced Search preserves namespaces and opens an entity graph',async({page},testInfo)=>{
  await page.goto('/advanced-search');
  await expect(page.getByRole('heading',{name:'Advanced Search'})).toBeVisible();
  await page.getByLabel('Reference namespace').selectOption('AYUSH');
  await page.getByLabel('ID number').fill('76448');
  await page.getByRole('button',{name:'Search KG references'}).click();
  await expect(page.getByText('AYUSH ID 76448',{exact:true})).toBeVisible({timeout:30000});
  await expect(page.getByText(/registry lookup|registry not verified/)).toHaveCount(0);
  await page.locator('details').first().locator(':scope > summary').click();
  await page.screenshot({path:testInfo.outputPath('advanced-search.png'),fullPage:true});
  await page.getByRole('link',{name:/Open .* in Explorer/}).first().click();
  await expect(page).toHaveURL(/\/explorer\/.*\/graph\?.*node=/);
  await expect(page.getByLabel('Node limit')).toHaveValue('50');
});
test('Chatbot introductory FAQ is separate from graph evidence',async({page},testInfo)=>{
  await page.goto('/');
  const response=await page.request.post('/api/chatbot_response.php',{data:{question:'What is Shodhana?',faq_id:'shodhana'}});
  expect(response.ok()).toBeTruthy();const answer=await response.json();
  expect(answer.answer).toContain('Background information');
  expect(answer.faq.id).toBe('shodhana');expect(answer.citations).toEqual([]);
  await page.getByRole('button',{name:'Open assistant',exact:true}).click();
  await page.getByRole('button',{name:'What is Shodhana?',exact:true}).click();
  await expect(page.getByText(/Background information/)).toBeVisible();
  await expect(page.getByText('Background sources (not KG edge evidence)')).toBeVisible();
  await page.screenshot({path:testInfo.outputPath('faq.png')});
});
test('Progressive graph loading retains positions and selection',async({page})=>{
  const seed='#45:82';
  await page.goto('/explorer/disease/graph?node='+encodeURIComponent(seed)+'&type=Disease&nodeLimit=100');
  const state=()=>page.evaluate(()=>{
    const host=[...document.querySelectorAll<Element & {_cyreg?: {cy:Core}}>('*')].find(element=>element._cyreg?.cy);
    const cy=host?._cyreg?.cy;
    return cy?{nodes:cy.nodes().length,position:cy.getElementById('#45:82').position(),selected:cy.getElementById('#45:82').selected()}:null;
  });
  await waitForGraphRecord(page, seed);
  await expect.poll(async()=>(await state())?.nodes ?? 0).toBeGreaterThan(0);
  await page.evaluate(()=>{
    const host=[...document.querySelectorAll<Element & {_cyreg?: {cy:Core}}>('*')].find(element=>element._cyreg?.cy);
    const cy=host!._cyreg!.cy;cy.stop();cy.getElementById('#45:82').position({x:123,y:456}).select();
  });
  await page.getByLabel('Node limit').selectOption('all');
  await expect(page.getByRole('status')).not.toContainText('Loading');
  const after=await state();expect(after?.position).toEqual({x:123,y:456});expect(after?.selected).toBe(true);
});
