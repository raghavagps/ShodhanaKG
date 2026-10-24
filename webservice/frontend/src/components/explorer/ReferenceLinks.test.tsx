import { render, screen, cleanup } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { ReferenceLinks } from './ReferenceLinks';
import type { PublicationReference } from '@/lib/types';

afterEach(cleanup);
const reference = (namespace: PublicationReference['namespace'], identifier: string): PublicationReference => ({
  namespace, identifier, raw: identifier, status: namespace ? 'verified' : 'unclassified',
  resolution_status: 'confirmed', catalog_version: 'test',
});
describe('reference namespaces', () => {
  it('distinguishes identical short identifiers by namespace', () => {
    render(<ReferenceLinks references={[reference('PMID', '76448'), { ...reference('AYUSH', '76448'), record_url: 'https://arp.ayush.gov.in/View_Res_Landing_Url?rp6=9603&rptypeReport=0&rpreportname=0' }]} />);
    expect(screen.getByRole('link', { name: 'Open ID: PMID 76448' })).toHaveAttribute('href', 'https://pubmed.ncbi.nlm.nih.gov/76448/');
    expect(screen.getByRole('link', { name: 'Open ID: AYUSH ID 76448' })).toHaveAttribute('href', 'https://arp.ayush.gov.in/View_Res_Landing_Url?rp6=9603&rptypeReport=0&rpreportname=0');
  });
  it('does not guess a registry for old or untyped data', () => {
    render(<ReferenceLinks raw="12345678" />);
    expect(screen.queryByRole('link')).toBeNull();
    expect(screen.getByText('Reference: 12345678')).toBeInTheDocument();
    expect(screen.queryByText(/unclassified/)).toBeNull();
  });
  it('preserves DOI classification when resolution is unresolved', () => {
    render(<ReferenceLinks references={[{ ...reference('DOI', '10.4103/joa.joa_262_22'), resolution_status: 'unresolved' }]} />);
    expect(screen.getByRole('link', { name: /DOI/ })).toBeInTheDocument();
    expect(screen.queryByText(/lookup unresolved/)).toBeNull();
  });
});
