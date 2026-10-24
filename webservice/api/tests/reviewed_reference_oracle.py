"""Version 3 independent namespace oracle, backed by the reviewed source audit.
Historical v2 generators remain reproducible; no production classifier is imported.
"""
import json
import re
from pathlib import Path
from build_factual_ground_truth import ROOT, DEFINITIONS, PHASES

def identifiers(edge):
    # Namespace oracle reads the reviewed audit, never the runtime catalog/resolver.
    audit_path = Path(__file__).resolve().parents[3] / 'docs/chatbot/audits/identifier_rules_2026_09_24_v1/audit.json'
    if not hasattr(identifiers, 'bindings'):
        identifiers.bindings = {rid: row for row in json.loads(audit_path.read_bytes())['rows'] for rid in row['edge_ids']}
    binding = identifiers.bindings.get(edge.get('@rid') or edge.get('id') or edge.get('edge_rid'))
    if edge.get('ref'):
        result = {'pubmed_id': '', 'doi': '', 'ayush_id': ''}
        if binding and binding['Reference'] == edge['ref']:
            field = {'PMID': 'pubmed_id', 'DOI': 'doi', 'AYUSH': 'ayush_id'}[binding['assessed_type']]
            result[field] = edge['ref'].lower() if field == 'doi' else edge['ref']
        else:
            result['unclassified_reference'] = edge['ref']
        return result
    result = {'pubmed_id': '', 'doi': '', 'ayush_id': ''}
    unknown = []
    for field in ('doi', 'DOI', 'pubmedId', 'pubmed_id', 'Pubmed ID', 'ref'):
        value = str(edge.get(field) or '').strip()
        if not value:
            continue
        doi = re.sub(r'^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)', '', value, flags=re.I)
        if re.fullmatch(r'10\.[0-9]{4,9}/\S+', doi) or field in ('doi', 'DOI'):
            result['doi'] = result['doi'] or doi.lower()
            continue
        pmid = re.sub(r'^(?:https?://pubmed\.ncbi\.nlm\.nih\.gov/|PMID:\s*)', '', value, flags=re.I).rstrip('/')
        if re.fullmatch(r'[0-9]+', pmid):
            result['pubmed_id'] = result['pubmed_id'] or pmid
        elif value not in unknown:
            unknown.append(value)
    result['ayush_id'] = next((str(edge[a]) for a in ('ayush_id', 'Ayush_ID', 'ayushId') if edge.get(a)), '')
    if unknown:
        result['unclassified_reference'] = '; '.join(unknown)
    return result


def shared_reference(left, right):
    a, b = identifiers(left), identifiers(right)
    for key in ('pubmed_id', 'doi', 'ayush_id'):
        if a[key] and b[key]:
            return {'type': key, 'value': a[key]} if a[key] == b[key] else None
    return None


def reference_paths(tables, definition):
    kind, name, steps = definition
    nodes = {r['@rid']: r for rows in tables.values() for r in rows if r.get('@cat') == 'v'}
    seeds = [n for n in nodes.values() if n['@type'] == kind and n.get('name') == name]
    if len(seeds) != 1:
        raise ValueError(f'Expected one exact seed: {kind}/{name}')
    paths = [([seeds[0]], [])]
    for edge_types, side, target_types in steps:
        joined = []
        for vertices, edges in paths:
            for edge_type in edge_types:
                for edge in tables[edge_type]:
                    if edge['@type'] != edge_type or edge[side] != vertices[-1]['@rid']:
                        continue
                    target = nodes.get(edge['@in' if side == '@out' else '@out'])
                    if target and target['@type'] in target_types:
                        joined.append((vertices + [target], edges + [edge]))
        paths = joined
    accepted, rejected = {}, {}
    for vertices, edges in paths:
        key = tuple(e['@rid'] for e in edges)
        row = {'nodes': [{'id': n['@rid'], 'type': n['@type'], 'label': n['name']} for n in vertices],
               'edges': [{'id': e['@rid'], 'type': e['@type'], 'source': e['@out'], 'target': e['@in'],
                          'properties': {k: v for k, v in e.items() if not k.startswith('@')},
                          'evidence': identifiers(e)} for e in edges],
               'related_entity': vertices[-1]['name'], 'related_type': vertices[-1]['@type']}
        if len(edges) == 2:
            match = shared_reference(*edges)
            if match is None:
                rejected[key] = row
                continue
            row['matched_evidence'] = match
        for edge in edges:
            if edge['@type'] in PHASES:
                row['phase'] = edge['@type']
        accepted[key] = row
    return [accepted[k] for k in sorted(accepted)], [rejected[k] for k in sorted(rejected)]

