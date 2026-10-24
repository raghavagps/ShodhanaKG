import { render, screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import captured from '../../../../api/tests/fixtures/results_evaluation_answers_v2.json';
import symptoms from '../../../../api/tests/fixtures/results_first_psoriasis.json';
import { EvidenceRecords } from './EvidenceRecords';
import type { AnswerCitation } from '@/lib/types';

for (const category of ['Media', 'Disease', 'Compound', 'Symptom', 'Therapy']) {
  it(`renders available fields from a captured ${category} question without changing its data`, () => {
    const answers = [symptoms, ...captured.rows.map(row => row.response)];
    const answer = answers.find(a => a.citations.some(c => c.scopes.some(s => s.entities?.some(e => e.type === category))));
    expect(answer).toBeDefined();
    const citation = answer!.citations.find(c => c.scopes.some(s => s.entities?.some(e => e.type === category)))! as AnswerCitation;
    const before = JSON.stringify(answer);
    render(<EvidenceRecords records={answer!.context.retrieval.results} citations={[citation]} visibleRecordIndices={[citation.record_indices[0]]} />);
    expect(screen.getAllByText(`${category}:`).length).toBeGreaterThan(0);
    for (const scope of citation.scopes) {
      for (const [key, value] of Object.entries(scope.properties)) {
        if (['Dose', 'Duration', 'Result', 'Procedure'].includes(key) && typeof value === 'string' && value.trim()) {
          expect(screen.getAllByText(value, { exact: false }).length).toBeGreaterThan(0);
        }
      }
    }
    expect(document.querySelector('details, pre')).toBeNull();
    expect(JSON.stringify(answer)).toBe(before);
  });
}
