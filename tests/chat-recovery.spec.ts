import {test,expect} from '@playwright/test';
test('assistant automatically recovers from transient health failure without reload',async({page})=>{
 let probes=0;
 await page.route('**/api/chatbot_health.php',route=>++probes===1?route.fulfill({status:503,contentType:'application/json',body:'{"ok":false}'}):route.continue());
 await page.goto('/');await page.getByRole('button',{name:'Open assistant',exact:true}).click();
 await expect(page.getByText('(Offline)',{exact:true})).toBeVisible();
 await expect(page.getByRole('textbox', { name: 'Question', exact: true })).toBeEnabled();
 await expect(page.getByText('(Offline)',{exact:true})).toHaveCount(0,{timeout:12000});
 expect(probes).toBeGreaterThan(1);
});
test('assistant recovers from failed request and preserves fresh multi-turn KG retrieval',async({page})=>{
 let calls=0;
 await page.route('**/api/chatbot_response.php',route=>++calls===1?route.fulfill({status:503,body:'temporary unavailable'}):route.continue());
 await page.goto('/');await page.getByRole('button',{name:'Open assistant',exact:true}).click();
 const input=page.getByRole('textbox', { name: 'Question', exact: true });await input.fill('What therapies are used for Vipadika?');await input.press('Enter');
 await expect(page.getByRole('button',{name:'Retry',exact:true})).toBeVisible();await expect(input).toBeEnabled();
 await page.getByRole('button',{name:'Retry',exact:true}).click();await expect(input).toBeEnabled({timeout:30000});
 const response=page.waitForRequest(r=>r.url().includes('chatbot_response.php')&&r.postDataJSON()?.question==='What about its formulations?');
 await input.fill('What about its formulations?');await input.press('Enter');
 expect((await response).postDataJSON().conversation_context.subject.name).toBe('Vipadika');
 await expect.poll(()=>page.evaluate(()=>JSON.parse(sessionStorage.getItem('chatbot-messages')||'[]').at(-1)?.status)).toBe('success');
 expect(await page.evaluate(()=>JSON.parse(sessionStorage.getItem('chatbot-messages')||'[]').at(-1)?.conversation_context.subject.name)).toBe('Vipadika');
 await expect(input).toBeEnabled({timeout:30000});await expect(page.getByText('(Offline)',{exact:true})).toHaveCount(0);
 for(const q of ['What are the symptoms of Psoriasis?','Which methods purify Copper?']){
  const done=page.waitForRequest(r=>r.url().includes('chatbot_response.php')&&r.postDataJSON()?.question===q);
  await input.fill(q);await input.press('Enter');expect((await done).postDataJSON().question).toBe(q);await expect(input).toBeEnabled({timeout:30000});
  expect(await page.evaluate(()=>JSON.parse(sessionStorage.getItem('chatbot-messages')||'[]').at(-1)?.status)).toBe('success');
 }
});
