import type { PublicationReference } from './types';

/** Namespace is part of identity: AYUSH 76448 and PMID 76448 are different. */
export function referenceKey(provenance: { references?: PublicationReference[]; reference?: string; pubmedId?: string }): string {
  const ref = provenance.references?.find(r => r.namespace && ['verified', 'declared'].includes(r.status));
  return ref ? `${ref.namespace}:${ref.identifier}` : `Unclassified:${provenance.reference ?? provenance.pubmedId ?? ''}`;
}
