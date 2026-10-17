import {test,expect} from '@playwright/test';
import {readFileSync} from 'node:fs';
const sample=JSON.parse(readFileSync(new URL('../api/tests/fixtures/results_first_psoriasis.json',import.meta.url),'utf8').replace(/^\uFEFF/,''));

test('chat dock reserves page space, widens, restores focus and respects mobile layout',async({page})=>{
 await page.goto('/');
 const input=page.getByRole('textbox',{name:'Question',exact:true});
 const navbar=await page.locator('header.sticky').boundingBox();
 await page.getByRole('button',{name:'Open assistant',exact:true}).click();
 const panel=page.getByRole('complementary',{name:'ShodhanaKG Assistant'});
 await expect(input).toBeFocused();
 await expect.poll(()=>panel.evaluate(el=>el.getBoundingClientRect().width)).toBe(440);
 // Input focus can precede the opening transition; measure the finished layout.
 await page.locator('.kg-chat-panel, .kg-app-layout>main').evaluateAll(async elements => {
   await Promise.all(elements.flatMap(element => element.getAnimations().map(animation => animation.finished)));
 });
 expect((await panel.boundingBox())!.y).toBe(navbar!.height);
 expect(await page.locator('.kg-app-layout>main').evaluate(el=>el.getBoundingClientRect().right)).toBeLessThanOrEqual((await panel.boundingBox())!.x+1);
 await page.getByRole('button',{name:'Widen panel'}).click();
 await expect.poll(()=>panel.evaluate(el=>el.getBoundingClientRect().width)).toBe(640);
 await page.setViewportSize({width:390,height:844});
 await expect.poll(()=>panel.evaluate(el=>el.getBoundingClientRect().width)).toBe(390);
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
 await page.keyboard.press('Escape');
 await expect(page.getByRole('button',{name:'Open assistant',exact:true})).toBeFocused();
});

test('result sheet preserves counts, namespace filters, pagination and Escape hierarchy',async({page})=>{
 await page.addInitScript(answer=>sessionStorage.setItem('chatbot-messages',JSON.stringify([{...answer,role:'bot',text:answer.answer}])),sample);
 await page.goto('/');await page.getByRole('button',{name:'Open assistant',exact:true}).click();
 const first=sample.answer_results[0];
 await page.getByRole('button',{name:`Evidence for ${first.label}`,exact:true}).click();
 const sheet=page.getByRole('dialog',{name:'Evidence',exact:true});
 await expect(sheet).toBeVisible();
 await expect(sheet.getByRole('article')).toHaveCount(first.record_indices.length);
 await sheet.getByRole('button',{name:'AYUSH',exact:true}).click();
 await expect(sheet.getByText('No sources in this filter.')).toBeVisible();
 await sheet.getByRole('button',{name:'All',exact:true}).click();
 await page.keyboard.press('Escape');
 await expect(sheet).toHaveCount(0);
 await expect(page.getByRole('button',{name:`Evidence for ${first.label}`,exact:true})).toBeFocused();
 await page.getByRole('button',{name:'View all 38 sources',exact:true}).click();
 await sheet.getByRole('button',{name:'Next sources'}).click();
 await expect(sheet.getByRole('article',{name:'Evidence record 11',exact:true})).toBeVisible();
 await sheet.getByRole('button',{name:'Previous sources'}).click();
 await expect(sheet.getByRole('article',{name:'Evidence record 1',exact:true})).toBeVisible();
 await page.keyboard.press('Escape');await page.keyboard.press('Escape');
 await expect(page.getByRole('button',{name:'Open assistant',exact:true})).toBeFocused();
});

test('reduced motion disables panel transitions',async({page})=>{
 await page.emulateMedia({reducedMotion:'reduce'});await page.goto('/');
 await page.getByRole('button',{name:'Open assistant',exact:true}).click();
 expect(await page.locator('.kg-chat-panel').evaluate(el=>getComputedStyle(el).transitionDuration)).toBe('0s');
 await expect(page.getByRole('textbox',{name:'Question',exact:true})).toBeFocused();
});
