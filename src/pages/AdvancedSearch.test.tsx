import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import AdvancedSearch from './AdvancedSearch';
import { fetchApi } from '@/lib/api';

vi.mock('@/lib/api', () => ({ fetchApi: vi.fn() }));
vi.mock('@/components/explorer/ReferenceResultCard', () => ({ ReferenceResultCard: () => null }));
const empty = {groups: [], totalGroups: 0, totalEdges: 0, nextCursor: null};
const requests: URLSearchParams[] = [];
let resolveDisease: (value: unknown) => void;
let client: QueryClient;
beforeEach(() => {
  sessionStorage.clear();requests.length = 0;
  const disease = new Promise(resolve => { resolveDisease = resolve; });
  vi.mocked(fetchApi).mockImplementation(async path => {
    const params = new URLSearchParams(path.split('?')[1]);
    if (params.has('facets')) {
      if (params.get('domain') === 'disease') return disease;
      return {...empty, types: ['Compound', 'Shared'], relationships: ['UNDERGOES', 'SHARED']};
    }
    requests.push(params);
    return {...empty, types: [], relationships: []};
  });
  client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  render(<QueryClientProvider client={client}><AdvancedSearch /></QueryClientProvider>);
});
afterEach(() => {cleanup();client.clear();vi.clearAllMocks();});

it('never submits stale dependent filters while the new domain metadata is pending', async () => {
  await screen.findByRole('option', {name: 'Compound'});
  fireEvent.change(screen.getByLabelText('Entity type'), {target: {value: 'Compound'}});
  fireEvent.change(screen.getByLabelText('Relationship'), {target: {value: 'UNDERGOES'}});
  fireEvent.change(screen.getByLabelText('Domain'), {target: {value: 'disease'}});
  const form = screen.getByRole('button', {name: 'Search KG references'}).closest('form')!;
  const count = requests.length;
  fireEvent.submit(form); // Also exercise keyboard/native submission, not only the disabled button.
  await act(async () => { await Promise.resolve(); });
  expect(requests).toHaveLength(count);
  await act(async () => {resolveDisease({...empty, types: ['Disease'], relationships: ['TREATED_BY']});});
  await waitFor(() => expect(screen.getByRole('button', {name: 'Search KG references'})).toBeEnabled());
  fireEvent.change(screen.getByLabelText('Keywords in stored records'), {target: {value: 'Psoriasis'}});
  fireEvent.submit(form);
  await waitFor(() => expect(requests.at(-1)?.get('q')).toBe('Psoriasis'));
  expect(requests.at(-1)?.get('domain')).toBe('disease');
  expect(requests.at(-1)?.get('type')).toBe('');
  expect(requests.at(-1)?.get('relationship')).toBe('');
  expect(screen.getByLabelText('Entity type')).toHaveValue('');
});

it('retains filters that are valid in both domains and preserves the chosen page size', async () => {
  await screen.findByRole('option', {name: 'Shared'});
  fireEvent.change(screen.getByLabelText('Entity type'), {target: {value: 'Shared'}});
  fireEvent.change(screen.getByLabelText('Relationship'), {target: {value: 'SHARED'}});
  fireEvent.change(screen.getByLabelText('Domain'), {target: {value: 'disease'}});
  await act(async () => {resolveDisease({...empty, types: ['Disease', 'Shared'], relationships: ['TREATED_BY', 'SHARED']});});
  await waitFor(() => expect(screen.getByRole('button', {name: 'Search KG references'})).toBeEnabled());
  fireEvent.change(screen.getByLabelText('References per page'), {target: {value: '50'}});
  fireEvent.click(screen.getByRole('button', {name: 'Search KG references'}));
  await waitFor(() => expect(requests.at(-1)?.get('domain')).toBe('disease'));
  expect(requests.at(-1)?.get('type')).toBe('Shared');
  expect(requests.at(-1)?.get('relationship')).toBe('SHARED');
  expect(requests.at(-1)?.get('limit')).toBe('50');
});
