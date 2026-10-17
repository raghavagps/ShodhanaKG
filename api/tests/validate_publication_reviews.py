"""Validate the saved pilot's provenance, not the scientific judgments themselves."""
from collections import Counter
import argparse
import json
from pathlib import Path
from verify_publications import ROOT, sha


def validate(audit_bytes, review):
    audit = json.loads(audit_bytes)
    if review['identity_audit_sha256'] != sha(audit_bytes):
        raise ValueError('Identity audit changed')
    if review['snapshot_sha256'] != audit['snapshot_sha256']:
        raise ValueError('Snapshot changed')
    claims = {c['edge_id']: c for c in audit['claims']}
    seen = set()
    verdicts = {'supported_observation', 'requires_full_text',
                'scope_qualification_required', 'suspected_misattribution'}
    for r in review['reviews']:
        c = claims[r['edge_id']]
        pair = (r['edge_id'], r['publication_key'])
        if pair in seen:
            raise ValueError('Duplicate review')
        seen.add(pair)
        if r['publication_key'] not in c['publication_keys']:
            raise ValueError('Publication is not attached to this edge')
        for field in ('claim_sha256', 'source', 'target', 'edge_type'):
            if r[field] != c[field]:
                raise ValueError('Claim or direction changed: ' + field)
        if r['verdict'] not in verdicts or r['review_scope'] not in {'metadata', 'abstract', 'full_text'}:
            raise ValueError('Unknown review scope/verdict')
        if r['verdict'] == 'supported_observation' and r['review_scope'] == 'metadata':
            raise ValueError('Metadata alone cannot support a claim')
        for field in ('source_url', 'locator', 'rationale_paraphrase', 'limitations'):
            if not r.get(field):
                raise ValueError('Missing review evidence: ' + field)
        if r['reviewer_kind'] != 'AI_assisted' or r['expert_review'] != {'status': 'pending', 'reviewer': None}:
            raise ValueError('This preliminary pilot cannot certify expert approval')
        if r['properties_reviewed'] or r['properties_status'] != 'not_reviewed':
            raise ValueError('This pilot does not review edge properties')
    for item in review['priority_queue']:
        if item['edge_ids'] != audit['publications'][item['publication_key']]['edge_ids']:
            raise ValueError('Priority queue lost exact edge attribution')
    return {'total_edges': len(claims), 'screened_edges': len({r['edge_id'] for r in review['reviews']}),
            'verdicts': dict(Counter(r['verdict'] for r in review['reviews'])),
            'expert_approved': 0, 'properties_reviewed': 0}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--audit', type=Path, default=ROOT / 'publication_verification_v2.json')
    parser.add_argument('--reviews', type=Path, default=ROOT / 'publication_claim_reviews_v2.json')
    parser.add_argument('--snapshot', type=Path, default=ROOT / 'publication_live_snapshot_v2.json')
    args = parser.parse_args()
    audit_bytes = args.audit.read_bytes()
    audit = json.loads(audit_bytes)
    if sha(args.snapshot.read_bytes()) != audit['snapshot_sha256']:
        raise ValueError('Frozen snapshot no longer matches audit')
    review = json.loads(args.reviews.read_bytes())
    print(json.dumps(validate(audit_bytes, review), indent=2))
