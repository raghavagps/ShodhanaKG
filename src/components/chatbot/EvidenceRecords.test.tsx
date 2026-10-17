import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { EvidenceRecords } from './EvidenceRecords';

describe('Evidence records', () => {
  it('keeps stored direction and each reference beside its own edge', () => {
    render(<EvidenceRecords records={[{
      nodes: [{ id: 'milk', label: 'Cow Milk', type: 'Media' }, { id: 'method', label: 'Nirvapa', type: 'Shodhana_Method' }, { id: 'copper', label: 'Copper', type: 'Compound' }],
      edges: [
        { source: 'method', target: 'milk', type: 'USES_MEDIA', evidence: { pubmed_id: '111' }, properties: { Duration: '3 Days' } },
        { source: 'copper', target: 'method', type: 'UNDERGOES', evidence: { pubmed_id: '111' }, properties: { Procedure: 'Heating' } },
      ], matched_evidence: { type: 'pubmed_id', value: '111' },
    }, { method: 'Other method', properties: { pubmed_id: '222' } }]} />);
    const first = screen.getByRole('article', { name: 'Evidence record 1' });
    expect(within(first).getByText('Nirvapa (Shodhana Method) → uses media → Cow Milk (Media)')).toBeInTheDocument();
    expect(within(first).getByText('Copper (Compound) → undergoes → Nirvapa (Shodhana Method)')).toBeInTheDocument();
    expect(within(first).queryByText('222')).not.toBeInTheDocument();
    expect(within(screen.getByRole('article', { name: 'Evidence record 2' })).getByText('222')).toBeInTheDocument();
  });

  it('preserves legacy nested scopes and safely displays null values', () => {
    render(<EvidenceRecords records={[{ study: 'Study A', properties: null,
      validation_properties: { pubmed_id: '123' }, safety_evidence: { doi: 'separate-reference' } }]} />);
    expect(screen.getByText('validation properties')).toBeInTheDocument();
    expect(screen.getByText('safety evidence')).toBeInTheDocument();
    expect(screen.getByText('separate-reference')).toBeInTheDocument();
    expect(screen.queryByText('Not supplied')).not.toBeInTheDocument();
    expect(screen.queryByText('[object Object]')).not.toBeInTheDocument();
  });

  it('makes records beyond the first page reachable by number and navigation', () => {
    render(<EvidenceRecords records={Array.from({ length: 25 }, (_, i) => ({ media: `Media ${i + 1}` }))} />);
    expect(screen.getAllByRole('article')).toHaveLength(10);
    fireEvent.click(screen.getByRole('button', { name: 'Next records' }));
    expect(screen.getByRole('article', { name: 'Evidence record 11' })).toBeInTheDocument();
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Start at evidence record' }), { target: { value: '25' } });
    expect(screen.getByText('Showing records 25–25 of 25')).toBeInTheDocument();
    expect(screen.getByText('Media 25')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Next records' })).toBeDisabled();
    fireEvent.click(screen.getByRole('button', { name: 'Previous records' }));
    expect(screen.getByRole('article', { name: 'Evidence record 15' })).toBeInTheDocument();
  });
});
