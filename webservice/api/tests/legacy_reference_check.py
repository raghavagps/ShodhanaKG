"""Read-only independent reference checks for historical endpoint scripts.

Uses frozen raw records and reviewed namespaces. Never captures expected answers
from production, rewrites fixtures or imports application traversal code.
"""
import json
import sys
from pathlib import Path
import build_expanded_ground_truth as expanded
import build_legacy_ground_truth as legacy
from reviewed_reference_oracle import identifiers
from evaluate_legacy_evidence import evaluate, contains

ROOT = Path(__file__).parent

def check(endpoint, entity, actual):
    names=('identifiers','properties','legacy_project','shared_reference')
    original={name:getattr(expanded,name) for name in names}
    try:
        return _check(endpoint,entity,actual)
    finally:
        for name,value in original.items(): setattr(expanded,name,value)

def _check(endpoint, entity, actual):
    tables=json.loads((ROOT/'fixtures/factual_graph_snapshot_expanded.json').read_text(encoding='utf8'))['tables']
    records={r['@rid']:r for rows in tables.values() for r in rows}
    audit=json.loads((ROOT.parents[2]/'docs/chatbot/audits/identifier_rules_2026_09_24_v1/audit.json').read_text(encoding='utf8'))
    bindings={r['Reference']:r['edge_ids'][0] for r in audit['rows']}
    namespace_cache={}
    def reviewed(edge):
        # Historical projectors sometimes pass properties without RID. Restore a
        # reviewed binding for this exact raw reference, never use digit length.
        if edge.get('ref') in bindings and not any(edge.get(k) for k in ('@rid','id','edge_rid')):
            edge=dict(edge, id=bindings[edge['ref']])
        key=json.dumps(edge,sort_keys=True)
        if key not in namespace_cache: namespace_cache[key]=identifiers(edge)
        # Historical projectors append fields; never expose a mutable cached dict.
        return dict(namespace_cache[key])
    # Inject the independent, reviewed namespace oracle into historical raw joins.
    # This is test-only composition; historical generators and fixtures stay intact.
    expanded.identifiers=reviewed
    expanded.properties=lambda edge: legacy.properties(edge, reviewed)
    expanded.legacy_project=lambda name,path: legacy.project(name,path,reviewed)
    expanded.shared_reference=lambda a,b: any(reviewed(a)[k] and reviewed(a)[k]==reviewed(b)[k] for k in ('pubmed_id','doi','ayush_id'))
    if endpoint in ('integrated_compound_disease','integrated_disease_compound'):
        from evaluate_system_consistency import Oracle as IntegratedOracle
        from build_system_ground_truth import canonical
        independent=IntegratedOracle(tables)
        source,target=('Compound','Disease') if endpoint=='integrated_compound_disease' else ('Disease','Compound')
        paths=independent.paths(source,entity,target)
        expected=[canonical(independent,*paths[k]) for k in sorted(paths)]
        case={'expected_results':expected,'expected_status':'success' if expected else 'no_data'}
        return evaluate(case,actual,records),len(expected)
    oracle=expanded.Oracle(tables)
    seeds=[r for r in oracle.nodes.values() if r['@type'] in expanded.CONTRACTS[endpoint][0] and r['name']==entity]
    if len(seeds)!=1: raise ValueError('Missing/ambiguous independent seed')
    ref=oracle.reference(endpoint,seeds[0])
    case={'expected_endpoint':endpoint,'expected_entity':entity,'expected_entity_type':seeds[0]['@type'],'expected_results':ref['rows'],
          'expected_status':'success' if ref['rows'] else 'no_data','expected_result_source_paths':ref['audit']}
    if endpoint=='compound_validation': case['expected_safety_candidates']=ref['safety']
    issues=evaluate(case,actual,records)
    if endpoint=='compound_validation':
        for row in actual.get('results',[]):
            match=next((i for i,r in enumerate(ref['rows']) if contains(row,r)),None)
            if match is None: continue
            safety=row.get('safety_evidence')
            candidates=ref['safety'][match]
            if not ((not candidates and safety is None) or any(contains(safety,c) for c in candidates)):
                issues.append('unsupported/missing safety evidence')
    return issues,len(ref['rows'])

if __name__=='__main__':
    request=json.load(sys.stdin)
    issues,count=check(request['endpoint'],request['entity'],request['response'])
    print(json.dumps({'endpoint':request['endpoint'],'entity':request['entity'],'independent_rows':count,'issues':issues}))
    raise SystemExit(bool(issues))
