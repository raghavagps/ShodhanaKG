import type { DisplayProperty } from '@/lib/types';

/** Shared node/edge presentation; preserves arrays, zero and false. */
export function PropertyList({ properties }: { properties: DisplayProperty[] }) {
  return <dl className="font-sans">
    {properties.map((item, index) => <div key={`${item.key}:${index}`} className="grid grid-cols-[110px_minmax(0,1fr)] gap-2 py-1.5 border-b border-border/50 last:border-0">
      <dt className="text-[11px] uppercase tracking-wider text-muted-foreground [overflow-wrap:anywhere]">{item.label}:</dt>
      <dd className="m-0 text-xs text-foreground whitespace-pre-wrap [overflow-wrap:anywhere]">{typeof item.value === 'object' ? JSON.stringify(item.value, null, 2) : String(item.value)}</dd>
    </div>)}
  </dl>;
}
