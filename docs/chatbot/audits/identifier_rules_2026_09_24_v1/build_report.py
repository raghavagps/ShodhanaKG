"""Report registry-backed classifications; proposed rules never supply verification."""
from collections import Counter
import csv
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import re

ROOT = Path(__file__).parent
FIX = ROOT.parents[3] / 'api/tests/fixtures'


def load(path):
    return json.loads(path.read_bytes())


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main():
    stored = load(ROOT / 'stored_references.json')
    ayush = load(ROOT / 'ayush_registry.json')
    v1 = load(FIX / 'publication_verification_v1.json')
    v2 = load(FIX / 'publication_verification_v2.json')
    doi_source = load(ROOT / 'doi_source_evidence.json')
    if ayush['snapshot_sha256'] != sha(ROOT / 'stored_references.json'):
        raise ValueError('Registry lookup/snapshot mismatch')
    by_ayush = {r['reference']: r for r in ayush['results']}
    expected_numeric = {str(r['raw']) for r in stored['references'] if str(r['raw']).isdigit()}
    assert len(by_ayush) == len(ayush['results']) and set(by_ayush) == expected_numeric
    assert all(r['status'] != 'lookup_error' for r in ayush['results'])
    # Verify this full-schema scan has the same references and edge IDs as the
    # earlier read-only capture used for the cached NCBI/Crossref requests.
    previous = load(FIX / 'publication_live_snapshot_v2.json')
    prior_refs = {r['@rid']: str(r.get('ref', '')) for rows in previous['tables'].values() for r in rows if r.get('@cat') == 'e'}
    current_refs = {rid: str(r['raw']) for r in stored['references'] for rid in r['ids']}
    assert current_refs == prior_refs
    results = []
    for entry in sorted(stored['references'], key=lambda r: str(r['raw'])):
        ref = str(entry['raw'])
        numeric = bool(re.fullmatch(r'[0-9]+', ref))
        doi_shape = bool(re.fullmatch(r'10\.[0-9]{4,9}/\S+', ref))
        # Operational interpretation of user's 'alphanumeric': nonnumeric values
        # containing letters, allowing punctuation. Strict isalnum would miss DOIs.
        proposed = ('PMID' if len(ref) >= 8 else 'AYUSH') if numeric else ('DOI' if re.search('[A-Za-z]', ref) else 'Unclassified')
        current = '/'.join(label for k, label in [('pubmed_id', 'PMID'), ('doi', 'DOI'), ('ayush_id', 'AYUSH')]
                           if entry['current_identifiers'].get(k)) or 'Unclassified'
        assigned, verified, evidence, conflict = 'Unclassified/ambiguous', 'No', '', []
        details = {}
        pm = v2['publications'].get('pubmed_id:' + ref, v1['publications'].get('pubmed_id:' + ref, {}))
        pubmed_record = pm.get('identity_status') == 'verified' and pm.get('metadata', {}).get('pmid') == ref
        ay = by_ayush.get(ref, {})
        if len(ay.get('matches', [])) == 1:
            match = ay['matches'][0]
            assigned = 'AYUSH'
            verified = 'Yes: exact AYUSH Art ID; title/context screened for namespace only'
            evidence = f"AYUSH {match['artid']} / {match['arp_id']}; {match['title']}; {match['url']}"
            details['ayush'] = match
            if pubmed_record:
                conflict.append('Same number also resolves in PubMed; raw numeric value alone is ambiguous')
                details['colliding_pubmed_record'] = pm['metadata']
        elif numeric and pubmed_record and not ay.get('matches'):
            assigned = 'PMID'
            verified = 'Yes: exact NCBI PMID record; no exact AYUSH Art ID returned'
            meta = pm['metadata']
            evidence = f"NCBI EFetch: {meta['title']}; {meta['url']}; cached request {pm['verification_url']}"
            details['pubmed'] = meta
        elif doi_shape:
            record = v2['publications'].get('doi:' + ref.lower(), {})
            if record.get('identity_status') == 'verified' and record.get('metadata', {}).get('doi', '').lower() == ref.lower():
                assigned = 'DOI'
                verified = 'Yes: exact Crossref DOI metadata'
                meta = record['metadata']
                evidence = f"Crossref: {meta['title']}; {record['verification_url']}"
                details['doi'] = meta
            else:
                matches = [m for m in doi_source['matches'] if m['doi'].lower() == ref.lower()]
                if len(matches) == 1:
                    assigned = 'DOI'
                    verified = 'Yes: DOI explicitly recorded by official AYUSH index; resolver registration remains unresolved'
                    evidence = f"AYUSH {matches[0]['artid']} / {matches[0]['arp_id']}; explicit DOI field; {matches[0]['url']}"
                    details['doi_source'] = matches[0]
                    conflict.append('Crossref/doi.org previously returned 404; publisher 403. Namespace supported; not an invalid-DOI finding')
        if current != assigned:
            conflict.insert(0, f'Current classifier says {current}; source-backed assignment is {assigned}')
        exception = assigned != 'Unclassified/ambiguous' and assigned != proposed
        if exception:
            conflict.append('Confirmed exception to proposed rule')
        seeds = sorted({c['source'] for c in entry['contexts'] if c['type'] in {'UNDERGOES', 'TREATED_BY', 'PRE_TREATED_BY', 'POST_TREATED_BY', 'HAS_SYMPTOM'} and c['source']})
        results.append({'Reference': ref, 'Current type': current, 'Proposed type': proposed,
                        'Independently verified?': verified, 'Source/evidence': evidence,
                        'Conflict': '; '.join(conflict) or 'None identified in namespace audit',
                        'assessed_type': assigned, 'proposed_rule_exception': exception,
                        'doi_format_checked': doi_shape, 'edge_ids': entry['ids'],
                        'kg_subjects_for_namespace_context': seeds, 'registry_metadata': details})
    fields = ['Reference', 'Current type', 'Proposed type', 'Independently verified?', 'Source/evidence', 'Conflict']
    with (ROOT / 'reference_classification.csv').open('x', encoding='utf-8-sig', newline='') as f:
        writer = csv.DictWriter(f, fieldnames=fields, extrasaction='ignore'); writer.writeheader(); writer.writerows(results)
    with (ROOT / 'reference_classification.md').open('x', encoding='utf-8') as f:
        f.write('# Complete reference classification table\n\nSee [audit report](./REPORT.md) for definitions and limits. Verification means namespace evidence, not scientific support.\n\n')
        f.write('| ' + ' | '.join(fields) + ' |\n|' + '|'.join(['---'] * len(fields)) + '|\n')
        for row in results:
            f.write('| ' + ' | '.join(str(row[k]).replace('|', '\\|').replace('\n', ' ') for k in fields) + ' |\n')
    summary = {'created_at': datetime.now(timezone.utc).isoformat(), 'distinct_references': len(results),
               'unique_edges': stored['unique_edges'], 'missing_edge_refs': len(stored['missing_edge_refs']),
               'current_counts': dict(Counter(r['Current type'] for r in results)),
               'proposed_counts': dict(Counter(r['Proposed type'] for r in results)),
               'source_backed_counts': dict(Counter(r['assessed_type'] for r in results)),
               'unclassified_or_ambiguous': sum(r['assessed_type'] == 'Unclassified/ambiguous' for r in results),
               'confirmed_exceptions_to_proposed_rules_in_this_snapshot': sum(r['proposed_rule_exception'] for r in results),
               'current_classifier_conflicts': sum(r['Current type'] != r['assessed_type'] for r in results),
               'cross_registry_numeric_collisions': sum('colliding_pubmed_record' in r['registry_metadata'] for r in results),
               'doi_registration_lookup_unresolved': sum('doi_source' in r['registry_metadata'] for r in results),
               'safe_as_universal_rules': False,
               'scope': 'Registry-backed identifier namespace assessment with AI-assisted title/context screening. Not scientific validation or exhaustive bibliographic deduplication.',
               'cached_registry_evidence_date': v2['created_at'],
               'inputs_sha256': {str(p): sha(p) for p in [ROOT / 'stored_references.json', ROOT / 'ayush_registry.json', ROOT / 'doi_source_evidence.json', FIX / 'publication_verification_v1.json', FIX / 'publication_verification_v2.json']},
               'rows': results}
    with (ROOT / 'audit.json').open('x', encoding='utf-8') as f:
        json.dump(summary, f, ensure_ascii=False, indent=2)
    print(json.dumps({k: v for k, v in summary.items() if k not in {'rows', 'inputs_sha256'}}, indent=2))


if __name__ == '__main__':
    main()
