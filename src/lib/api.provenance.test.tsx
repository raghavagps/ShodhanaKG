import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { provenanceSubgraphApi, useProvenanceSubgraph } from '@/lib/api';
import type { ProvenancePath, ProvenanceSubgraphResponse } from '@/lib/types';
import type { ReactNode } from 'react';

// Mock fetch
const mockFetch = vi.fn();
global.fetch = mockFetch as unknown as typeof fetch;

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
  };
};

const mockResponse: ProvenanceSubgraphResponse = {
  ok: true,
  paths: [
    {
      disease: { id: '36', label: 'Eczema', type: 'Disease', description: null },
      edge1: { id: '#70:0', type: 'PRETREATED_BY', pubmedId: '23723667' },
      therapy: { id: '1155', label: 'Abhyanga', type: 'Therapy', description: 'whole body oil massage' },
      edge2: { id: '#84:14', type: 'USES_FORMULATION', pubmedId: '23723667', dose: null, duration: '1 Day', adjuvant: null },
      formulation: { id: '1356', label: 'Bala Taila', type: 'Formulation', description: null },
      provenance: { pubmedId: '23723667', confidence: 'high' }
    },
    {
      disease: { id: '36', label: 'Eczema', type: 'Disease', description: null },
      edge1: { id: '#68:1', type: 'TREATED_BY', pubmedId: '21455456' },
      therapy: { id: '1155', label: 'Abhyanga', type: 'Therapy', description: 'whole body oil massage' },
      edge2: { id: '#84:44', type: 'USES_FORMULATION', pubmedId: '21455456', dose: null, duration: '3 Days', adjuvant: null },
      formulation: { id: '1634', label: 'Marichadi Taila', type: 'Formulation', description: null },
      provenance: { pubmedId: '21455456', confidence: 'high' }
    }
  ],
  meta: {
    totalPaths: 2,
    uniquePubmedIds: ['23723667', '21455456'],
    queryMs: 75,
    diseaseId: '36'
  }
};

describe('provenanceSubgraphApi', () => {
  beforeEach(() => {
    mockFetch.mockClear();
  });

  it('should fetch provenance paths for a disease', async () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => mockResponse,
    });

    const result = await provenanceSubgraphApi('36');

    expect(result.paths).toHaveLength(2);
    expect(result.paths[0].disease.label).toBe('Eczema');
    expect(result.paths[0].provenance.pubmedId).toBe('23723667');
    expect(result.meta?.totalPaths).toBe(2);
  });

  it('should filter by PubMed ID', async () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        ...mockResponse,
        paths: [mockResponse.paths[0]],
        meta: { ...mockResponse.meta, totalPaths: 1 }
      }),
    });

    await provenanceSubgraphApi('36', '23723667');

    expect(mockFetch).toHaveBeenCalledWith(
      expect.stringContaining('pubmedId=23723667')
    );
  });

  it('should handle API errors', async () => {
    mockFetch.mockResolvedValueOnce({
      ok: false,
      status: 404,
      statusText: 'Not Found',
    });

    await expect(provenanceSubgraphApi('999999')).rejects.toThrow();
  });
});

describe('useProvenanceSubgraph', () => {
  it('should not fetch when diseaseId is null', () => {
    mockFetch.mockClear();
    
    const { result } = renderHook(
      () => useProvenanceSubgraph(null),
      { wrapper: createWrapper() }
    );

    expect(mockFetch).not.toHaveBeenCalled();
    expect(result.current.isLoading).toBe(false);
  });

  it('should fetch data when diseaseId is provided', async () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => mockResponse,
    });

    const { result } = renderHook(
      () => useProvenanceSubgraph('36'),
      { wrapper: createWrapper() }
    );

    expect(result.current.isLoading).toBe(true);

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.paths).toHaveLength(2);
  });

  it('should validate provenance confidence', async () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => mockResponse,
    });

    const { result } = renderHook(
      () => useProvenanceSubgraph('36'),
      { wrapper: createWrapper() }
    );

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    const paths = result.current.data?.paths || [];
    paths.forEach((path: ProvenancePath) => {
      expect(path.provenance.confidence).toBe('high');
      expect(path.edge1.pubmedId).toBe(path.edge2.pubmedId);
      expect(path.provenance.pubmedId).toBe(path.edge1.pubmedId);
    });
  });
});
