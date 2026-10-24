import { describe, it, expect, vi, afterEach } from 'vitest';
import { renderHook, waitFor, act } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { useGraphFilters } from './useGraphEntities';
import { fetchApi } from '@/lib/api';
vi.mock('@/lib/api', async () => ({ ...await vi.importActual('@/lib/api'), fetchApi: vi.fn() }));
afterEach(() => vi.resetAllMocks());
function wrapper(url: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return ({ children }) => <QueryClientProvider client={client}><MemoryRouter initialEntries={[url]}>{children}</MemoryRouter></QueryClientProvider>;
}
describe('KG selection hydration', () => {
  it('resolves legacy seeds by actual type and retains labels without graph data', async () => {
    vi.mocked(fetchApi).mockResolvedValue({ node: { id: '#51:2', type: 'Symptom', label: 'Rashes' } });
    const { result } = renderHook(() => useGraphFilters(['Disease','Symptom']), { wrapper: wrapper('/?node=%2351%3A2') });
    await waitFor(() => expect(result.current.filters.Symptom).toEqual(['#51:2']));
    expect(result.current.filters.Disease).toEqual([]);
    expect(result.current.entities['#51:2'].node.label).toBe('Rashes');
    act(() => result.current.setFilters({ Disease: [], Symptom: [] }));
    await waitFor(() => expect(result.current.filters.Symptom).toEqual([]));
  });
  it('rejects wrong type instead of assigning the node to a guessed filter', async () => {
    vi.mocked(fetchApi).mockResolvedValue({ node: { id: '#51:2', type: 'Symptom', label: 'Rashes' } });
    const { result } = renderHook(() => useGraphFilters(['Disease','Symptom']), { wrapper: wrapper('/?node=%2351%3A2&type=Disease') });
    await waitFor(() => expect(result.current.errors).toHaveLength(1));
    expect(result.current.filters).toEqual({ Disease: [], Symptom: [] });
  });
});
