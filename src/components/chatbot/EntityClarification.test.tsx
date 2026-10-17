import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ChatMessage } from './ChatMessage';

describe('Entity clarification', () => {
  it('shows actual types and submits the exact candidate identity and requested result', () => {
    const candidate = { id: '#33:31', label: 'Cancer', type: 'Indication', result_type: 'Purified_Compound', match_method: 'supported_alternative', source: 'KG schema' };
    const select = vi.fn();
    render(<ChatMessage onSelectEntity={select} message={{role: 'bot', text: 'Choose a supported relationship.', status: 'needs_clarification', clarification: {candidates: [candidate]}}} />);
    fireEvent.click(screen.getByRole('button', {name: /Cancer.*Indication.*purified compound/i}));
    expect(select).toHaveBeenCalledWith(candidate);
    expect(screen.queryByText(/Disease/)).not.toBeInTheDocument();
  });
  it('shows a correction separately from deterministic results', () => {
    render(<ChatMessage message={{role: 'bot', text: 'Methods', status: 'success', resolution: [{mention: 'coper', normalized: 'coper', outcome: 'resolved', candidates: [{id:'#5:24',label:'Copper',type:'Compound',mention:'coper',match_method:'spelling',source:'KG label'}]}]}} />);
    expect(screen.getByText('Interpreted “coper” as Copper.')).toBeInTheDocument();
  });
});
