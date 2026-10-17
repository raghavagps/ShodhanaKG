import { expect, test } from '@playwright/test';

const api = process.env.CHATBOT_TEST_API || 'http://127.0.0.1:8081/api';
const endpoints = {
  therapy_formulation: 'Abhyanga', media_process: 'Cow Milk',
  shodhana_method_process: 'Bhavana', process_shodhana_method: 'Marana',
  purified_compound_process: 'Shodhita Jaypala', method_toxic_substance: 'Swedana',
  process_next_process: 'Marana', next_process_process: 'Marana',
  provenance_compound_method_media: 'Copper', provenance_media_method_compound: 'Cow Milk',
  provenance_disease_therapy_formulation: 'Alopecia', provenance_formulation_therapy_disease: 'Laghu Sutshekhar',
};

test.describe('Traversal HTTP contracts (live ArcadeDB)', () => {
  for (const [endpoint, entity] of Object.entries(endpoints)) {
    test(endpoint, async ({ request }) => {
      const response = await request.get(`${api}/${endpoint}.php`, { params: { entity } });
      expect(response.status()).toBe(200);
      const body = await response.json();
      expect(body.status).toBe('success');
      expect(body.entity.name).toBe(entity);
      expect(body.results.length).toBeGreaterThan(0);
      for (const result of body.results) {
        expect(result.nodes.length).toBe(endpoint.startsWith('provenance_') ? 3 : 2);
        expect(result.edges.length).toBe(result.nodes.length - 1);
        if (endpoint.startsWith('provenance_')) {
          expect(result.matched_evidence.value).toBeTruthy();
          for (const edge of result.edges) expect(edge.evidence[result.matched_evidence.type]).toBe(result.matched_evidence.value);
        }
      }
    });
  }

  test('validates direct endpoint requests', async ({ request }) => {
    expect((await request.get(`${api}/therapy_formulation.php`)).status()).toBe(400);
    expect((await request.post(`${api}/therapy_formulation.php`)).status()).toBe(405);
  });

  for (const stream of [false, true]) {
    test(`chatbot ${stream ? 'SSE' : 'JSON'} abstention`, async ({ request }) => {
      const response = await request.post(`${api}/chatbot_response.php`, { data: { question: 'Copper ke liye kya media hai?', stream } });
      expect(response.status()).toBe(200);
      if (stream) {
        const text = await response.text();
        expect(response.headers()['content-type']).toContain('text/event-stream');
        expect(text).toContain('event: done');
        expect(text).toContain('"status":"unsupported"');
        expect(text).toContain('language_not_supported');
      } else {
        const body = await response.json();
        expect(body.status).toBe('unsupported');
        expect(body.context.reason).toBe('language_not_supported');
      }
    });
  }
});
