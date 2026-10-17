const fs=require('fs'),path=require('path'),assert=require('assert');
const {chromium,firefox,webkit}=require('../../node_modules/playwright');
const out=process.env.RELEASE_AUDIT,base='https://webs.iiitd.edu.in/raghava/shodhanakg';
(async()=>{
 const reports=[];
 for(const [name,engine] of Object.entries({chromium})) {
  const browser=await engine.launch({headless:true,args:['--host-resolver-rules=MAP webs.iiitd.edu.in 192.168.22.132']});const page=await browser.newPage();const errors=[],failed=[],checks=[],navigationCancellations=[];let leaving=false;
  page.on('pageerror',e=>errors.push(e.message));page.on('requestfailed',r=>{const item={url:r.url(),error:r.failure()?.errorText};if(leaving && r.url().includes('/api/chatbot_response.php') && /ABORT|cancel/i.test(item.error||''))navigationCancellations.push(item);else failed.push(item);});
  try {
   for(const route of ['/','/explorer/disease/graph','/explorer/compound/graph','/explorer/integrated/graph','/advanced-search']) {
    const r=await page.goto(base+route,{waitUntil:'networkidle'});assert.equal(r.status(),200);
    await page.locator('nav').first().waitFor();assert((await page.locator('body').innerText()).length>100);checks.push('route '+route);
   }
   await page.getByLabel('Reference namespace').selectOption('AYUSH');await page.getByLabel('ID number').fill('76448');await page.getByRole('button',{name:'Search KG references'}).click();
   await page.getByText('AYUSH ID 76448',{exact:true}).waitFor({timeout:30000});checks.push('AYUSH reference search');
   await page.locator('details').first().locator(':scope > summary').click();await page.getByRole('link',{name:/Open .* in Explorer/}).first().click();
   assert(page.url().includes('/raghava/shodhanakg/explorer/'));const rid=new URL(page.url()).searchParams.get('node');assert(rid);
   await page.waitForFunction(id=>[...document.querySelectorAll('*')].some(e=>e._cyreg?.cy?.getElementById(id).visible()),rid,{timeout:30000});checks.push('RID selected in graph');
   await page.getByRole('button',{name:'Open assistant',exact:true}).click();const input=page.getByRole('textbox',{name:'Question',exact:true});await input.fill('What Shodhana method is used to purify Copper?');const response=page.waitForResponse(r=>r.url().includes('/api/chatbot_response.php')&&r.request().method()==='POST');await input.press('Enter');
   await page.getByText('Swedana',{exact:true}).first().waitFor({timeout:30000});checks.push('packaged chatbot KG answer');
   assert.equal((await response).status(),200);await page.waitForFunction(()=>{const input=document.querySelector('input[aria-label="Question"]');return input && !input.disabled;},{},{timeout:30000});await page.screenshot({path:path.join(out,name+'-package.png'),fullPage:true});leaving=true;
   await page.goto(base+'/missing-package-route',{waitUntil:'networkidle'});assert.equal(await page.getByRole('link',{name:'Return to Home'}).getAttribute('href'),'/raghava/shodhanakg/');checks.push('404 return base');
  }catch(e){errors.push(e.stack||e.message);}finally{await browser.close();}
  // chatApi cancels its SSE reader after a terminal event. Chromium reports
  // that successful cleanup as ERR_ABORTED; retain it, but only classify it
  // after the completed-answer UI checks have passed without a page error.
  const terminalCleanup=errors.length===0 && checks.includes('packaged chatbot KG answer') ? failed.filter(r=>r.url.includes('/api/chatbot_response.php')&&r.error==='net::ERR_ABORTED') : [];
  reports.push({browser:name,checks,errors,failed:failed.filter(r=>!terminalCleanup.includes(r)),terminalCleanup,navigationCancellations});fs.writeFileSync(path.join(out,'package-browser.json'),JSON.stringify(reports,null,2));
 }
 console.log(JSON.stringify(reports));process.exitCode=reports.some(r=>r.errors.length||r.failed.length)?1:0;
})().catch(e=>{console.error(e);process.exitCode=1;});

