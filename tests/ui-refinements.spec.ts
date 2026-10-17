import { test, expect } from '@playwright/test';

test('Advanced Search domain options, page size and navigation state',async({page})=>{
 await page.goto('/advanced-search');
 await page.getByLabel('Domain',{exact:true}).selectOption('compound');
 await expect(page.getByLabel('Entity type').locator('option',{hasText:'Shodhana_Method'})).toHaveCount(1);
 await expect(page.getByLabel('Entity type').locator('option',{hasText:'Disease'})).toHaveCount(0);
 await page.getByLabel('Entity type').selectOption('Compound');
 await page.getByLabel('Domain',{exact:true}).selectOption('disease');
 await expect(page.getByLabel('Entity type')).toHaveValue('');
 await expect(page.getByLabel('Relationship').locator('option',{hasText:'UNDERGOES'})).toHaveCount(0);
 await page.getByLabel('References per page').selectOption('50');
 await page.getByLabel('Keywords in stored records').fill('Psoriasis');
 const searchResponse=page.waitForResponse(response=>{
  const url=new URL(response.url());
  return url.pathname.endsWith('/reference-search.php') && url.searchParams.get('q')==='Psoriasis' && !url.searchParams.has('facets');
 });
 await page.getByRole('button',{name:'Search KG references'}).click();
 const response=await searchResponse;
 expect(response.status()).toBe(200);
 const submitted=new URL(response.url()).searchParams;
 expect(submitted.get('domain')).toBe('disease');
 expect(submitted.get('type')).toBe('');
 expect(submitted.get('relationship')).toBe('');
 expect(submitted.get('limit')).toBe('50');
 await expect(page.getByText(/matching references/)).toBeVisible();
 await expect(page.getByText(/Retrieved \d{4}/)).toHaveCount(0);
 await page.getByRole('link',{name:'Home',exact:true}).click();
 await page.getByRole('link',{name:'Advanced Search',exact:true}).click();
 await expect(page.getByLabel('Keywords in stored records')).toHaveValue('Psoriasis');
 await expect(page.getByLabel('Domain',{exact:true})).toHaveValue('disease');
 await expect(page.getByLabel('References per page')).toHaveValue('50');
});

test('Mobile Advanced Search navigation is accessible',async({page})=>{
 await page.setViewportSize({width:390,height:844});await page.goto('/');
 await page.getByRole('button',{name:'Open navigation'}).click();
 await page.getByRole('menuitem',{name:'Advanced Search',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Advanced Search'})).toBeVisible();
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBeTruthy();
});

test('Graph selector and mode-specific diagnosis colour',async({page})=>{
 await page.goto('/explorer/disease/graph?node=%2345%3A82&type=Disease');
 await expect(page.getByLabel('Node limit')).toHaveValue('50');
 expect(await page.getByLabel('Node limit').locator('option').allTextContents()).toEqual(['50','100','200','All','Custom']);
 await expect(page.getByRole('button',{name:'Load all matching',exact:true})).toHaveCount(0);
 await expect(page.getByText(/Nodes \d+\/\d+ \u00b7 Edges/)).toBeVisible();
 await page.getByRole('button',{name:'Colorblind Mode',exact:true}).click();
 const swatches=page.locator('span').filter({hasText:/^AyurvedicDiagnosis$/});
 await expect.poll(()=>page.evaluate(()=>[...document.querySelectorAll('span')].some(el=>el.style.background==='rgb(86, 180, 233)'))).toBe(true);
 await page.getByLabel('Node limit').selectOption('all');
 await expect(page.getByText(/Nodes \d+\/\d+ \u00b7 Edges/)).not.toContainText('Loading');
 await expect(page.getByText(/Nodes \d+\/\d+ \u00b7 Edges/)).not.toContainText('Truncated');
});


test('Statistics retain distinct metric labels and search restores its current page',async({page})=>{
 await page.goto('/', { waitUntil: 'domcontentloaded' });
 for(const name of ['Total Nodes','Total Relations','Distinct References']) await expect(page.getByText(name,{exact:true})).toBeVisible();
 await page.getByRole('link',{name:'Advanced Search',exact:true}).click();
 await page.getByRole('button',{name:'Next',exact:true}).click();
 await expect(page.getByRole('button',{name:'Page 2',exact:true})).toHaveAttribute('aria-current','page');
 await expect(page.getByRole('button',{name:'Next',exact:true})).toBeEnabled();
 await page.getByRole('link',{name:'Home',exact:true}).click();
 await page.getByRole('link',{name:'Advanced Search',exact:true}).click();
 await expect(page.getByRole('button',{name:'Page 2',exact:true})).toHaveAttribute('aria-current','page');
 await page.getByLabel('References per page').selectOption('100');
 await expect(page.getByRole('button',{name:'Page 1',exact:true})).toHaveAttribute('aria-current','page');
 await expect(page.getByRole('button',{name:'Next',exact:true})).toBeVisible();
});
