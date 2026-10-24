import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { IntegratedPaths } from './IntegratedPaths';
import { fetchApi } from '@/lib/api';
vi.mock('@/lib/api', () => ({ fetchApi: vi.fn() }));

describe('Integrated evidence paths', () => {
  it('keeps stored direction, properties and citations and can fetch the next page', async () => {
    const path = { nodes: [{ id: '#1:0', label: 'Raw', type: 'Compound' }, { id: '#2:0', label: 'Method', type: 'Shodhana_Method' }], edges: [{ id: '#3:0', type: 'UNDERGOES', source: '#1:0', target: '#2:0', properties: { Dose: '5 g' }, display_properties: [{ key: 'Dose', label: 'Dose', value: '5 g' }], evidence: { references: [{ namespace: 'AYUSH', status: 'verified', identifier: '76448', raw: '76448' }] } }] };
    vi.mocked(fetchApi).mockResolvedValue({ status: 'success', results: [], pagination: { has_more: false } });
    render(<IntegratedPaths paths={[path]} continuations={[{ source_type: 'Compound', entity: 'Raw', result_type: 'Disease', offset: 7 }]} />);
    fireEvent.click(screen.getByText('Evidence paths (1 loaded)'));
    await waitFor(() => expect(screen.getByText('Raw → Method')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Raw → Method'));
    await waitFor(() => expect(screen.getByText('AYUSH ID 76448')).toBeInTheDocument());
    expect(screen.getAllByText('Raw → Method')).toHaveLength(2);
    expect(screen.getByText('AYUSH ID 76448')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Search AYUSH' })).toHaveAttribute('href', 'https://arp.ayush.gov.in/research_advance_search');
    expect(screen.queryByRole('link', { name: /Open ID/ })).toBeNull();
    expect(screen.getByText('Dose:').parentElement).toHaveTextContent('5 g');
    fireEvent.click(screen.getByText('View next evidence page'));
    await waitFor(() => expect(fetchApi).toHaveBeenCalledWith(expect.stringContaining('offset=7')));
  });
});
