"""Reconcile saved audit artifacts without changing any KG or old audit record."""
import argparse
from collections import Counter, defaultdict
import json
from pathlib import Path
from verify_publications import ROOT, inventory, sha


def edges(snapshot):
    # Parent vertex tables can repeat child records; identify records by RID.
    return {r['@rid']: r for rows in snapshot['tables'].values()
            for r in rows if r.get('@cat') == 'e'}


def reconcile(old, live, mapping, audit):
    before, after = edges(old), edges(live)
    pubs, claims = inventory(live, mapping['mappings'])
    raw = defaultdict(list)
    for rid, edge in after.items():
        raw[str(edge.get('ref') or '').strip()].append(rid)
    normalized = defaultdict(set)
    for c in claims:
        for key in c['publication_keys']:
            normalized[key].add(str(c['properties'].get('ref', '')))
    stats = defaultdict(list)
    for ref in raw:
        stats[ref.rstrip('0').rstrip('.')].append(ref)
    doi_groups = defaultdict(list)
    for key, p in audit['publications'].items():
        doi = p.get('metadata', {}).get('doi')
        if doi:
            doi_groups[doi.lower()].append(key)
    withdrawn = []
    for value, override in mapping['mappings'].items():
        key = override['kind'] + ':' + value
        withdrawn.append({'old_key': 'pubmed_id:' + value, 'new_key': key,
                          'edge_ids': pubs[key]['edge_ids'], 'explorer': override['explorer'],
                          'supersedes': 'Any PMID existence, missing-PMID, or unrelated-PubMed-topic finding for this raw reference',
                          'source_support': 'not_reviewed'})
    correction_claims = [dict(c, reference_check='correct_original_pmid_preserved',
                              correction_review='out_of_scope_by_user')
                         for c in claims if 'pubmed_id:39210691' in c['publication_keys']]
    return {
        'scope': 'Evaluation only. No KG data changes; no automatic expert approval.',
        'live_comparison': {'before_edges': len(before), 'live_edges': len(after),
                            'added_edge_ids': sorted(after.keys() - before.keys()),
                            'removed_edge_ids': sorted(before.keys() - after.keys()),
                            'changed_edge_ids': sorted(k for k in before.keys() & after.keys() if before[k] != after[k])},
        'count_reconciliation': {
            'raw_nonempty_identifiers': len([k for k in raw if k]),
            'normalized_identifiers': len(pubs),
            'identifier_categories': dict(Counter(p['kind'] for p in pubs.values())),
            'category_caveat': 'AYUSH namespaces confirmed by user. Remaining numeric references are PMID candidates, not proven intended PMIDs.',
            'short_numeric_namespace_review_queue': [k for k, p in pubs.items() if p['kind'] == 'pubmed_id' and len(p['value']) <= 6],
            'edges_missing_reference': raw.get('', []),
            'edges_without_classified_identifier': [c['edge_id'] for c in claims if not c['publication_keys']],
            'normalization_collision_groups': {k: sorted(v) for k, v in normalized.items() if len(v) > 1},
            'repeated_reference_occurrences_beyond_first': sum(max(len(v) - 1, 0) for k, v in raw.items() if k),
            'registry_doi_alias_groups': {k: v for k, v in doi_groups.items() if len(v) > 1},
            'study_level_duplicate_detection': 'Incomplete: no master study table/bibliography; cross-registry identities without DOI metadata remain unresolved.',
            'stats_pre_floor_count': len([k for k in stats if k]),
            'stats_floor': 412,
            'stats_display_count': max(412, len([k for k in stats if k])),
            'stats_normalization_damage': [{'raw': x, 'stats_value': k} for k, values in stats.items() for x in values if x != k],
            'stats_normalization_collisions': {k: v for k, v in stats.items() if len(v) > 1},
            'explanation': 'The application raises the computed count to 412 in api/kg-stats.php; src/pages/ModeSelection.tsx also falls back to 412. No two missing studies or normalization merges are evidenced by this graph.'},
        'namespace_corrections': withdrawn,
        'doi_followup': {'doi': '10.4103/joa.joa_262_22', 'edge_ids': pubs['doi:10.4103/joa.joa_262_22']['edge_ids'],
                         'status': 'registry_lookup_issue_publisher_verification_pending',
                         'user_identified_study': 'Journal of Ayurveda comparison of Shodhit and Ashodhit Semecarpus anacardium seeds',
                         'invalid_doi_finding': False},
        'original_study_reference_check': {'original_pmid': '39210691',
                       'status': 'reference_check_only_correction_review_excluded_by_user',
                       'relationship_counts': dict(Counter(c['edge_type'] for c in correction_claims)),
                       'property_counts': dict(Counter(k for c in correction_claims for k in c['properties'] if k != 'ref')),
                       'all_raw_refs_match_original_pmid': all(c['properties']['ref'] == '39210691' for c in correction_claims),
                       'edges_marked_incorrect': [],
                       'limitation': 'No correction-impact judgment requested or made. All original edges retained.',
                       'edge_review_queue': correction_claims},
        'identifier_ledger': [{'key': k, 'raw_values': sorted(normalized[k]), 'edge_ids': p['edge_ids']} for k, p in sorted(pubs.items())]
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    files = {'old_snapshot': ROOT / 'factual_graph_snapshot_expanded.json',
             'live_snapshot': ROOT / 'publication_live_snapshot_v2.json',
             'mapping': ROOT / 'publication_namespace_overrides_v2.json',
             'audit': ROOT / 'publication_verification_v2.json'}
    data = {k: p.read_bytes() for k, p in files.items()}
    values = {k: json.loads(b) for k, b in data.items()}
    if values['audit']['snapshot_sha256'] != sha(data['live_snapshot']):
        raise ValueError('Audit/live snapshot mismatch')
    if values['audit']['namespace_map_sha256'] != sha(data['mapping']):
        raise ValueError('Audit/namespace map mismatch')
    result = reconcile(values['old_snapshot'], values['live_snapshot'], values['mapping'], values['audit'])
    result['input_sha256'] = {k: sha(b) for k, b in data.items()}
    with args.output.open('x', encoding='utf-8') as output:
        json.dump(result, output, indent=2, ensure_ascii=False)
    print(json.dumps(result['count_reconciliation'], indent=2))


if __name__ == '__main__':
    main()
