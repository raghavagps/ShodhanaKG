import type { PublicationReference } from '@/lib/types';
import { referenceUrl } from '@/lib/referenceUrl';

export function ReferenceLinks({ references, raw, minimalist = false }: { references?: PublicationReference[]; raw?: string; minimalist?: boolean }) {
  if (!references?.length) return raw ? <span>Reference: {raw}</span> : null;
  return <>{references.map((ref, index) => {
    const classified = ref.namespace && ['verified', 'declared'].includes(ref.status);
    const text = classified ? `${ref.namespace === 'AYUSH' ? 'AYUSH ID' : ref.namespace} ${ref.identifier}` : `Reference: ${ref.raw}`;
    const href = referenceUrl(ref);
    return <span key={index} className={minimalist
      ? "flex flex-wrap items-center gap-2 py-1.5 border-b border-border/50 last:border-0 font-sans text-xs [overflow-wrap:anywhere]"
      : "my-2 flex flex-wrap items-center gap-2 rounded-lg border border-border/70 bg-secondary/20 p-3 font-sans text-sm [overflow-wrap:anywhere]"}>
      <span className={minimalist ? "text-[11px] uppercase tracking-wider text-muted-foreground" : "text-xs font-semibold text-muted-foreground"}>ID</span>
      <span className="min-w-0 flex-1">{text}</span>
      {href ? <a href={href} target="_blank" rel="noopener noreferrer" aria-label={`Open ID: ${text}`} className="inline-flex shrink-0 rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground hover:bg-primary/90">Open ID →</a>
        : classified && ref.namespace === 'AYUSH' ? <span className="flex flex-wrap gap-2 text-xs">
          <button onClick={() => navigator.clipboard?.writeText(ref.identifier).catch(() => {})} className="text-primary underline">Copy ID</button>
          <a href="https://arp.ayush.gov.in/research_advance_search" target="_blank" rel="noopener noreferrer" className="text-primary underline">Search AYUSH</a>
        </span> : null}
    </span>;
  })}</>;
}
