import { fireEvent, render, screen, within, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import sample from '../../../../api/tests/fixtures/results_first_psoriasis.json';
import { ChatMessage } from './ChatMessage';
import { ChatMessage as Message } from '@/lib/types';

describe('Results-first answers', () => {
  it('restores focus to each explicit opener even when clicking did not focus it', async () => {
    const message = { ...sample, role: 'bot', text: sample.answer } as unknown as Message;
    render(<ChatMessage message={message} />);
    for (const name of [`Evidence for ${sample.answer_results[0].label}`, 'View source 1', 'View all 38 sources']) {
      const trigger = screen.getByRole('button', { name });
      trigger.blur();
      fireEvent.click(trigger);
      fireEvent.keyDown(screen.getByRole('dialog', { name: 'Evidence' }), { key: 'Escape' });
      await waitFor(() => expect(trigger).toHaveFocus());
    }
  });
  it('groups 19 symptoms, expands all, and focuses any of the 38 supporting records', () => {
    const message = { ...sample, role: 'bot', text: sample.answer } as unknown as Message;
    render(<ChatMessage message={message} />);
    const list = screen.getByRole('list', { name: 'Retrieved results' });
    expect(within(list).getAllByRole('listitem')).toHaveLength(5);
    fireEvent.click(screen.getByRole('button', { name: 'Show all 19' }));
    expect(within(list).getAllByRole('listitem')).toHaveLength(19);
    expect(within(list).getAllByRole('button', { name: /^View source/ })).toHaveLength(38);
    fireEvent.click(screen.getByRole('button', { name: 'View source 38' }));
    const card = screen.getByRole('article', { name: 'Evidence record 38' });
    expect(card).toHaveFocus();
    expect(within(card).getByText('Disease → Symptom', { exact: false })).toBeVisible();
    expect(screen.getByText('Showing sources 38–38 of 38')).toBeVisible();
    expect(within(card).queryByText('Not supplied')).not.toBeInTheDocument();
    expect(within(card).queryByText('Technical details')).not.toBeInTheDocument();
  });

  it('renders public scalar edge fields while preserving the complete data', () => {
    const message = structuredClone({ ...sample, role: 'bot', text: sample.answer }) as unknown as Message;
    message.citations![0].scopes[0].properties = {
      custom_measurement: 'Measured value', zero: 0, observed: false,
      missing: null, empty: ' ', absent: 'Not supplied', nested: { blank: null, detail: 'Nested value' },
    };
    const before = JSON.stringify(message);
    render(<ChatMessage message={message} />);
    fireEvent.click(screen.getByRole('button', { name: 'View source 1' }));
    const card = screen.getByRole('article', { name: 'Evidence record 1' });
    expect(card.querySelector('details, pre')).toBeNull();
    expect(JSON.stringify(message)).toBe(before);
    expect(within(card).getByText('Measured value')).toBeVisible();
    expect(within(card).getByText('0')).toBeVisible();
    expect(within(card).getByText('false')).toBeVisible();
    expect(within(card).queryByText('Nested value')).not.toBeInTheDocument();
    expect(within(card).queryByText('missing:')).not.toBeInTheDocument();
    expect(within(card).queryByText('Not supplied')).not.toBeInTheDocument();
  });
});
