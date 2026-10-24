import { describe, it, expect } from 'vitest';
import { referenceUrl } from './referenceUrl';
import type { PublicationReference } from './types';
const ref = (changes: Partial<PublicationReference>): PublicationReference => ({ raw: '76448', identifier: '76448', namespace: 'AYUSH', status: 'verified', resolution_status: 'confirmed', catalog_version: 'test', ...changes });
describe('namespace-specific reference destinations', () => {
  const record = 'https://arp.ayush.gov.in/View_Res_Landing_Url?rp6=9603&rptypeReport=0&rpreportname=0';
  it('uses audited AYUSH row ID, not article ID; supports saved references', () => {
    expect(referenceUrl(ref({ record_url: record }))).toBe(record);
    expect(referenceUrl(ref({ source: `AYUSH 76448(Ay); ${record}` }))).toBe(record);
    expect(referenceUrl(ref({}))).toBeUndefined();
  });
  it.each([record.replace('arp.ayush.gov.in','example.com'), record+'&redirect=evil', record+'#evil', 'javascript:alert(1)'])('rejects untrusted destination %s', record_url => {
    expect(referenceUrl(ref({ record_url }))).toBeUndefined();
  });
  it('does not guess a namespace or use conflicting references', () => {
    expect(referenceUrl(ref({ namespace: null, identifier: '34758243' }))).toBeUndefined();
    expect(referenceUrl(ref({ status: 'conflict', record_url: record }))).toBeUndefined();
    expect(referenceUrl(ref({ status: 'declared', record_url: record }))).toBeUndefined();
  });
  it('uses valid PMID and DOI destinations irrespective of digit length', () => {
    expect(referenceUrl(ref({ namespace: 'PMID', identifier: '123' }))).toBe('https://pubmed.ncbi.nlm.nih.gov/123/');
    expect(referenceUrl(ref({ namespace: 'DOI', identifier: '10.4103/joa.joa_262_22' }))).toBe('https://doi.org/10.4103/joa.joa_262_22');
    expect(referenceUrl(ref({ namespace: 'DOI', identifier: 'abc123' }))).toBeUndefined();
  });
});
