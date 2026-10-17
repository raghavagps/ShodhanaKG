const fs=require('fs'),path=require('path'),assert=require('assert');
const {chromium}=require('../../node_modules/playwright');
const out=process.env.RELEASE_AUDIT;
(async()=>{
 const browser=await chromium.launch({headless:true,args:['--host-resolver-rules=MAP webs.iiitd.edu.in 192.168.22.132']});
 const page=await browser.newPage();const checks=[],raw={};
 try {
  await page.goto('https://webs.iiitd.edu.in/raghava/shodhanakg/',{waitUntil:'domcontentloaded'});
  async function post(body){return page.evaluate(async body=>{const r=await fetch('/raghava/shodhanakg/api/chatbot_response.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});return {status:r.status,type:r.headers.get('content-type'),text:await r.text()};},body);}
  raw.json=await post({question:'What Shodhana method is used to purify Copper?'});const answer=JSON.parse(raw.json.text);assert.equal(answer.status,'success');assert.equal(answer.query_plan.result_type,'Shodhana_Method');assert(answer.citations.length);checks.push('Copper methods JSON with citations');
  raw.sse=await post({question:'What Shodhana method is used to purify Copper?',stream:true});assert(raw.sse.type.includes('text/event-stream'));const blocks=raw.sse.text.split(/\r?\n\r?\n/);const done=blocks.filter(b=>/^event: done$/m.test(b));assert.equal(done.length,1);const terminal=JSON.parse(done[0].split(/\r?\n/).filter(l=>l.startsWith('data:')).map(l=>l.slice(5).trim()).join('\n'));for(const key of ['answer','answer_results','citations','query_plan','status'])assert.deepStrictEqual(terminal[key],answer[key],key);checks.push('JSON/SSE parity: answer, results, citations, query plan, status');
  raw.first=await post({question:'What therapies are used for Vipadika?'});const first=JSON.parse(raw.first.text);assert.equal(first.status,'success');assert(first.conversation_context);
  raw.followup=await post({question:'What about its formulations?',conversation_context:first.conversation_context});const next=JSON.parse(raw.followup.text);assert.equal(next.status,'success');assert.equal(next.conversation_context.result_type,'Formulation');assert(/Vipadika/i.test(JSON.stringify(next.conversation_context.subject)));assert(next.citations.length);checks.push('Vipadika therapies to formulations structured follow-up');
  raw.protected=await page.evaluate(async()=>{const a=[];for(const p of ['/.deployment-backups/release-20261011-184233/plan.json','/api/config.php','/api/core/reference_catalog_v1.json','/api/nonexistent-deploy-check.php']){const r=await fetch('/raghava/shodhanakg'+p);a.push({path:p,status:r.status,type:r.headers.get('content-type')});}return a;});
  for(const r of raw.protected)assert([403,404].includes(r.status),JSON.stringify(r));checks.push('Private deployment/config/catalog denied and unknown API rejected');
 }catch(e){raw.error=e.stack;process.exitCode=1;}finally{await browser.close();fs.writeFileSync(path.join(out,'api-smoke.json'),JSON.stringify({checks,raw},null,2));console.log(JSON.stringify({checks,error:raw.error}));}
})();

