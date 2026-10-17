import { useEffect, useState } from 'react';
import './AdvancedSearch.css';
import { useQuery } from '@tanstack/react-query';
import { fetchApi } from '@/lib/api';
import type { ApiNode, ApiEdge, PublicationReference } from '@/lib/types';
import { ReferenceResultCard } from '@/components/explorer/ReferenceResultCard';

interface Results { groups: { id: string; reference: PublicationReference; records: {source: ApiNode; target: ApiNode; edge: ApiEdge}[] }[]; totalGroups: number; totalEdges: number; nextCursor: string | null; types: string[]; relationships: string[]; fetchedAt: string }
const initial = { namespace: 'both', identifier: '', q: '', domain: 'integrated', type: '', relationship: '', limit: '25' };
function validDomainFilters(form: typeof initial, facets: Pick<Results, 'types' | 'relationships'>) {
  return {...form, type: facets.types.includes(form.type) ? form.type : '', relationship: facets.relationships.includes(form.relationship) ? form.relationship : ''};
}
const storageKey = 'shodhanakg-reference-search-v1';
function restoreSearch() {
  try {
    const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    const clean = (value: Record<string,string>) => Object.fromEntries(Object.entries(initial).map(([key,fallback]) => [key, typeof value?.[key] === 'string' ? value[key] : fallback])) as typeof initial;
    return saved ? {form: clean(saved.form), submitted: clean(saved.submitted), cursor: typeof saved.cursor === 'string' ? saved.cursor : ''} : null;
  } catch { return null; }
}
export default function AdvancedSearch() {
  const [saved] = useState(restoreSearch);
  const [form, setForm] = useState(saved?.form ?? initial);
  const [submitted, setSubmitted] = useState(saved?.submitted ?? initial);
  const [cursor, setCursor] = useState(saved?.cursor ?? '');
  const [notice, setNotice] = useState('');
  useEffect(() => {try {sessionStorage.setItem(storageKey, JSON.stringify({form,submitted,cursor}));} catch { /* Storage can be disabled. */ }}, [form,submitted,cursor]);
  const facets = useQuery({queryKey: ['reference-search-facets',form.domain], queryFn: () => fetchApi<Results>('reference-search.php?' + new URLSearchParams({domain:form.domain,facets:'1'})), staleTime:60000});
  useEffect(() => {if(facets.data) setForm(previous => validDomainFilters(previous, facets.data));},[facets.data]);
  const result = useQuery({ queryKey: ['reference-search', submitted, cursor], queryFn: () => fetchApi<Results>('reference-search.php?' + new URLSearchParams({...submitted, cursor})), retry: false });
  useEffect(() => {if(cursor && result.isError && /400/.test(result.error.message)) {setCursor('');setNotice('Stored pagination is no longer valid. Showing the first page.');}},[cursor,result.isError,result.error]);
  const field = (key: keyof typeof initial) => ({'aria-label': ({namespace:'Reference namespace',identifier:'ID number',q:'Keywords in stored records',domain:'Domain',type:'Entity type',relationship:'Relationship',limit:'References per page'})[key], value: form[key], onChange: (event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {const value=event.target.value;setForm(previous=>({...previous,[key]:value}));if(key==='limit'){setSubmitted(previous=>({...previous,limit:value}));setCursor('');}}, className:'mt-1.5 h-10 w-full min-w-0 rounded-md border border-input bg-background px-3 text-sm font-normal focus:outline-none focus:ring-2 focus:ring-ring'});
  const [sort, setSort] = useState('original');
  const reset = () => {setForm({...initial});setSubmitted({...initial});setCursor('');setNotice('');};
  const removeFilter = (key: keyof typeof initial) => {
    const next = {...submitted, [key]: initial[key]};
    if (key === 'domain') {next.type = '';next.relationship = '';}
    setForm(next);setSubmitted(next);setCursor('');setNotice('');
  };
  const filterLabels = {namespace:'Namespace',identifier:'ID number',q:'Keyword',domain:'Domain',type:'Entity type',relationship:'Relationship'};
  const activeFilters = (Object.keys(filterLabels) as (keyof typeof filterLabels)[]).filter(key => submitted[key] !== initial[key]);
  const groups = [...(result.data?.groups ?? [])];
  if (sort === 'identifier') groups.sort((a,b) => a.reference.identifier.localeCompare(b.reference.identifier,undefined,{numeric:true}));
  if (sort === 'edges') groups.sort((a,b) => b.records.length-a.records.length);
  // ReferenceSearch cursors contain a snapshot signature and numeric offset.
  // Reuse the server-issued signature; stale snapshots retain the existing 400 recovery.
  const pageSize = Number(submitted.limit);
  const pageCount = Math.max(1, Math.ceil((result.data?.totalGroups ?? 0) / pageSize));
  const currentPage = cursor ? Math.floor(Number(cursor.split(':')[1]) / pageSize) + 1 : 1;
  const signature = (result.data?.nextCursor || cursor).split(':')[0];
  const pageNumbers = Array.from({length: pageCount}, (_, index) => index + 1)
    .filter(page => pageCount <= 7 || page === 1 || page === pageCount || Math.abs(page - currentPage) <= 1 || (currentPage <= 3 && page <= 5) || (currentPage >= pageCount - 2 && page >= pageCount - 4));
  const goToPage = (page: number) => {
    if (page < 1 || page > pageCount || result.isFetching) return;
    if (page === 1) setCursor('');
    else if (signature) setCursor(`${signature}:${(page - 1) * pageSize}`);
  };
  const outline = 'inline-flex items-center justify-center rounded-md border border-border bg-background px-3 py-2 text-sm text-primary hover:bg-secondary/50 disabled:opacity-50';
  return <main className="advanced-search mx-auto w-full max-w-[1100px] space-y-6 px-4 py-8 text-sm leading-relaxed sm:px-6">
    <header className="space-y-2"><h1 className="text-3xl font-display">Advanced Search</h1><p className="text-muted-foreground">Search references and associated records stored in ShodhanaKG, not external publications or article full text.</p></header>
    <form className="search-filter-panel grid grid-cols-12 gap-4 rounded-[10px] border border-border bg-card p-5 [&>label]:col-span-12 [&>label]:text-xs [&>label]:font-medium md:[&>label]:col-span-4" onSubmit={event => {
      event.preventDefault();
      if (!facets.data || facets.isFetching || facets.isError) return;
      // A native select can appear empty while React still holds a value from
      // the previous domain. Validate the actual submitted state, too.
      const next = validDomainFilters(form, facets.data);
      setForm(next);setSubmitted(next);setCursor('');setNotice('');
    }}>
      <label>Reference namespace<select {...field('namespace')}><option value="both">PubMed and AYUSH</option><option value="PMID">PubMed</option><option value="AYUSH">AYUSH</option></select></label>
      <label>ID number<input {...field('identifier')} maxLength={200} /></label>
      <label>Keywords<input {...field('q')} maxLength={200} /></label>
      <label>Domain<select {...field('domain')}><option value="integrated">All domains</option><option value="disease">Disease</option><option value="compound">Compound</option></select></label>
      <label>Entity type<select {...field('type')}><option value="">All types</option>{facets.data?.types.map(type => <option key={type}>{type}</option>)}</select></label>
      <label>Relationship<select {...field('relationship')}><option value="">All relationships</option>{facets.data?.relationships.map(type => <option key={type}>{type}</option>)}</select></label>
      <label className="search-page-size">References per page<select {...field('limit')}>{['25','50','100'].map(n=><option key={n} value={n}>{n}</option>)}</select></label>
      <div className="search-actions col-span-12 flex flex-wrap items-end justify-end gap-3 md:col-span-8">
        <button type="button" onClick={reset} className="h-10 px-3 text-muted-foreground hover:text-foreground">Reset</button>
        <button disabled={facets.isFetching || facets.isError} className="h-10 rounded-md bg-primary px-4 font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50" type="submit">Search KG references</button>
      </div>
    </form>
    {notice && <p role="status">{notice}</p>}
    {facets.isError && <p role="alert">Unable to load domain filters. <button className="underline" onClick={()=>facets.refetch()}>Retry filters</button></p>}
    {result.isError && <p role="alert" className="rounded-[10px] border border-destructive/30 bg-card p-4">{result.error.message} <button onClick={() => result.refetch()} className="underline">Retry</button></p>}
    <div className="search-summary space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        {result.data && <p role="status"><span className="font-medium">{result.data.totalGroups.toLocaleString()}</span> matching references &middot; <span className="font-medium">{result.data.totalEdges.toLocaleString()}</span> supporting edges</p>}
        <label className="flex items-center gap-2 text-xs text-muted-foreground">Sort this page<select className="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground" value={sort} onChange={event => setSort(event.target.value)}><option value="original">Original order</option><option value="identifier">Reference ID</option><option value="edges">Most supporting edges</option></select></label>
      </div>
      {activeFilters.length > 0 && <div className="flex flex-wrap items-center gap-2">{activeFilters.map(key => <button key={key} onClick={()=>removeFilter(key)} aria-label={`Remove ${filterLabels[key]} filter`} className="max-w-full rounded-full border border-border bg-secondary/40 px-3 py-1 text-xs [overflow-wrap:anywhere]">{filterLabels[key]}: {submitted[key].replace(/_/g,' ')} <span aria-hidden="true">&times;</span></button>)}<button className="px-2 py-1 text-xs text-primary underline" onClick={reset}>Clear all</button></div>}
    </div>
    {result.isFetching && <div role="status" aria-label="Searching KG records" className="space-y-4"><span className="sr-only">Searching KG records...</span>{[0,1,2].map(n=><div key={n} className="space-y-4 rounded-[10px] border border-border bg-card p-5 motion-safe:animate-pulse"><div className="h-5 w-1/3 rounded bg-muted"/><div className="h-3 w-3/4 rounded bg-muted"/><div className="h-3 w-1/2 rounded bg-muted"/></div>)}</div>}
    {result.data && <>
      {!result.isFetching && !groups.length && <div className="rounded-[10px] border border-border bg-card p-8 text-center"><p className="mb-4 font-medium">No references match these filters</p><button className={outline} onClick={reset}>Reset</button></div>}
      <div className="space-y-4" aria-busy={result.isFetching}>{groups.map(group => <ReferenceResultCard key={group.id} group={group} />)}</div>
      <nav aria-label="Search results pagination" className="search-pagination flex flex-wrap items-center justify-center gap-1.5 pt-4">
        <button className={outline} disabled={result.isFetching || currentPage <= 1} onClick={() => goToPage(currentPage - 1)}>Prev</button>
        {pageNumbers.map((page, index) => <span key={page} className="inline-flex items-center gap-1.5">
          {index > 0 && page > pageNumbers[index - 1] + 1 && <span aria-hidden="true" className="px-1 text-muted-foreground">&hellip;</span>}
          <button aria-label={`Page ${page}`} aria-current={page === currentPage ? 'page' : undefined} disabled={result.isFetching} onClick={() => goToPage(page)} className={`${outline} min-w-9 ${page === currentPage ? '!bg-primary !text-primary-foreground font-semibold' : ''}`}>{page}</button>
        </span>)}
        <button className={outline} disabled={result.isFetching || !result.data.nextCursor} onClick={() => goToPage(currentPage + 1)}>Next</button>
      </nav>
    </>}
  </main>;
}
