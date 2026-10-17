"""Read-only publication identity audit. Existence never implies scientific support."""
import argparse
from collections import Counter
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from build_factual_ground_truth import identifiers

ROOT = Path(__file__).parent / 'fixtures'


def sha(data):
    return hashlib.sha256(data).hexdigest()


def fetch(url):
    last = None
    for attempt in range(3):
        try:
            request = urllib.request.Request(url, headers={'User-Agent': 'ShodhanaKG-publication-audit/1.0'})
            with urllib.request.urlopen(request, timeout=25) as response:
                return response.status, response.read()
        except urllib.error.HTTPError as error:
            if error.code == 404:
                return 404, error.read()
            last = str(error)
        except (OSError, ValueError) as error:
            last = str(error)
        time.sleep(0.5 * (attempt + 1))
    raise RuntimeError(last)


def parse_pubmed(body):
    root = ET.fromstring(body)
    records = {}
    for article in root.findall('PubmedArticle'):
        citation = article.find('MedlineCitation')
        pmid = citation.findtext('PMID')
        # Do not pick up IDs of papers nested in the article's bibliography.
        ids = {i.get('IdType'): i.text for i in article.findall('PubmedData/ArticleIdList/ArticleId')}
        title = ''.join(citation.find('Article/ArticleTitle').itertext())
        abstract = ' '.join(''.join(a.itertext()) for a in citation.findall('Article/Abstract/AbstractText'))
        notices = [{'relation': c.get('RefType'), 'pmid': c.findtext('PMID')}
                   for c in citation.findall('CommentsCorrectionsList/CommentsCorrections')]
        records[pmid] = {'pmid': pmid, 'title': title,
                        'journal': citation.findtext('Article/Journal/Title'),
                        'publication_types': [t.text for t in citation.findall('Article/PublicationTypeList/PublicationType')],
                        'doi': (ids.get('doi') or '').lower(), 'pmcid': ids.get('pmc'),
                        'notices': notices, 'abstract_available': bool(abstract),
                        'abstract_sha256': sha(abstract.encode()) if abstract else None,
                        'url': 'https://pubmed.ncbi.nlm.nih.gov/' + pmid + '/'}
    for article in root.findall('PubmedBookArticle'):
        doc = article.find('BookDocument')
        pmid = doc.findtext('PMID')
        title_node = doc.find('ArticleTitle')
        records[pmid] = {'pmid': pmid, 'title': ''.join(title_node.itertext()) if title_node is not None else '',
                        'publication_types': ['Book document'], 'doi': '', 'pmcid': None,
                        'notices': [], 'url': 'https://pubmed.ncbi.nlm.nih.gov/' + pmid + '/'}
    return records


def inventory(snapshot, namespace_map=None):
    nodes = {r['@rid']: r for rows in snapshot['tables'].values() for r in rows if r.get('@cat') == 'v'}
    publications = {}
    claims = []
    for rows in snapshot['tables'].values():
        for edge in rows:
            if edge.get('@cat') != 'e':
                continue
            refs = []
            classified = identifiers(edge)
            override = (namespace_map or {}).get(str(edge.get('ref', '')).strip())
            if override:
                raw = str(edge['ref']).strip()
                if override['kind'] != 'ayush_id' or classified['pubmed_id'] != raw:
                    raise ValueError('Conflicting namespace override: ' + raw)
                classified['pubmed_id'] = ''
                classified['ayush_id'] = raw
            for kind, value in classified.items():
                if not value:
                    continue
                key = kind + ':' + value
                refs.append(key)
                publications.setdefault(key, {'kind': kind, 'value': value, 'edge_ids': []})['edge_ids'].append(edge['@rid'])
            props = {k: v for k, v in edge.items() if not k.startswith('@')}
            claims.append({'edge_id': edge['@rid'], 'edge_type': edge['@type'],
                           'source': {k: nodes[edge['@out']][v] for k, v in [('id', '@rid'), ('type', '@type'), ('label', 'name')]},
                           'target': {k: nodes[edge['@in']][v] for k, v in [('id', '@rid'), ('type', '@type'), ('label', 'name')]},
                           'properties': props, 'publication_keys': refs,
                           'claim_sha256': sha(json.dumps(edge, sort_keys=True).encode()),
                           'source_support': 'not_reviewed', 'expert_review': {'status': 'pending', 'reviewer': None}})
    return publications, claims


def summary(report):
    return {'publications': len(report['publications']), 'edges': len(report['claims']),
            'identity_statuses': dict(Counter(p['identity_status'] for p in report['publications'].values())),
            'edges_with_unverified_identifiers': sum(any(report['publications'][k]['identity_status'] != 'verified' for k in c['publication_keys']) for c in report['claims']),
            'source_support_reviewed': 0, 'expert_reviewed': 0}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--snapshot', type=Path, default=ROOT / 'factual_graph_snapshot_expanded.json')
    parser.add_argument('--namespace-map', type=Path, default=ROOT / 'publication_namespace_overrides_v2.json')
    args = parser.parse_args()
    snapshot_file = args.snapshot
    snapshot_bytes = snapshot_file.read_bytes()
    mapping_bytes = args.namespace_map.read_bytes()
    mapping = json.loads(mapping_bytes)
    pubs, claims = inventory(json.loads(snapshot_bytes), mapping['mappings'])
    for p in pubs.values():
        p['identity_status'] = 'pending'
        p['namespace_basis'] = 'Unclassified raw reference interpreted by syntax; intended namespace not independently confirmed'
        if p['kind'] == 'ayush_id':
            p['identity_status'] = 'namespace_confirmed_source_pending'
            p['namespace_basis'] = mapping['authority']
            p['explorer'] = mapping['mappings'][p['value']]['explorer']
        elif p['kind'] == 'doi':
            p['namespace_basis'] = 'DOI syntax in raw reference'
    report = {'created_at': datetime.now(timezone.utc).isoformat(), 'snapshot_sha256': sha(snapshot_bytes),
              'namespace_map_sha256': sha(mapping_bytes), 'namespace_map': mapping,
              'verifier_sha256': sha(Path(__file__).read_bytes()), 'scope': 'Publication identity only; all source/clinical support pending',
              'publications': pubs, 'claims': claims, 'requests': []}
    with args.output.open('x', encoding='utf-8') as output:
        def save():
            report['summary'] = summary(report)
            output.seek(0); json.dump(report, output, indent=2, ensure_ascii=False); output.truncate(); output.flush()
        save()
        pmids = sorted(p['value'] for p in pubs.values() if p['kind'] == 'pubmed_id')
        for start in range(0, len(pmids), 50):
            batch = pmids[start:start + 50]
            url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?' + urllib.parse.urlencode({'db': 'pubmed', 'id': ','.join(batch), 'retmode': 'xml'})
            request_record = {'url': url, 'checked_at': datetime.now(timezone.utc).isoformat()}
            try:
                status, body = fetch(url)
                request_record.update({'http_status': status, 'body_sha256': sha(body)})
                if status != 200:
                    raise ValueError('Registry request was not successful')
                records = parse_pubmed(body)
                for pmid in batch:
                    p = pubs['pubmed_id:' + pmid]
                    if pmid in records:
                        p.update({'identity_status': 'verified', 'metadata': records[pmid], 'verification_url': url})
                    else:
                        # An omitted batch result is not proof of an invalid identifier.
                        p.update({'identity_status': 'not_returned_pending_confirmation', 'verification_url': url})
            except Exception as error:
                request_record['error'] = str(error)
                for pmid in batch:
                    pubs['pubmed_id:' + pmid]['identity_status'] = 'lookup_error'
            report['requests'].append(request_record); save()
            print('PubMed', min(start + 50, len(pmids)), '/', len(pmids), flush=True)
            time.sleep(0.4)
        for key, p in pubs.items():
            if p['kind'] != 'doi':
                continue
            url = 'https://api.crossref.org/works/' + urllib.parse.quote(p['value'], safe='')
            request_record = {'url': url, 'checked_at': datetime.now(timezone.utc).isoformat()}
            try:
                status, body = fetch(url)
                request_record.update({'http_status': status, 'body_sha256': sha(body)})
                if status == 200:
                    metadata = json.loads(body)['message']
                    if metadata.get('DOI', '').lower() != p['value'].lower():
                        raise ValueError('Registry returned a different DOI')
                    p.update({'identity_status': 'verified', 'metadata': {'doi': metadata['DOI'], 'title': metadata.get('title'),
                              'publisher': metadata.get('publisher'), 'type': metadata.get('type'), 'url': metadata.get('URL'),
                              'updates': metadata.get('update-to', []), 'relation': metadata.get('relation', {})}, 'verification_url': url})
                else:
                    p.update({'identity_status': 'registry_lookup_unresolved', 'verification_url': url})
            except Exception as error:
                request_record['error'] = str(error)
                p['identity_status'] = 'lookup_error'
            report['requests'].append(request_record); save()
            print('DOI', p['value'], p['identity_status'], flush=True)
            time.sleep(0.4)
        print(json.dumps(report['summary']))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
