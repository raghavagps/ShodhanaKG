import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { IntegratedPaths } from './IntegratedPaths';

vi.mock('@/lib/api', () => ({ fetchApi: vi.fn() }));

describe('Evidence disclosure readiness', () => {
  it('retains all path summaries but mounts only the opened path properties and raw evidence', async () => {
    const paths = Array.from({ length: 50 }, (_, i) => ({
      nodes: [{ id: `source-${i}`, label: `Source ${i}`, type: 'Compound' }, { id: `target-${i}`, label: `Target ${i}`, type: 'Shodhana_Method' }],
      edges: [{ id: `edge-${i}`, source: `source-${i}`, target: `target-${i}`, type: 'UNDERGOES', properties: { Procedure: `Procedure ${i}` }, display_properties: [{ key: 'Procedure', label: 'Procedure', value: `Procedure ${i}` }], evidence: { references: [] } }],
    }));
    const { container } = render(<IntegratedPaths paths={paths} />);
    expect(screen.getByText('Evidence paths (50 loaded)')).toBeInTheDocument();
    expect(container.querySelectorAll('pre')).toHaveLength(0);
    expect(screen.queryByText('Procedure 0')).toBeNull();
    fireEvent.click(screen.getByText('Evidence paths (50 loaded)'));
    await waitFor(() => expect(container.querySelectorAll('summary')).toHaveLength(51));
    expect(container.querySelectorAll('pre')).toHaveLength(0);
    fireEvent.click(screen.getByText('Source 0 → Target 0'));
    await waitFor(() => expect(screen.getByText('Procedure 0')).toBeInTheDocument());
    expect(screen.queryByText('Procedure 1')).toBeNull();
    expect(container.querySelectorAll('pre')).toHaveLength(1);
    expect(container.querySelector('pre')).toHaveTextContent('edge-0');
    fireEvent.click(screen.getByText('Technical details'));
    await waitFor(() => expect(screen.getByText('Procedure 0')).toBeVisible());
    fireEvent.click(screen.getAllByText('Source 0 → Target 0')[0]);
    await waitFor(() => expect(screen.queryByText('Procedure 0')).toBeNull());
    fireEvent.click(screen.getByText('Source 49 → Target 49'));
    await waitFor(() => expect(screen.getByText('Procedure 49')).toBeVisible());
    expect(container.querySelector('pre')).toHaveTextContent('edge-49');
  });
});
