import { afterEach, expect, it, vi } from 'vitest';
import { ReadableStream } from 'node:stream/web';
import { TextDecoder, TextEncoder } from 'node:util';
import { fetchChatStream } from './chatApi';
afterEach(()=>vi.unstubAllGlobals());
it('completes on a terminal SSE event even when the peer leaves HTTP open',async()=>{
  vi.stubGlobal('TextDecoder',TextDecoder);
  const cancel=vi.fn();
  const body=new ReadableStream({start(c){c.enqueue(new TextEncoder().encode('event: done\ndata: {"status":"success","answer":"complete","citations":[]}\n\n'));},cancel});
  vi.stubGlobal('fetch',vi.fn().mockResolvedValue({ok:true,headers:new Headers({'content-type':'text/event-stream'}),body}));
  const controller=new AbortController();
  const pending=fetchChatStream('q',[],'',vi.fn(),vi.fn(),undefined,0,undefined,controller.signal);
  const result=await Promise.race([pending,new Promise(resolve=>setTimeout(()=>resolve('still waiting for EOF'),100))]);
  if(typeof result==='string') { await body.cancel().catch(()=>{}); controller.abort(); }
  expect(result).toMatchObject({status:'success',answer:'complete',citations:[]});
  expect(cancel).toHaveBeenCalled();
});
