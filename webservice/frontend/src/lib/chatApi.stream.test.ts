import { afterEach, describe, expect, it, vi } from 'vitest';
import { fetchChatStream, fetchChatResponse, checkChatHealth, CHAT_TIMEOUT_MS } from './chatApi';
import { ReadableStream } from 'node:stream/web';
import { TextEncoder, TextDecoder } from 'node:util';

afterEach(() => { vi.unstubAllGlobals(); vi.useRealTimers(); });

function mockStream(text: string, type = 'text/event-stream', split = true) {
  vi.stubGlobal('TextDecoder', TextDecoder);
  const bytes = new TextEncoder().encode(text);
  const body = new ReadableStream({
    start(controller) {
      // Every byte is a separate chunk, including split UTF-8 and CRLF boundaries.
      if(split) for (const byte of bytes) controller.enqueue(new Uint8Array([byte]));
      else controller.enqueue(bytes);
      controller.close();
    },
  });
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, headers: new Headers({ 'content-type': type }), body }));
}

describe('chatbot transport', () => {
  it('preserves curated FAQ context and its background sources without wrapping them as retrieval', async () => {
    const context = { type: 'faq_static_response', faq: { id: 'shodhana', sources: [{ title: 'Background source', url: 'https://example.org/source' }] } };
    mockStream(`event: context\ndata: ${JSON.stringify(context)}\n\nevent: done\ndata: ${JSON.stringify({ status: 'success', answer: 'Background information', context })}\n\n`);
    const onContext = vi.fn();
    const result = await fetchChatStream('What is Shodhana?', [], '', vi.fn(), onContext);
    expect(onContext).toHaveBeenCalledWith(context);
    expect(result.context).toEqual(context);
  });
  it('retains authoritative structured answers and citation maps in the final event', async () => {
    const presentation = { status: 'success', answer: 'Recorded association. [1]', answer_intro: 'Available information', answer_blocks: [{ text: 'Recorded association.', citation_ids: ['1'] }], citations: [{ id: '1', record_indices: [0], statement: 'Recorded association.' }] };
    mockStream(`event: done\ndata: ${JSON.stringify(presentation)}\n\n`);
    const result = await fetchChatStream('q', [], '', vi.fn(), vi.fn());
    expect(result.answer_blocks).toEqual(presentation.answer_blocks);
    expect(result.citations).toEqual(presentation.citations);
    expect(result.answer).toBe(presentation.answer);
  });
  it('preserves complete evidence context and replaces rejected text with final fallback', async () => {
    const context = { status: 'success', question: 'q', routing: { endpoint: 'provenance_compound_method_media' }, retrieval: { results: [{ nodes: [{ label: 'ताम्र' }] }] } };
    mockStream(`event: context\r\ndata: ${JSON.stringify(context)}\r\n\r\ndata: {"chunk":"ताम्र unsupported claim"}\r\n\r\nevent: done\r\ndata: {"status":"grounding_error","answer":"Unsupported answer withheld."}`);
    const onContext = vi.fn();
    const tokens = vi.fn();
    const result = await fetchChatStream('q', [], '', tokens, onContext);
    expect(onContext).toHaveBeenCalledWith(context);
    expect(tokens).toHaveBeenCalledWith('ताम्र unsupported claim');
    expect(result.status).toBe('grounding_error');
    expect(result.answer).toBe('Unsupported answer withheld.');
  });

  it('preserves no-correlated-evidence reason', async () => {
    mockStream('event: context\ndata: {"status":"no_data","reason":"no_correlated_evidence","retrieval":{"results":[]}}\n\nevent: done\ndata: {"status":"no_data","answer":"No correlated evidence."}\n\n');
    const result = await fetchChatStream('q', [], '', vi.fn(), vi.fn());
    expect(result.context?.reason).toBe('no_correlated_evidence');
    expect(result.status).toBe('no_data');
  });

  it('accepts JSON FAQ responses from older backends', async () => {
    const payload = { status: 'success', answer: 'FAQ answer' };
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, headers: new Headers({ 'content-type': 'application/json' }), json: async () => payload }));
    expect((await fetchChatStream('q', [], '', vi.fn(), vi.fn())).answer).toBe('FAQ answer');
  });

  it('rejects an interrupted stream instead of treating partial text as a completed answer', async () => {
    mockStream('data: {"chunk":"partial"}\n\n');
    await expect(fetchChatStream('q', [], '', vi.fn(), vi.fn())).rejects.toThrow('before completion');
  });

  it('rejects an HTML gateway response', async () => {
    mockStream('<html>Gateway error</html>', 'text/html');
    await expect(fetchChatStream('q', [], '', vi.fn(), vi.fn())).rejects.toThrow('format');
  });
});


describe('request lifecycle and metadata parity', () => {
  it('preserves node evidence, query plans and future terminal metadata', async () => {
    const payload = {status: 'success', answer: 'Stored description', node_evidence: [{id: '#1:2'}], query_plan: {result_type: 'Disease'}, understanding: {intent: 'property'}, evidence_records: [{id: 'edge:1'}]};
    mockStream(`event: done\ndata: ${JSON.stringify(payload)}\n\n`);
    expect(await fetchChatStream('q', [], '', vi.fn(), vi.fn())).toMatchObject(payload);
  });
  it('rejects duplicate terminal events in a received frame batch', async () => {
    const frame = 'event: done\ndata: {"status":"success","answer":"a"}\n\n';
    mockStream(frame + frame, 'text/event-stream', false);
    await expect(fetchChatStream('q', [], '', vi.fn(), vi.fn())).rejects.toThrow('Duplicate');
  });
  function stalledFetch() {
    vi.stubGlobal('fetch', vi.fn((_url, options) => new Promise((_resolve, reject) => {
      options.signal.addEventListener('abort', () => reject(options.signal.reason), {once: true});
      if (options.signal.aborted) reject(options.signal.reason);
    })));
  }
  it('bounds JSON and SSE network waits and health checks', async () => {
    vi.useFakeTimers(); stalledFetch();
    const json = fetchChatResponse('q');
    const stream = expect(fetchChatStream('q', [], '', vi.fn(), vi.fn())).rejects.toThrow('timed out');
    const health = checkChatHealth();
    await vi.advanceTimersByTimeAsync(5000);
    expect(await health).toBe(false);
    await vi.advanceTimersByTimeAsync(CHAT_TIMEOUT_MS);
    expect((await json).status).toBe('network_error');
    await stream;
    expect(vi.getTimerCount()).toBe(0);
  });
  it('supports caller cancellation without waiting for the deadline', async () => {
    stalledFetch();
    const controller = new AbortController();
    const pending = expect(fetchChatStream('q', [], '', vi.fn(), vi.fn(), undefined, 0, undefined, controller.signal)).rejects.toThrow('cancelled');
    controller.abort(new Error('cancelled'));
    await pending;
  });
});
