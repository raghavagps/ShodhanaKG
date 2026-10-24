import { useEffect, useMemo, useState } from 'react';
import { useInfiniteQuery, type InfiniteData } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import type { ApiNode, ApiEdge, Domain, ApiGraphResponse } from '@/lib/types';
import type { IntegratedMode } from '@/lib/api';

interface Page { nodes: ApiNode[]; edges: ApiEdge[]; meta: NonNullable<ApiGraphResponse['meta']> & {totalNodes: number; totalEdges: number; loadedNodes: number; loadedEdges: number; cursor: string | null; withinBudget: boolean; effectiveBudget: number} }
const limitOptions = ['50', '100', '200', 'all', 'custom'];
const validLimit = (value: string) => /^\d+$/.test(value) && Number(value) >= 1 && Number(value) <= 100000;
export function useProgressiveGraph(domain: Domain, seeds: string[], depth = 1, _nodeLimit = 50, _edgeLimit = 1000, integratedMode: IntegratedMode = '1hop', visibleTypes?: Set<string>, visibleEdges?: Set<string>) {
  const [params,setParams] = useSearchParams();
  const rawLimit = params.get('nodeLimit') ?? '50';
  // Preserve older bookmarked numeric limits as Custom values.
  const selectedLimit = limitOptions.includes(rawLimit) ? rawLimit : validLimit(rawLimit) ? 'custom' : '50';
  const savedCustom = selectedLimit === 'custom' && validLimit(rawLimit) ? rawLimit : params.get('customNodeLimit') ?? '50';
  const customLimit = validLimit(savedCustom) ? savedCustom : '50';
  const [customDraft, setCustomDraft] = useState(customLimit);
  useEffect(() => { setCustomDraft(customLimit); }, [customLimit]);
  const budget = selectedLimit === 'all' ? 100000 : Number(selectedLimit === 'custom' ? customLimit : selectedLimit);
  const scope = JSON.stringify([domain,seeds,depth,integratedMode,params.get('hideTypes'),params.get('hideEdges'),visibleTypes && [...visibleTypes].sort(),visibleEdges && [...visibleEdges].sort()]);
  const query = useInfiniteQuery<Page, Error, InfiniteData<Page, string>, readonly unknown[], string>({
    queryKey: ['progressive-graph',scope,budget], placeholderData: (previous, previousQuery) => previousQuery?.queryKey[1] === scope ? previous : undefined, initialPageParam:'', enabled: seeds.length>0, retry:false,
    queryFn: async ({pageParam,signal}) => {
      const search = new URLSearchParams({domain,seeds:seeds.join(','),depth:String(depth),integratedMode,progressive:'1',budget:String(budget),cursor:pageParam});
      // Exclusion filters preserve canonical frontend type names without guessing a schema list.
      search.set('hideTypes', params.get('hideTypes') ?? '');search.set('hideEdges',params.get('hideEdges') ?? '');
      if (visibleTypes) search.set('visibleTypes',[...visibleTypes].join(','));
      if (visibleEdges) search.set('visibleEdges',[...visibleEdges].join(','));
      const base=import.meta.env.VITE_API_BASE || import.meta.env.BASE_URL.replace(/\/$/,'');
      const response=await fetch(`${base}/api/graph.php?${search}`,{signal});const body=await response.json();
      if(!response.ok || body.ok===false) throw new Error(body.error?.message || 'Graph loading failed');return body as Page;
    },
    getNextPageParam: last => last.meta.cursor ?? undefined,
    staleTime:60000,
  });
  const last=query.data?.pages.at(-1)?.meta;
  const {isFetching,isError,hasNextPage,fetchNextPage} = query;
  useEffect(()=>{
    if(!query.isPlaceholderData && !isFetching && !isError && hasNextPage && last?.withinBudget) void fetchNextPage();
  },[query.isPlaceholderData,last,isFetching,isError,hasNextPage,fetchNextPage]);
  const data=useMemo(()=>query.data?({
    nodes:Array.from(new Map(query.data.pages.flatMap(page=>page.nodes).map(node=>[node.id,node])).values()),
    edges:Array.from(new Map(query.data.pages.flatMap(page=>page.edges).map(edge=>[edge.id,edge])).values()),meta:last ? {...last,evidencePaths:query.data.pages.flatMap(page=>page.meta.evidencePaths ?? [])} : undefined,
  }):undefined,[query.data,last]);
  const controls=<div className="px-4 py-2 border-b border-border/60 text-xs">
    <label className="flex flex-wrap items-center gap-2">Node limit
      <select aria-label="Node limit" className="rounded border border-input bg-background px-2 py-1" value={selectedLimit}
        onChange={event=>{setCustomDraft(customLimit);setParams(previous=>{const next=new URLSearchParams(previous);next.set('nodeLimit',event.target.value);if(event.target.value === 'custom') next.set('customNodeLimit',customLimit);return next;},{replace:true});}}>
        {limitOptions.map(value=><option key={value} value={value}>{value === 'all' ? 'All' : value === 'custom' ? 'Custom' : value}</option>)}
      </select>
    </label>
    {selectedLimit === 'custom' && <div className="mt-2">
      <label className="flex flex-wrap items-center gap-2">Custom node count
        <input aria-label="Custom node count" type="number" min={1} max={100000} step={1}
          className="w-28 rounded border border-input bg-background px-2 py-1" value={customDraft}
          aria-invalid={!validLimit(customDraft)} aria-describedby={!validLimit(customDraft) ? 'graph-limit-error' : undefined}
          onChange={event=>{const value=event.target.value;setCustomDraft(value);if(validLimit(value)) setParams(previous=>{const next=new URLSearchParams(previous);next.set('nodeLimit','custom');next.set('customNodeLimit',String(Number(value)));return next;},{replace:true});}} />
      </label>
      {!validLimit(customDraft) && <p id="graph-limit-error" className="mt-1 text-destructive">Enter a whole number from 1 to 100,000.</p>}
    </div>}
  </div>;
  return {...query,data,controls};
}
