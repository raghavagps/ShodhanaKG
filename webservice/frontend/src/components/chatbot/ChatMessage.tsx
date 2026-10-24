import React, { useState, useContext, useId, useRef } from 'react';
import { ChevronDown, ChevronUp, Database } from 'lucide-react';
import { cn } from '@/lib/utils';
import { ChatMessage as ChatMessageType } from '@/lib/types';
import { ReferenceLinks } from '@/components/explorer/ReferenceLinks';
import { TypingIndicator } from './TypingIndicator';
import { EvidenceSheet } from './EvidenceSheet';
import { ChatPresentation } from './ChatPresentation';

interface ChatMessageProps {
  message: ChatMessageType;
  onRetry?: () => void;
  onNextPage?: () => void;
  onSelectEntity?: (candidate: import('@/lib/types').EntityCandidate) => void;
}

const escapeHtml = (unsafe: string) => {
  return unsafe
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
};

export const ChatMessage: React.FC<ChatMessageProps> = ({ message, onRetry, onNextPage, onSelectEntity }) => {
  const isBot = message.role === 'bot';
  const presentation = useContext(ChatPresentation);
  const visualId = useId();
  const evidenceTrigger = useRef<HTMLButtonElement | null>(null);
  const [itemId, setItemId] = useState<string | undefined>();
  const sourcesOpen = (local: boolean) => local && (!presentation || presentation.sheetOwner === visualId);
  const faq = (message.context as unknown as {faq?: {sources?: {title: string; url: string}[]}})?.faq;
  const [showSources, setShowSources] = useState(false);
  const [showAllResults, setShowAllResults] = useState(false);
  const [focusedRecord, setFocusedRecord] = useState<number | undefined>();
  const [focusRequest, setFocusRequest] = useState(0);
  const openCitation = (id: string, trigger: HTMLButtonElement) => {
    const citation = message.citations?.find(c => c.id === id);
    if (!citation) return;
    evidenceTrigger.current = trigger;
    setFocusedRecord(citation.record_indices[0]);
    setFocusRequest(n => n + 1);
    setItemId(undefined);
    presentation?.setSheetOwner(visualId);
    setShowSources(true);
  };

  // Basic parser for **bold** and [PubMed: 12345] citations safely
  const renderText = (text: string) => {
    if (!text) return null;

    // Split by PubMed citations
    const parts = text.split(/\[(?:PubMed|PMID):\s*(\d+)\]/gi);
    
    return parts.map((part, index) => {
      if (index % 2 === 1) {
        return <ReferenceLinks key={index} raw={part} />;
      }
      
      let safeHtml = escapeHtml(part);
      safeHtml = safeHtml
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n- (.*?)(?=\n|$)/g, '<br/>• $1')
        .replace(/\n/g, '<br/>');
        
      return <span key={index} dangerouslySetInnerHTML={{ __html: safeHtml }} />;
    });
  };

  const hasContext = message.status === 'success' && message.context?.retrieval?.results && message.context.retrieval.results.length > 0;

  return (
    <div className={cn("flex w-full", isBot ? "justify-start" : "justify-end")}>
      <div
        className={cn(
          "flex flex-col gap-2",
          isBot 
            ? "kg-chat-bot" 
            : "kg-chat-user"
        )}
      >
        {faq?.sources?.length ? <div className="text-xs border-b pb-2 mb-2"><p className="font-medium">Background sources (not KG edge evidence)</p>{faq.sources.map(source => <p key={source.url}><a className="underline text-primary" href={source.url} target="_blank" rel="noopener noreferrer">{source.title}</a></p>)}</div> : null}
        {message.resolution?.flatMap(r => r.outcome === 'resolved' ? r.candidates.filter(c => c.match_method === 'spelling') : []).map(c =>
          <p key={c.id} className="text-xs text-muted-foreground">Interpreted “{c.mention}” as {c.label}.</p>
        )}
        {message.clarification?.candidates.length ? <div className="flex flex-col gap-2" aria-label="Choose a KG entity">
          {message.clarification.candidates.map(c => <button key={`${c.id}:${c.result_type}`} type="button"
            disabled={!onSelectEntity || message.isStreaming} onClick={() => onSelectEntity?.(c)}
            className="rounded-md border border-border p-2 text-left hover:bg-background disabled:opacity-50">
            {c.label} <span className="text-muted-foreground">({c.type.replace(/_/g, ' ')})</span>
            {c.result_type && <span className="block text-xs">Explore {c.result_type.replace(/_/g, ' ').toLowerCase()}</span>}
          </button>)}
        </div> : null}
        {(!message.text && message.status !== 'network_error' && message.status !== 'llm_error') ? (
          <TypingIndicator />
        ) : (
          message.status !== 'network_error' && (
            <div className="kg-chat-text leading-relaxed break-words min-w-0">
              {message.answer_results?.length && message.status === 'success' ? (
                <div className="space-y-3">
                  {message.answer_heading && <h3 className="font-semibold">{message.answer_heading}</h3>}
                  {message.answer_intro && <p>{message.answer_intro}</p>}
                  <ol aria-label="Retrieved results" className="kg-result-list">
                    {message.answer_results.slice(0, showAllResults ? undefined : 5).map(result => <li key={result.id}>
                      <button type="button" className="kg-result-row" aria-label={`Evidence for ${result.label}`} onClick={event => {evidenceTrigger.current = event.currentTarget;setItemId(result.id);setFocusedRecord(undefined);presentation?.setSheetOwner(visualId);setShowSources(true);}}>
                        <span>{result.label}</span><span className="kg-result-count">{result.citation_ids.length} {result.citation_ids.length === 1 ? 'source' : 'sources'}</span>
                        <span className="kg-result-bar" aria-hidden="true"><span style={{width:`${result.citation_ids.length / Math.max(1, ...message.answer_results.map(item => item.citation_ids.length)) * 100}%`}} /></span>
                      </button>
                      <span className="kg-result-citations">{result.citation_ids.map(id => <button key={id} type="button" aria-label={`View source ${id}`} onClick={event => openCitation(id, event.currentTarget)} className="text-primary underline px-0.5 text-xs">[{id}]</button>)}</span>
                    </li>)}
                  </ol>
                  {message.answer_results.length > 5 && <button type="button" aria-expanded={showAllResults} className="text-primary underline" onClick={() => setShowAllResults(value => !value)}>
                    {showAllResults ? 'Show top 5' : `Show all ${message.answer_results.length}`}
                  </button>}
                </div>
              ) : message.answer_blocks?.length && message.status === 'success' ? (
                <div className="space-y-3">
                  {message.answer_intro && <p>{message.answer_intro}</p>}
                  {message.answer_blocks.map((block, i) => <p key={i}>
                    {block.text}{' '}
                    {block.citation_ids.map(id => <button key={id} type="button" aria-label={`View source ${id}`} onClick={event => openCitation(id, event.currentTarget)} className="text-primary underline px-0.5">[{id}]</button>)}
                  </p>)}
                </div>
              ) : renderText(message.text)}
            </div>
          )
        )}

        {hasContext && (
          <div className="mt-2 pt-2 border-t border-border/30">
            <button
              aria-expanded={sourcesOpen(showSources)}
              onClick={event => {evidenceTrigger.current = event.currentTarget;setItemId(undefined);setFocusedRecord(undefined);presentation?.setSheetOwner(visualId);setShowSources(!sourcesOpen(showSources));}}
              className="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              <Database className="w-3.5 h-3.5" />
              <span>{message.answer_results?.length ? `View all ${message.citations?.length ?? 0} sources` : message.citations?.length ? 'View all results and sources' : 'Evidence / Retrieved Knowledge'}</span>
              {showSources ? <ChevronUp className="w-3.5 h-3.5" /> : <ChevronDown className="w-3.5 h-3.5" />}
            </button>
            
            {sourcesOpen(showSources) && <EvidenceSheet returnFocus={evidenceTrigger.current} key={itemId ?? 'all'} message={message} itemId={itemId} focusedRecord={focusedRecord} focusRequest={focusRequest} onNextPage={onNextPage} onClose={() => {setShowSources(false);presentation?.setSheetOwner(null);}} />}

          </div>
        )}
        {message.pagination?.has_more && onNextPage && <button type="button" className="text-primary underline text-left" onClick={onNextPage}>View next evidence page ({message.pagination.total} total records)</button>}

        {message.status === 'network_error' && (
          <div className="mt-2 text-xs text-destructive font-medium bg-destructive/10 p-2.5 rounded border border-destructive/20 flex flex-col gap-2">
            <span>{message.text || message.error || "A network error occurred."}</span>
            {onRetry && (
              <button 
                onClick={onRetry}
                className="self-start text-[11px] bg-background border border-border px-2 py-1 rounded hover:bg-muted transition-colors text-foreground"
              >
                Retry
              </button>
            )}
          </div>
        )}
      </div>
    </div>
  );
};
