"""Publish the reviewed namespace audit as a runtime catalog; no KG writes."""
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
AUDIT = ROOT / 'docs/chatbot/audits/identifier_rules_2026_09_24_v1/audit.json'

def main():
    audit = json.loads(AUDIT.read_bytes())
    entries = []
    for row in audit['rows']:
        raw, namespace = row['Reference'], row['assessed_type']
        entries.append(dict(raw=raw, identifier=raw.lower() if namespace == 'DOI' else raw,
                            namespace=namespace, edge_ids=row['edge_ids'],
                            verified_at=audit['created_at'], source=row['Source/evidence'],
                            aliases={k: v for k, v in row.get('registry_metadata', {}).get('ayush', {}).items() if k in ('artid', 'arp_id')} if namespace == 'AYUSH' else {},
                            resolution_status='unresolved' if raw == '10.4103/joa.joa_262_22' else 'confirmed'))
    target = ROOT / 'api/core/reference_catalog_v1.json'
    with target.open('x', encoding='utf-8') as stream:
        json.dump(dict(version='2026-09-24.v1', audit_sha256=hashlib.sha256(AUDIT.read_bytes()).hexdigest(),
                       scope='Identifier namespace only; not scientific validation.', entries=entries), stream, indent=2)

if __name__ == '__main__':
    main()
