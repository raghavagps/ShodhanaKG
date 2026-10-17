import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ChatMessage } from './ChatMessage';
import { ChatMessage as Message } from '@/lib/types';
import pilot from '../../../api/tests/fixtures/conversational_pilot_v7.json';

describe('Conversational answers', () => {
  it('renders historical string-only entities without inferring types or changing stored evidence', () => {
    const sample = pilot.rows[0];
    const message = { ...sample.response, context: sample.context, role: 'bot', text: sample.response.answer } as unknown as Message;
    const before = JSON.stringify(message);
    render(<ChatMessage message={message} />);
    fireEvent.click(screen.getByRole('button', { name: 'View source 1' }));
    const card = screen.getByRole('article', { name: 'Evidence record 1' });
    expect(within(card).getByText('Sutshekhar Rasa')).toBeVisible();
    expect(within(card).getByText('Formulation:')).toBeVisible();
    expect(within(card).getByText('Oral Medication')).toBeVisible();
    expect(within(card).getByText('Reference: 253965')).toBeVisible();
    expect(within(card).queryByRole('link')).not.toBeInTheDocument();
    expect(within(card).queryByText('Disease:')).not.toBeInTheDocument();
    expect(JSON.stringify(message)).toBe(before);
  });
  it('opens the cited record without technical prose and distinguishes DOI from PMID', () => {
    const message: Message = {
      role: 'bot', status: 'success', text: 'A recorded association [11]',
      answer_intro: 'Here is the available information.',
      answer_blocks: [{ text: 'Sutshekhar Rasa is recorded under Oral Medication for Vipadika.', citation_ids: ['11'] }],
      context: { status: 'success', question: 'q', retrieval: { status: 'success', entity: { name: 'Vipadika' }, path: [], results: Array.from({ length: 11 }, () => ({ therapy: 'Oral Medication', formulation: 'Sutshekhar Rasa' })) } },
      citations: [{ id: '11', fact_ids: ['f11'], record_indices: [10], attribution: 'record', statement: 'Sutshekhar Rasa is recorded under Oral Medication for Vipadika.', scopes: [
        { description: 'Evidence for this therapy–formulation association', identifiers: { doi: '10.1234/test', references: [{ raw: '10.1234/test', identifier: '10.1234/test', namespace: 'DOI', status: 'declared', resolution_status: 'not_checked', catalog_version: 'test' }] }, properties: { Duration: '7 days' } },
      ] }],
    };
    render(<ChatMessage message={message} />);
    expect(screen.queryByText('What this reference supports')).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'View source 11' }));
    const card = screen.getByRole('article', { name: 'Evidence record 11' });
    expect(card).toHaveFocus();
    expect(within(card).queryByText('What this reference supports')).not.toBeInTheDocument();
    expect(within(card).getByText('7 days')).toBeVisible();
    expect(message.citations![0].scopes[0].properties.Duration).toBe('7 days');
    expect(within(card).getByRole('link', { name: 'Open ID: DOI 10.1234/test' })).toHaveAttribute('href', 'https://doi.org/10.1234/test');
    expect(within(card).queryByRole('link', { name: /PMID/ })).not.toBeInTheDocument();
    expect(within(card).queryByText(/does not include a study outcome/)).not.toBeInTheDocument();
    expect(within(card).queryByText(/separate support for each step is not available/)).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'View all results and sources' })).toHaveAttribute('aria-expanded', 'true');
  });
});
