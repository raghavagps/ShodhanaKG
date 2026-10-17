import { useState, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { ChevronDown } from 'lucide-react';
import type { ApiNode, ApiEdge, PublicationReference, DisplayProperty } from '@/lib/types';
import { ReferenceLinks } from './ReferenceLinks';
import { EdgeProperties } from './EdgeProperties';
import { referencePresentation } from '@/lib/referencePresentation';
import { entityGraphUrl } from '@/lib/entityNavigation';

type RecordEdge = { source: ApiNode; target: ApiNode; edge: ApiEdge };
type Group = { id: string; reference: PublicationReference; records: RecordEdge[] };
const badge = 'inline-flex max-w-full items-center rounded-full border border-border bg-secondary/40 px-2.5 py-1 text-xs font-medium [overflow-wrap:anywhere]';
const relationLabel = (type: string) => type.replace(/_/g, ' ');

// Native details hides its children visually but still renders every property
// and serializes every JSON record. Mount them on first expansion, then retain
// them so collapsing/reopening does not reset nested details or selection.
function DeferredDetails({ summary, children, className, hidden }: {
  summary: ReactNode; children: () => ReactNode; className?: string; hidden?: boolean;
}) {
  const [visited, setVisited] = useState(false);
  return <details className={className} hidden={hidden} onToggle={event => {
    if (event.target === event.currentTarget && event.currentTarget.open) setVisited(true);
  }}>{summary}{visited && children()}</details>;
}

/** Presentation only: retains each supplied edge identity, endpoints and properties. */
export function ReferenceResultCard({ group }: { group: Group }) {
  const [showAll, setShowAll] = useState(false);
  const [visitedAll, setVisitedAll] = useState(false);
  const relationships = [...new Set(group.records.map(record => record.edge.type))];
  const visible = showAll ? group.records : group.records.slice(0, 5);
  const mounted = visitedAll ? group.records : visible;
  const commonSource = group.records.length > 0 && group.records.every(record => record.source.id === group.records[0].source.id) ? group.records[0].source : null;
  return <section className="reference-card min-w-0 overflow-hidden rounded-[10px] border border-border bg-card">
    <header className="reference-card-header">
      <div className="reference-card-top">
        <div className="reference-card-link"><ReferenceLinks references={[group.reference]} /></div>
        {group.reference.namespace && <span className="reference-namespace">{group.reference.namespace === 'PMID' ? 'PubMed' : group.reference.namespace === 'AYUSH' ? 'AYUSH ID' : group.reference.namespace}</span>}
        <span className="reference-edge-count">{group.records.length} supporting edges</span>
      </div>
      <div className="reference-counts">
        {relationships.map(type => <span key={type} className="reference-count-chip">{relationLabel(type)} <span>{group.records.filter(record => record.edge.type === type).length}</span></span>)}
      </div>
      {commonSource && <p className="break-words text-muted-foreground">Subject: <span className="font-medium text-foreground">{commonSource.label}</span></p>}
    </header>
    <div className="reference-card-body">
      {relationships.filter(type => visible.some(record => record.edge.type === type)).map(type => <div key={type} className="reference-group">
        <h2 className="reference-group-title">{relationLabel(type)} &middot; {group.records.filter(record => record.edge.type === type).length}</h2>
        {mounted.filter(record => record.edge.type === type).map(record => {
          const { source, target, edge } = record;
          const shown = showAll || visible.includes(record);
          return <DeferredDetails key={edge.id} hidden={!shown} className="group/edge border-t border-border/60 first:border-0" summary={
            <summary className="reference-edge-row flex cursor-pointer list-none items-center justify-between gap-3 rounded-md py-3 hover:bg-secondary/30 [&::-webkit-details-marker]:hidden">
              <span className="flex min-w-0 flex-1 flex-col gap-2 md:flex-row md:items-center md:gap-3">
                {!commonSource && <><span className="reference-entity min-w-0 flex-1 break-words font-medium">{source.label}<small>{relationLabel(source.type)}</small></span></>}
                <span data-relationship={edge.type} className={`${badge} reference-relation self-start md:self-auto`}>{relationLabel(edge.type)}</span>
                
                <span className="reference-entity min-w-0 flex-1 break-words font-medium">{target.label}<small>{relationLabel(target.type)}</small></span>
              </span>
              <ChevronDown aria-hidden="true" className="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-open/edge:rotate-180" />
            </summary>}>
            {() =>
            <div className="reference-expanded mb-3 space-y-4 rounded-md border border-border/60 bg-secondary/20 p-4">
              <div className="flex flex-wrap gap-2">{[source,target].map((node,index) => <Link className="max-w-full rounded-md border border-primary/25 bg-background px-3 py-2 text-xs font-medium text-primary hover:bg-secondary/50 [overflow-wrap:anywhere]" key={`${node.id}:${index}`} to={entityGraphUrl(node)}>Open {node.label} in Explorer</Link>)}</div>
              <div className="[&_dt]:text-xs [&_dd]:text-sm"><EdgeProperties properties={edge.display_properties as DisplayProperty[]} phase={edge.treatment_phase as string} /></div>
              <DeferredDetails className="border-t border-border pt-3" summary={<summary className="cursor-pointer text-xs font-medium text-muted-foreground">Technical details</summary>}>{() => <pre className="mt-3 whitespace-pre-wrap break-words text-xs">{JSON.stringify(referencePresentation(edge),null,2)}</pre>}</DeferredDetails>
            </div>
            }
          </DeferredDetails>;
        })}
      </div>)}
    </div>
    {group.records.length > 5 && <div className="reference-card-footer"><button className="text-sm font-medium text-primary hover:underline" aria-expanded={showAll} onClick={() => {setVisitedAll(true);setShowAll(previous => !previous);}}>{showAll ? 'Show fewer edges' : `Show all ${group.records.length} edges`}</button></div>}
  </section>;
}
