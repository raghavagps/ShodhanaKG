import { useQueries } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import { useMemo, type Dispatch, type SetStateAction } from 'react';
import { fetchApi, parseGraphParams } from '@/lib/api';
import { entityType } from '@/lib/entityNavigation';
import type { ApiNode } from '@/lib/types';

/** Labels and identity are hydrated independently of graph limits and visibility. */
export function useGraphEntities(ids: string[]) {
  const queries = useQueries({ queries: ids.map(id => ({
    queryKey: ['graph-entity', id],
    queryFn: async () => (await fetchApi<{ node: ApiNode }>(`node.php?${new URLSearchParams({ domain: 'integrated', id: id.replace(/^[dc]:/, ''), neighborLimit: '1' })}`)).node,
    staleTime: 60_000, retry: false,
  })) });
  return Object.fromEntries(ids.map((id, index) => [id, { node: queries[index].data, pending: queries[index].isPending, error: queries[index].isError }]));
}

/** URL is the selection source of truth, including same-page navigation and browser history. */
export function useGraphFilters<T extends string>(types: readonly T[]) {
  const [params, setParams] = useSearchParams();
  const parsed = parseGraphParams(params);
  const entities = useGraphEntities(parsed.seedIds);
  const filters = Object.fromEntries(types.map(type => [type, []])) as Record<T, string[]>;
  const errors: string[] = [];
  for (const id of parsed.seedIds) {
    const entry = entities[id];
    const declared = Object.entries(parsed.filterMap).find(([, ids]) => ids.includes(id))?.[0] ?? (params.get('node') === id ? params.get('type') : null);
    if (entry.node) {
      const type = entityType(entry.node.type) as T;
      if (!types.includes(type) || (declared && entityType(declared) !== type)) errors.push('The selected entity does not match this graph or entity type.');
      else if (!filters[type].includes(entry.node.id)) filters[type].push(entry.node.id);
    } else if (entry.error) errors.push('The selected entity is unavailable. Please choose a current KG entity.');
  }
  const setFilters: Dispatch<SetStateAction<Record<T, string[]>>> = next => {
    const value = typeof next === 'function' ? next(filters) : next;
    setParams(previous => {
      const updated = new URLSearchParams(previous);
      ['node', 'type', 'seeds', 'disease', 'compound', 'filter'].forEach(key => updated.delete(key));
      const values = Object.entries(value).flatMap(([type, ids]) => (ids as string[]).map(id => `${type}:${id}`));
      if (values.length) updated.set('filter', values.join(','));
      return updated;
    });
  };
  return { filters, setFilters, entities, errors, pending: Object.values(entities).some(entry => entry.pending) };
}

export function useGraphVisibility<T extends string>(key: string, types: readonly T[]): [Set<T>, Dispatch<SetStateAction<Set<T>>>] {
  const [params, setParams] = useSearchParams();
  const hidden = params.get(key) ?? '';
  const visible = useMemo(() => new Set(types.filter(type => !hidden.split(',').includes(type))), [hidden, types]);
  const setVisible: Dispatch<SetStateAction<Set<T>>> = next => {
    const value = typeof next === 'function' ? next(visible) : next;
    setParams(previous => {
      const result = new URLSearchParams(previous);
      const hiddenTypes = types.filter(type => !value.has(type));
      if (hiddenTypes.length) result.set(key, hiddenTypes.join(',')); else result.delete(key);
      return result;
    }, { replace: true });
  };
  return [visible, setVisible];
}
