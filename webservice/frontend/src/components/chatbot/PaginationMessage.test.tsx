import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { ChatMessage } from './ChatMessage';

describe('Complete result access', () => {
  it('keeps the server page control separate from local result expansion', () => {
    const next = vi.fn();
    render(<ChatMessage message={{ role: 'bot', text: 'Results', status: 'success', answer_results: [{ id: 'f1', label: 'Entity', citation_ids: ['1'], fact_ids: ['f1'], record_indices: [0] }], pagination: { offset: 0, limit: 1000, total: 1001, has_more: true, next_offset: 1000 } }} onNextPage={next} />);
    fireEvent.click(screen.getByText('View next evidence page (1001 total records)'));
    expect(next).toHaveBeenCalledOnce();
    expect(screen.getByText('Entity')).toBeInTheDocument();
  });
});
