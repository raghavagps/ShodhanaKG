"""Independent raw-record joins for all 53 endpoints and additional seeds.

Endpoint names and projection contracts are manually specified. No production
catalog, resolver, endpoint output or correlation implementation is imported.
"""
import argparse
from collections import defaultdict
import hashlib
import json
from pathlib import Path
from build_factual_ground_truth import ROOT, DEFINITIONS, PHASES, identifiers, shared_reference
from build_legacy_ground_truth import DEFINITIONS_LEGACY, project as legacy_project, properties, key


def step(edges, side, targets):
    return (tuple(edges.split('|')), '@out' if side == 'out' else '@in', tuple(targets.split('|')))


U = step('UNDERGOES', 'out', 'Shodhana_Method')
P = step('PRODUCES', 'out', 'Purified_Compound')
V = step('VALIDATED_BY', 'out', 'Confirmation_Study')
F = step('FOLLOWED_BY', 'out', 'Process')
PI = step('PRODUCES', 'in', 'Shodhana_Method|Process')
UI = step('UNDERGOES', 'in', 'Compound')
# Separate reference specification; contracts are not read from PHP.
EXTRA = {
 'compound_methods': (('Compound',), [[U]]),
 'compound_validation': (('Compound',), [[U,V],[U,F,V]]),
 'compound_toxicity': (('Compound',), [[step('CONTAINS','out','Toxic_Substance')]]),
 'compound_detoxification': (('Compound',), [[U,step('DETOXIFIES','out','Toxic_Substance')]]),
 'compound_therapeutic_use': (('Compound',), [[U,P,step('TREATS','out','Indication')]]),
 'disease_symptoms': (('Disease',), [[step('HAS_SYMPTOM','out','Symptom')]]),
 'disease_therapy': (('Disease',), [[step('|'.join(PHASES),'out','Therapy')]]),
 'integrated_compound_disease': (('Compound',), [[U,P,step('USED_FOR_TREATMENT','out','Disease')]]),
 'shodhana_method_validation': (('Shodhana_Method',), [[V],[F,V]]),
 'indication_compound': (('Indication',), [[step('TREATS','in','Purified_Compound'),PI,UI]]),
 'toxic_substance_compound': (('Toxic_Substance',), [[step('CONTAINS','in','Compound')]]),
 'confirmation_study_method': (('Confirmation_Study',), [[step('VALIDATED_BY','in','Shodhana_Method|Process')]]),
 'confirmation_study_purified_compound': (('Confirmation_Study',), [[step('CONFIRMS_SAFETY_OF','out','Purified_Compound')]]),
 'compound_purified_forms': (('Compound',), [[step('DERIEVED_FROM','in','Purified_Compound')]]),
 'formulation_compound': (('Formulation','Ayurvedic_Formulation'), [[step('USED_IN_FORMULATION','in','Purified_Compound')]]),
 'ayurvedic_diagnosis_disease': (('Ayurvedic_Diagnosis',), [[step('HAS_AYURVEDIC_DIAGNOSIS','in','Disease')]]),
 'assessment_disease': (('Assessment_Parameter',), [[step('ASSESS','out','Disease')]]),
 'process_classification': (('Shodhana_Method',), [[step('CLASSIFIED_AS','out','Shodhana_Type')]]),
 'classification_process': (('Shodhana_Type',), [[step('CLASSIFIED_AS','in','Shodhana_Method')]]),
 'purified_compound_disease': (('Purified_Compound',), [[step('USED_FOR_TREATMENT','out','Disease')],[step('TREATS','out','Indication')]]),
 'purified_compound_formulation': (('Purified_Compound',), [[step('USED_IN_FORMULATION','out','Formulation|Ayurvedic_Formulation')]]),
 'purified_compound_source': (('Purified_Compound',), [[PI,UI]]),
 'purified_compound_validation': (('Purified_Compound',), [[step('CONFIRMS_SAFETY_OF','in','Confirmation_Study')]]),
 'integrated_disease_compound': (('Disease',), [[step('USED_FOR_TREATMENT|TREATS','in','Purified_Compound'),PI,UI]]),
 'disease_dosha': (('Disease',), [[step('HAS_DOSHA_IMBALANCE','out','Dosha')]]),
 'disease_diagnosis': (('Disease',), [[step('HAS_AYURVEDIC_DIAGNOSIS','out','Ayurvedic_Diagnosis')]]),
 'disease_assessment': (('Disease',), [[step('ASSESS','in','Assessment_Parameter')]]),
 'symptoms_disease': (('Symptom',), [[step('HAS_SYMPTOM','in','Disease')]]),
 'dosha_disease': (('Dosha',), [[step('HAS_DOSHA_IMBALANCE','in','Disease')]]),
}
CONTRACTS = {e: ((d[0],), [d[2]]) for e,d in {**DEFINITIONS, **DEFINITIONS_LEGACY}.items()}
CONTRACTS.update(EXTRA)
ID_ONLY = {'confirmation_study_method','confirmation_study_purified_compound','compound_purified_forms',
           'formulation_compound','ayurvedic_diagnosis_disease','assessment_disease','process_classification','classification_process'}
TARGET_FIELD = {'compound_toxicity':'toxic_substance','disease_symptoms':'symptom','disease_therapy':'therapy',
 'toxic_substance_compound':'compound','purified_compound_disease':'disease','purified_compound_formulation':'formulation',
 'disease_dosha':'dosha','disease_diagnosis':'ayurvedic_diagnosis','disease_assessment':'assessment_parameter',
 'symptoms_disease':'disease','dosha_disease':'disease'}


class Oracle:
    def __init__(self, tables):
        self.tables = tables
        self.nodes = {r['@rid']: r for rows in tables.values() for r in rows if r.get('@cat') == 'v'}
        self.node_records = {rid:{'id':rid,'type':n['@type'],'label':n['name']} for rid,n in self.nodes.items()}
        self.edge_records = {}
        self.index = defaultdict(list)
        for rows in tables.values():
            for edge in rows:
                if edge.get('@cat') != 'e': continue
                self.edge_records[edge['@rid']] = {'id':edge['@rid'],'type':edge['@type'],'source':edge['@out'],'target':edge['@in'],
                    'properties':{k:v for k,v in edge.items() if not k.startswith('@')},'evidence':identifiers(edge)}
                for side in ('@out','@in'): self.index[(edge['@type'],side,edge[side])].append(edge)

    def paths(self, endpoint, seed, steps):
        accepted, rejected = [], []
        def walk(nodes, edges, depth):
            if depth == len(steps):
                (accepted if self.correlated(endpoint, edges) else rejected).append(self.path(nodes, edges))
                return
            types, side, targets = steps[depth]
            candidates = [e for t in types for e in self.index[(t,side,nodes[-1]['@rid'])]]
            if not candidates and depth and endpoint in ('indication_compound','integrated_disease_compound'):
                (accepted if self.correlated(endpoint, edges) else rejected).append(self.path(nodes, edges))
            for edge in candidates:
                node = self.nodes.get(edge['@in' if side == '@out' else '@out'])
                if node and node['@type'] in targets: walk(nodes+[node],edges+[edge],depth+1)
        walk([seed],[],0)
        return accepted, rejected

    def correlated(self, endpoint, edges):
        # Therapeutic-use contract connects a separately cited indication to a
        # correlated purification path; it does NOT claim one shared study.
        checked = edges[:2] if endpoint == 'compound_therapeutic_use' else edges
        if len(checked) < 2: return True
        ids=[self.edge_records[e['@rid']]['evidence'] for e in checked]
        for kind in ('pubmed_id','doi','ayush_id'):
            if ids[0].get(kind) and ids[1].get(kind): return all(row.get(kind)==ids[0][kind] for row in ids)
        return False

    def path(self, nodes, edges):
        p = {'nodes':[self.node_records[n['@rid']] for n in nodes],
             'edges':[self.edge_records[e['@rid']] for e in edges],
             'related_entity':nodes[-1]['name'],'related_type':nodes[-1]['@type']}
        if len(edges)>1:
            a,b=(self.edge_records[e['@rid']]['evidence'] for e in edges[:2])
            p['matched_evidence']=None
            for kind in ('pubmed_id','doi','ayush_id'):
                if a.get(kind) and b.get(kind):
                    if a[kind]==b[kind]:p['matched_evidence']={'type':kind,'value':a[kind]}
                    break
        for e in edges:
            if e['@type'] in PHASES: p['phase']=e['@type']
        return p

    def safety(self, path):
        study=path['nodes'][-1]['id']; raw=path['edges'][-1]['properties']
        return [{'target_entity':self.nodes[e['@in']]['name'], 'result':properties(self.edge_records[e['@rid']])['result'],
                 'properties':identifiers(e)} for e in self.index[('CONFIRMS_SAFETY_OF','@out',study)] if shared_reference(raw,e)]

    def project(self, endpoint, path):
        if endpoint in DEFINITIONS or endpoint == 'compound_methods': return path
        if endpoint in DEFINITIONS_LEGACY: return legacy_project(endpoint,path)
        n,e=path['nodes'],path['edges']; target=n[-1]['label']; props=properties(e[-1])
        if endpoint in ID_ONLY: return {'related_entity':target,'properties':identifiers(e[-1]['properties'])}
        if endpoint in TARGET_FIELD:
            if endpoint=='disease_therapy': props['phase']=e[0]['type']
            return {TARGET_FIELD[endpoint]:target,'properties':props}
        if endpoint=='purified_compound_validation':
            return {'study':target,'result':properties(e[0])['result'],'properties':identifiers(e[0]['properties'])}
        if endpoint=='compound_detoxification':
            props['procedure']=properties(e[0])['procedure'] or props['procedure']
            return {'shodhana_method':n[1]['label'],'toxic_substance':target,'properties':props}
        if endpoint in ('compound_therapeutic_use','integrated_compound_disease'):
            props['procedure']=properties(e[0])['procedure'] or props['procedure']
            return {'shodhana_method':n[1]['label'],'purified_compound':n[2]['label'],
                    'indication' if endpoint=='compound_therapeutic_use' else 'disease':target,'properties':props}
        if endpoint=='purified_compound_source':
            return {'shodhana_method':n[1]['label'],'compound':target,'properties':props}
        if endpoint in ('indication_compound','integrated_disease_compound'):
            if endpoint=='indication_compound' and len(e)==3:
                props=properties(e[0]);props['procedure']=properties(e[-1])['procedure'] or props['procedure']
            row={'compound':target if len(e)==3 else None,'shodhana_method':n[2]['label'] if len(e)>1 else None,
                 'purified_compound':n[1]['label'],'properties':props}
            if endpoint=='indication_compound': row['indication']=n[0]['label']
            return row
        if endpoint=='shodhana_method_validation':
            return {'study':target,'method' if len(e)==1 else 'process':n[-2]['label'],'properties':properties(e[-1])}
        if endpoint=='compound_validation':
            vp=identifiers(e[-1]['properties']);vp['procedure']=props['procedure']
            row={'validation_path':['Shodhana_Method','VALIDATED_BY','Confirmation_Study'],
                 'method':n[1]['label'],'study':target,'validation_properties':vp}
            if len(e)==3:
                row.update(process=n[2]['label'],validation_path=['Shodhana_Method','FOLLOWED_BY','Process','VALIDATED_BY','Confirmation_Study'])
            return row
        raise ValueError(endpoint)

    def reference(self, endpoint, seed):
        valid, rejected = [], []
        for steps in CONTRACTS[endpoint][1]:
            a,b=self.paths(endpoint,seed,steps);valid.extend(a);rejected.extend(b)
        rows={}; audits=defaultdict(list); safety=defaultdict(dict)
        for p in valid:
            row=self.project(endpoint,p); k=key(row); rows[k]=row
            audits[k].append([e['id'] for e in p['edges']])
            if endpoint=='compound_validation':
                for s in self.safety(p): safety[k][key(s)]=s
        forbidden={key(self.project(endpoint,p)):self.project(endpoint,p) for p in rejected}
        return {'rows':[rows[k] for k in sorted(rows)],'audit':[audits[k] for k in sorted(rows)],
                'safety':[list(safety[k].values()) for k in sorted(rows)],
                'forbidden':[forbidden[k] for k in sorted(forbidden) if k not in rows][:3],
                'accepted_paths':len(valid),'rejected_paths':len(rejected),
                'branches':sorted(set(tuple(e['type'] for e in p['edges']) for p in valid))}


def build(raw):
    data=json.loads(raw); oracle=Oracle(data['tables']);digest=hashlib.sha256(raw).hexdigest()
    baselines={e:d[1] for e,d in {**DEFINITIONS,**DEFINITIONS_LEGACY}.items()}
    cases=[];coverage=[]
    for endpoint,(types,_) in sorted(CONTRACTS.items()):
        print('Building '+endpoint,flush=True)
        candidates=sorted((n for n in oracle.nodes.values() if n['@type'] in types),key=lambda n:(n['name'],n['@rid']))
        # Selection uses only the raw graph: baseline seed plus two additional
        # positive entities, and one verified empty entity where one exists.
        selected=[];empty=None;positive_count=0;branches=set()
        for seed in candidates:
            ref=oracle.reference(endpoint,seed)
            if ref['rows']:
                positive_count+=1;branches.update(tuple(b) for b in ref['branches'])
                baseline=seed['name']==baselines.get(endpoint)
                covered={tuple(b) for _,r in selected for b in r['branches']}
                if baseline or sum(n['name']!=baselines.get(endpoint) for n,_ in selected)<2 or set(map(tuple,ref['branches']))-covered:
                    selected.append((seed,ref))
            elif empty is None: empty=(seed,ref)
        if empty: selected.append(empty)
        coverage.append({'endpoint':endpoint,'positive_entities_available':positive_count,
                         'positive_entities_tested':sum(bool(r['rows']) for _,r in selected),
                         'empty_entity_tested':empty is not None,'observed_branches':[list(b) for b in sorted(branches)],
                         'tested_branches':[list(b) for b in sorted({tuple(b) for _,r in selected for b in r['branches']})]})
        for index,(seed,ref) in enumerate(selected):
            case={'id':f'expanded_{endpoint}_{index+1}', 'question':f'Endpoint factual check: {endpoint} / {seed["name"]}',
                  'expected_endpoint':endpoint,'expected_entity':seed['name'],'expected_entity_type':seed['@type'],
                  'expected_status':'success' if ref['rows'] else 'no_data','expected_results':ref['rows'],
                  'expected_result_count':len(ref['rows']),'forbidden_results':ref['forbidden'],
                  'expected_result_source_paths':ref['audit'],
                  'ground_truth_source':{'snapshot':'factual_graph_snapshot_expanded.json','sha256':digest,'captured_at':data['captured_at'],
                       'basis':'independent raw-record joins and explicit projection contracts','identifier_version':2,
                       'review_status':'graph_verified_expert_review_pending','accepted_path_count':ref['accepted_paths'],'rejected_candidate_count':ref['rejected_paths']}}
            if endpoint=='compound_validation': case['expected_safety_candidates']=ref['safety']
            cases.append(case)
    return cases,coverage


def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--write',action='store_true');args=parser.parse_args()
    cases,coverage=build((ROOT/'factual_graph_snapshot_expanded.json').read_bytes())
    for name,value in [('expanded_factual_cases_v2.json',cases),('expanded_factual_coverage_v2.json',coverage)]:
        path=ROOT/name
        if args.write:
            with path.open('w',encoding='utf-8') as f: json.dump(value,f,indent=2,ensure_ascii=False)
        elif json.loads(path.read_text(encoding='utf-8'))!=value: raise SystemExit('Reference mismatch; inspect rather than overwriting')
    print(f'{len(coverage)} endpoints, {len(cases)} cases, {sum(c["expected_result_count"] for c in cases)} expected rows')


if __name__=='__main__': main()
