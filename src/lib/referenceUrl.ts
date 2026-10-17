import type { PublicationReference } from './types';

/** Namespace is established by the classifier, never inferred from ID length. */
export function referenceUrl(ref: PublicationReference): string | undefined {
  if (!['verified', 'declared'].includes(ref.status)) return;
  if (ref.namespace === 'PMID' && /^\d+$/.test(ref.identifier)) return `https://pubmed.ncbi.nlm.nih.gov/${ref.identifier}/`;
  if (ref.namespace === 'DOI' && /^10\.\d{4,9}\/\S+$/.test(ref.identifier)) return `https://doi.org/${ref.identifier.split('/').map(encodeURIComponent).join('/')}`;
  if (ref.namespace !== 'AYUSH' || ref.status !== 'verified') return;
  // Supports previously saved classified references as well as the additive DTO.
  const candidate = ref.record_url ?? ref.source?.match(/https:\/\/arp\.ayush\.gov\.in\/View_Res_Landing_Url\?rp6=\d+&rptypeReport=0&rpreportname=0(?=\s|$)/)?.[0];
  if (!candidate) return;
  try {
    const url = new URL(candidate);
    if (url.origin !== 'https://arp.ayush.gov.in' || url.pathname !== '/View_Res_Landing_Url' || url.hash || url.username || url.password) return;
    if (!/^\d+$/.test(url.searchParams.get('rp6') ?? '') || url.searchParams.get('rptypeReport') !== '0' || url.searchParams.get('rpreportname') !== '0' || [...url.searchParams].length !== 3) return;
    return url.href;
  } catch { return; }
}
