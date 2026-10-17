import { useState } from 'react';
import { fetchApi } from '@/lib/api';
import type { ApiGraphResponse, PublicationReference } from '@/lib/types';
import { ReferenceLinks } from './ReferenceLinks';
import { EdgeProperties } from './EdgeProperties';

type Path = NonNullable<NonNullable<ApiGraphResponse['meta']>['evidencePaths']>[number];
type Continuation = NonNullable<NonNullable<ApiGraphResponse['meta']>['continuations']>[number];

function EvidencePath({ path }: { path: Path }) {
  const [open, setOpen] = useState(false);
  return <details className="border rounded p-2 my-1" onToggle={event => {
    if (event.target === event.currentTarget) setOpen(event.currentTarget.open);
  }}>
    <summary>{path.nodes.map(n => n.label).join(' → ')}</summary>
    {open && path.edges.map(edge => <div key={edge.id} className="border-t mt-2 pt-2">
      <p>{path.nodes.find(n => n.id === edge.source)?.label} → {path.nodes.find(n => n.id === edge.target)?.label}</p>
      <ReferenceLinks references={edge.evidence.references as PublicationReference[]} />
      <EdgeProperties properties={edge.display_properties} phase={edge.treatment_phase} />
      <details><summary>Technical details</summary><pre className="whitespace-pre-wrap break-all">{JSON.stringify(edge, null, 2)}</pre></details>
    </div>)}
  </details>;
}

export function IntegratedPaths({ paths, continuations = [] }: { paths: Path[]; continuations?: Continuation[] }) {
  const [open, setOpen] = useState(false);
  const [extra, setExtra] = useState<Path[]>([]);
  const [next, setNext] = useState(continuations);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const load = async () => {
    setBusy(true); setError('');
    try {
      const request = next[0];
      const params = new URLSearchParams({ source_type: request.source_type, entity: request.entity, result_type: request.result_type, offset: String(request.offset) });
      const response = await fetchApi<{ status: string; results: Path[]; pagination: { has_more: boolean; next_offset: number } }>(`semantic_query.php?${params}`);
      if (response.status !== 'success') throw new Error('Could not load the next evidence page.');
      setExtra(old => [...old, ...response.results]);
      setNext(old => response.pagination.has_more ? [{ ...request, offset: response.pagination.next_offset }, ...old.slice(1)] : old.slice(1));
    } catch (e) { setError(e instanceof Error ? e.message : 'Unable to load evidence.'); }
    finally { setBusy(false); }
  };
  return <details className="border-b p-2 text-xs max-h-64 overflow-auto bg-background" onToggle={event => {
    if (event.target === event.currentTarget) setOpen(event.currentTarget.open);
  }}>
    <summary className="cursor-pointer">Evidence paths ({paths.length + extra.length} loaded)</summary>
    {open && <>
    <p className="my-2">Each relationship keeps its own source. Different studies may support purification and treatment; this does not establish clinical efficacy.</p>
    {[...paths, ...extra].map(path => <EvidencePath key={path.edges.map(e => e.id).join('|')} path={path} />)}
    {next.length > 0 && <button onClick={load} disabled={busy} className="text-primary underline">{busy ? 'Loading…' : 'View next evidence page'}</button>}
    {error && <p role="alert">{error}</p>}
    </>}
  </details>;
}
