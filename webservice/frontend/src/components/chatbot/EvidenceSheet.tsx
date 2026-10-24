import { useContext, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';
import type { ChatMessage } from '@/lib/types';
import { ChatPresentation } from './ChatPresentation';
import { EvidenceRecords } from './EvidenceRecords';

type Namespace = 'All' | 'PMID' | 'AYUSH';
export function EvidenceSheet({ message, itemId, focusedRecord, focusRequest, returnFocus, onClose, onNextPage }: {
  returnFocus?: HTMLElement | null; message: ChatMessage; itemId?: string; focusedRecord?: number; focusRequest?: number;
  onClose: () => void; onNextPage?: () => void;
}) {
  const presentation = useContext(ChatPresentation);
  const [namespace, setNamespace] = useState<Namespace>('All');
  const sheet = useRef<HTMLElement>(null);
  const close = useRef<HTMLButtonElement>(null);
  const initialFocusedRecord = useRef(focusedRecord);
  // WebKit pointer clicks do not necessarily focus buttons. Keep the explicit opener.
  const opener = useRef(returnFocus ?? document.activeElement as HTMLElement | null);
  const item = message.answer_results?.find(result => result.id === itemId);
  const records = message.context?.retrieval?.results ?? [];
  const indices = records.map((_, index) => index).filter(index => {
    if (item && !item.record_indices.includes(index)) return false;
    if (namespace === 'All') return true;
    return message.citations?.some(citation => citation.record_indices.includes(index) && (!item || item.citation_ids.includes(citation.id)) && citation.scopes.some(scope => {
      const refs = scope.identifiers.references;
      if (refs?.length) return refs.some(ref => ref.namespace === namespace && ['verified', 'declared'].includes(ref.status));
      return namespace === 'PMID' ? !!scope.identifiers.pubmed_id : !!scope.identifiers.ayush_id;
    }));
  });
  useEffect(() => {
    const trigger = opener.current;
    // Existing citation focus remains authoritative when opening an individual citation.
    if (initialFocusedRecord.current === undefined) close.current?.focus();
    return () => { requestAnimationFrame(() => { if (trigger?.isConnected && !trigger.closest('[inert]')) trigger.focus(); }); };
  }, []);
  const content = <section ref={sheet} role="dialog" aria-modal="true" aria-label="Evidence" className="kg-evidence-sheet"
    onKeyDown={event => {
      if (event.key === 'Escape') {event.preventDefault();event.stopPropagation();onClose();}
      if (event.key !== 'Tab') return;
      const controls = Array.from(sheet.current?.querySelectorAll<HTMLElement>('button:not(:disabled), a[href], input:not(:disabled), summary, [tabindex="0"]') ?? []).filter(element => !element.closest('details:not([open])') || element.tagName === 'SUMMARY');
      const first = controls[0], last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) {event.preventDefault();last?.focus();}
      else if (!event.shiftKey && document.activeElement === last) {event.preventDefault();first?.focus();}
    }}>
    <header className="kg-evidence-header"><h3>Evidence{item ? ` \u00b7 ${item.label}` : ''}</h3><button ref={close} className="kg-chat-icon" aria-label="Back to chat" onClick={onClose}><X size={18} /></button></header>
    <div className="kg-evidence-filters" aria-label="Source namespace">{(['All','PMID','AYUSH'] as const).map(value => <button key={value} aria-pressed={namespace === value} onClick={() => setNamespace(value)}>{value === 'PMID' ? 'PubMed' : value}</button>)}</div>
    <div className="kg-evidence-body">
      <p className="mb-3 text-xs">Evidence for <span>{item?.label ?? message.context?.retrieval?.entity?.name ?? message.context?.routing?.entity}</span></p>
      {!indices.length && <p>No sources in this filter.</p>}
      <EvidenceRecords key={`${itemId ?? 'all'}:${namespace}`} records={records} citations={message.citations} focusedRecord={focusedRecord} focusRequest={focusRequest} visibleRecordIndices={indices} namespaceFilter={namespace === 'All' ? undefined : namespace} />
      {message.pagination?.has_more && onNextPage && <button className="kg-chat-more" onClick={onNextPage}>View next evidence page ({message.pagination.total} total records)</button>}
    </div>
  </section>;
  return presentation?.host ? createPortal(content, presentation.host) : content;
}
