import { referencePresentation } from '@/lib/referencePresentation';
import { ReferenceLinks } from "@/components/explorer/ReferenceLinks";
import { useEffect, useRef, useState, useMemo } from 'react';
import { AnswerCitation } from '@/lib/types';

const isRecord = (value: unknown): value is Record<string, unknown> =>
  value !== null && typeof value === 'object' && !Array.isArray(value);
const label = (value: string) => value.replace(/_/g, ' ');
const meaningful = (value: unknown): boolean => {
  if (value == null) return false;
  if (typeof value === 'string') return value.trim() !== '' && !/^(null|not supplied)$/i.test(value.trim());
  if (Array.isArray(value)) return value.some(meaningful);
  if (isRecord(value)) return Object.values(value).some(meaningful);
  return true;
};

// Generic display rules, not question-specific fields. Preserve zero and false.
const publicScalar = (key: string, value: unknown) => meaningful(value)
  && ['string', 'number', 'boolean'].includes(typeof value)
  && !key.startsWith('@') && !key.startsWith('_')
  && !/^(id|rid|.*[_ ](?:id|ids|rid|rids)|source|target|ref|references|pubmedid|ayushid|doi|statement|attribution|related_entity|related_type|edge_type|type)$/i.test(key);
const fieldLabel = (key: string) => label(key.replace(/([a-z])([A-Z])/g, '$1 $2')).replace(/^./, c => c.toUpperCase());
function PropertyLines({ properties, displayProperties = [] }: { properties: Record<string, unknown>; displayProperties?: AnswerCitation['scopes'][number]['display_properties'] }) {
  const entries = new Map(Object.entries(properties));
  for (const property of displayProperties) entries.set(property.key, property.value);
  const seen = new Set<string>();
  return <>{Array.from(entries).filter(([key, value]) => {
    const canonical = key.replace(/[ _]/g, '').toLowerCase();
    if (!publicScalar(key, value) || canonical === 'procedure' || seen.has(canonical)) return false;
    seen.add(canonical); return true;
  }).map(([key, value]) => <p key={key}><strong>{displayProperties.find(p => p.key === key)?.label || fieldLabel(key)}:</strong> {String(value)}</p>)}</>;
}

const scopeVisible = (scope: AnswerCitation['scopes'][number], namespace?: 'PMID' | 'AYUSH') => {
  if (!namespace) return true;
  const refs = scope.identifiers.references;
  return refs?.length ? refs.some(ref => ref.namespace === namespace && ['verified','declared'].includes(ref.status)) : namespace === 'PMID' ? !!scope.identifiers.pubmed_id : !!scope.identifiers.ayush_id;
};

/** Preserve nested evidence scopes rather than flattening references across records. */
function Fields({ value: rawValue }: { value: unknown }) {
  const value = referencePresentation(rawValue);
  if (!meaningful(value)) return null;
  if (Array.isArray(value)) return <ol className="space-y-1 pl-3 list-decimal">{value.filter(meaningful).map((item, i) => <li key={i}><Fields value={item} /></li>)}</ol>;
  if (isRecord(value)) return (
    <dl className="space-y-1">
      {Object.entries(value).filter(([key, item]) => meaningful(item) && !key.startsWith('@') && !/^(id|rid|edge_id|source_id|target_id|fact_ids|record_indices|raw|raw_json|statement)$/i.test(key)).map(([key, item]) => (
        <div key={key} className="kg-source-fields">
          <dt className="inline font-semibold">{label(key)}</dt>{' '}
          <dd className="inline break-words whitespace-pre-wrap"><Fields value={item} /></dd>
        </div>
      ))}
    </dl>
  );
  return <>{String(value)}</>;
}

function EvidenceRecord({ record }: { record: Record<string, unknown> }) {
  const nodes = Array.isArray(record.nodes) ? record.nodes.filter(isRecord) : [];
  const edges = Array.isArray(record.edges) ? record.edges.filter(isRecord) : [];
  if (!nodes.length || !edges.length) return <Fields value={referencePresentation(record)} />;
  const nodeName = (id: unknown) => {
    const node = nodes.find(node => node.id === id);
    return node ? `${String(node.label)} (${label(String(node.type))})` : 'Unknown node';
  };
  return (
    <div className="space-y-3">
      {edges.map((edge, i) => (
        <div key={i} className="rounded border border-border p-2 space-y-2">
          <p className="font-medium break-words">{nodeName(edge.source)} → {label(String(edge.type)).toLowerCase()} → {nodeName(edge.target)}</p>
          <div><p className="font-medium">Edge evidence</p><Fields value={edge.evidence} /></div>
          <div><p className="font-medium">Recorded properties</p><Fields value={edge.properties} /></div>
        </div>
      ))}
      {record.matched_evidence !== undefined && <div><p className="font-medium">Shared reference within this path</p><Fields value={record.matched_evidence} /></div>}
      {record.phase !== undefined && <div><p className="font-medium">Treatment phase</p><Fields value={record.phase} /></div>}
    </div>
  );
}

/** Presentation only: full records and citation scopes remain in the message data. */
function DescriptiveEvidence(props: { citation: AnswerCitation; record: Record<string, unknown>; namespaceFilter?: 'PMID' | 'AYUSH' }) {
  return <CompactEvidence {...props} />;
}

function CompactEvidence({ citation, record, namespaceFilter }: { citation: AnswerCitation; record: Record<string, unknown>; namespaceFilter?: 'PMID' | 'AYUSH' }) {
  return <div className="kg-source-scopes">
    {citation.scopes.filter(scope => scopeVisible(scope, namespaceFilter)).map((scope, i) => {
      const procedure = scope.display_properties?.find(property => /^procedure$/i.test(property.key))?.value ?? Object.entries(scope.properties).find(([key]) => /^procedure$/i.test(key))?.[1];
      const relationship = scope.relationship || citation.relationships?.join('; ');
      // Only read an edge's properties when its stable ID matches this scope.
      const edge = Array.isArray(record.edges) ? record.edges.filter(isRecord).find(e => e.id === scope.edge_id && scope.edge_id) : undefined;
      const properties = { ...(citation.scopes.length === 1 && !Array.isArray(record.edges) ? record : {}), ...(edge && isRecord(edge.properties) ? edge.properties : {}), ...scope.properties };
      // Historical saved answers contain string-only citation.entities. Do not
      // invent their types or dereference them as current typed entity objects.
      // Their actual named record fields remain available in PropertyLines.
      const entities = (scope.entities ?? (edge && Array.isArray(record.nodes) ? record.nodes.filter(isRecord).filter(n => n.id === edge.source || n.id === edge.target).map(n => ({type: String(n.type ?? ''), label: String(n.label ?? '')})) : citation.entities ?? []))
        .filter(entity => isRecord(entity) && typeof entity.type === 'string' && typeof entity.label === 'string');

      return <div key={scope.edge_id ?? i} className="kg-source-scope">
        <div className="kg-source-top">
          <span className="kg-source-number">[{citation.id}]</span>
          <div className="kg-source-identifiers">
            {scope.identifiers.references?.length ? scope.identifiers.references.map((reference, index) =>
              <div key={index} data-namespace={reference.namespace} className="kg-source-reference">
                <ReferenceLinks references={[reference]} />
              </div>
            ) : <ReferenceLinks raw={scope.identifiers.unclassified_reference || scope.identifiers.pubmed_id || scope.identifiers.doi || scope.identifiers.ayush_id} />}
          </div>
        </div>
        <div className="kg-source-fields">
          {entities.filter(entity => meaningful(entity.type) && meaningful(entity.label)).map((entity, index) =>
            <p key={index}><strong>{label(entity.type)}:</strong> {entity.label}</p>
          )}
          {meaningful(relationship) && <p><strong>Relationship:</strong> {relationship}</p>}
          <PropertyLines properties={Object.fromEntries(Object.entries(properties).filter(([key, value]) => !entities.some(e => e.type.replace(/[ _]/g, '').toLowerCase() === key.replace(/[ _]/g, '').toLowerCase() && e.label === value)))} displayProperties={scope.display_properties} />
          {meaningful(scope.treatment_phase) && <p><strong>Treatment phase:</strong> {scope.treatment_phase}</p>}
        </div>
        {meaningful(procedure) && <div className="kg-source-procedure"><span>PROCEDURE:</span><div><Fields value={procedure} /></div></div>}
      </div>;
    })}
  </div>;
}

export function EvidenceRecords({ records, citations, focusedRecord, focusRequest, visibleRecordIndices, namespaceFilter }: { records: Record<string, unknown>[]; citations?: AnswerCitation[]; focusedRecord?: number; focusRequest?: number; visibleRecordIndices?: number[]; namespaceFilter?: 'PMID' | 'AYUSH' }) {
  const indices = useMemo(() => visibleRecordIndices ?? records.map((_, index) => index), [visibleRecordIndices, records]);
  const [start, setStart] = useState(0);
  const section = useRef<HTMLElement>(null);
  useEffect(() => {
    if (focusedRecord !== undefined && indices.includes(focusedRecord)) setStart(indices.indexOf(focusedRecord));
  }, [focusedRecord, focusRequest, indices]);
  useEffect(() => {
    if (focusedRecord === undefined || indices[start] !== focusedRecord) return;
    const card = section.current?.querySelector<HTMLElement>(`[data-record-index="${focusedRecord}"]`);
    card?.focus();
    card?.scrollIntoView?.({ block: 'nearest' });
  }, [start, focusedRecord, focusRequest, indices]);
  const count = indices.length;
  const first = Math.min(start, Math.max(0, count - 1));
  const end = Math.min(first + 10, count);
  if (!count) return null;
  return (
    <section ref={section} aria-label="Retrieved evidence" className="min-w-0 space-y-3">
      <label className="flex flex-wrap gap-2 items-center">
        {citations?.length ? 'Jump to source' : 'Start at record'}
        <input aria-label="Start at evidence record" type="number" min={1} max={count} value={first + 1}
          className="w-20 rounded border border-border bg-background px-2 py-1"
          onChange={event => {
            const value = Number(event.target.value);
            if (Number.isInteger(value) && value >= 1 && value <= count) setStart(value - 1);
          }} />
      </label>
      <p aria-live="polite">Showing {citations?.length ? 'sources' : 'records'} {first + 1}–{end} of {count}</p>
      {indices.slice(first, end).map((recordIndex) => {
        const record = records[recordIndex];
        const citation = citations?.find(c => c.record_indices.includes(recordIndex));
        return (
        <article key={recordIndex} data-record-index={recordIndex} tabIndex={-1} aria-label={`Evidence record ${recordIndex + 1}`} className="border-l-2 border-primary/40 pl-2 py-1 min-w-0">
          {!citations?.length && <h4 className="font-medium mb-2">Evidence record {recordIndex + 1}</h4>}
          {citation ? (
            citation.relationships !== undefined || citation.scopes.some(scope => scope.relationship)
              ? <CompactEvidence citation={citation} record={record} namespaceFilter={namespaceFilter} />
              : <DescriptiveEvidence citation={citation} record={record} namespaceFilter={namespaceFilter} />
          ) : <EvidenceRecord record={record} />}
        </article>
      ); })}
      <div className="flex gap-3">
        <button type="button" disabled={first === 0} onClick={() => setStart(Math.max(0, first - 10))} className="underline disabled:opacity-40">Previous {citations?.length ? 'sources' : 'records'}</button>
        <button type="button" disabled={end === count} onClick={() => setStart(end)} className="underline disabled:opacity-40">Next {citations?.length ? 'sources' : 'records'}</button>
      </div>
    </section>
  );
}
