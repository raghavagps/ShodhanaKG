import copy
import json
import hashlib
import unittest
from build_expanded_ground_truth import Oracle, CONTRACTS, ROOT, step


class ExpandedReferenceTests(unittest.TestCase):
    def setUp(self):
        def node(rid, kind, name): return {'@rid':rid,'@cat':'v','@type':kind,'name':name}
        def edge(rid, kind, source, target, ref): return {'@rid':rid,'@cat':'e','@type':kind,'@out':source,'@in':target,'ref':ref}
        self.seed=node('a','Compound','A')
        self.tables={'Compound':[self.seed], 'Shodhana_Method':[node('m','Shodhana_Method','M')],
                     'Media':[node('x','Media','X'),node('y','Media','Y')],
                     'UNDERGOES':[edge('u','UNDERGOES','a','m','111')],
                     'USES_MEDIA':[edge('v','USES_MEDIA','m','x','111'),edge('w','USES_MEDIA','m','y','222')]}
        self.steps=[step('UNDERGOES','out','Shodhana_Method'),step('USES_MEDIA','out','Media')]

    def test_correlated_paths_exclude_connected_other_study(self):
        valid,invalid=Oracle(self.tables).paths('compound_media',self.seed,self.steps)
        self.assertEqual([p['related_entity'] for p in valid],['X'])
        self.assertEqual([p['related_entity'] for p in invalid],['Y'])
        self.assertEqual([e['id'] for e in valid[0]['edges']],['u','v'])

    def test_wrong_direction_and_wrong_type_are_rejected(self):
        for change in ('direction','type'):
            tables=copy.deepcopy(self.tables)
            if change=='direction': tables['UNDERGOES'][0]['@out'],tables['UNDERGOES'][0]['@in']='m','a'
            else: tables['Shodhana_Method'][0]['@type']='Process'
            self.assertEqual(Oracle(tables).paths('compound_media',self.seed,self.steps),([],[]))

    def test_inverse_retains_stored_direction(self):
        seed=self.tables['Media'][0]
        paths,_=Oracle(self.tables).paths('media_compound',seed,[step('USES_MEDIA','in','Shodhana_Method'),step('UNDERGOES','in','Compound')])
        self.assertEqual(paths[0]['related_entity'],'A')
        self.assertEqual(paths[0]['edges'][0]['source'],'m')
        self.assertEqual(paths[0]['edges'][0]['target'],'x')

    def test_reference_changes_when_raw_citation_or_property_changes(self):
        baseline=Oracle(self.tables).reference('compound_media',self.seed)
        changed=copy.deepcopy(self.tables);changed['USES_MEDIA'][0]['ref']='10.1234/example'
        self.assertEqual(Oracle(changed).reference('compound_media',self.seed)['rows'],[])
        changed=copy.deepcopy(self.tables);changed['USES_MEDIA'][0]['Procedure']='A new procedure'
        other=Oracle(changed).reference('compound_media',self.seed)
        self.assertNotEqual(baseline['rows'],other['rows'])

    def test_process_source_is_a_valid_partial_inverse_path(self):
        tables={'Indication':[{'@rid':'i','@cat':'v','@type':'Indication','name':'I'}],
                'Purified_Compound':[{'@rid':'p','@cat':'v','@type':'Purified_Compound','name':'P'}],
                'Process':[{'@rid':'x','@cat':'v','@type':'Process','name':'X'}],
                'TREATS':[{'@rid':'t','@cat':'e','@type':'TREATS','@out':'p','@in':'i','ref':'111'}],
                'PRODUCES':[{'@rid':'s','@cat':'e','@type':'PRODUCES','@out':'x','@in':'p','ref':'111'}]}
        oracle=Oracle(tables)
        result=oracle.reference('indication_compound',tables['Indication'][0])
        self.assertEqual(result['rows'][0]['compound'],None)
        self.assertEqual(result['rows'][0]['shodhana_method'],'X')
        self.assertEqual(result['audit'],[[['t','s']]])
        tables['PRODUCES'][0]['ref']='222'
        self.assertEqual(Oracle(tables).reference('indication_compound',tables['Indication'][0])['rows'],[])

    def test_all_registered_endpoints_have_independent_contracts(self):
        inventory=json.loads((ROOT/'traversal_inventory.json').read_text(encoding='utf-8'))
        entries=next(v for v in inventory.values() if isinstance(v,list) and v and isinstance(v[0],dict) and 'endpoint' in v[0])
        self.assertEqual(set(CONTRACTS),{e['endpoint'] for e in entries})
        self.assertEqual(len(CONTRACTS),53)

    def test_original_snapshot_and_references_are_preserved(self):
        original=json.loads((ROOT/'factual_graph_snapshot.json').read_text(encoding='utf-8'))
        expanded=json.loads((ROOT/'factual_graph_snapshot_expanded.json').read_text(encoding='utf-8'))
        for table,rows in original['tables'].items():
            self.assertEqual(sorted(rows,key=lambda r:r['@rid']),sorted(expanded['tables'][table],key=lambda r:r['@rid']))
        oracle=Oracle(expanded['tables'])
        for file in ('factual_evaluation_cases_v2.json','legacy_factual_evaluation_cases_v2.json'):
            for case in json.loads((ROOT/file).read_text(encoding='utf-8')):
                seeds=[n for n in oracle.nodes.values() if n['name']==case['expected_entity'] and n['@type'] in CONTRACTS[case['expected_endpoint']][0]]
                self.assertEqual(len(seeds),1)
                actual=oracle.reference(case['expected_endpoint'],seeds[0])['rows']
                self.assertEqual(sorted(map(lambda r:json.dumps(r,sort_keys=True),actual)),sorted(map(lambda r:json.dumps(r,sort_keys=True),case['expected_results'])),case['id'])

    def test_expanded_labels_rebuild_from_raw_records_and_cover_each_endpoint(self):
        raw=(ROOT/'factual_graph_snapshot_expanded.json').read_bytes()
        oracle=Oracle(json.loads(raw)['tables'])
        cases=json.loads((ROOT/'expanded_factual_cases_v2.json').read_text(encoding='utf-8'))
        coverage=json.loads((ROOT/'expanded_factual_coverage_v2.json').read_text(encoding='utf-8'))
        self.assertEqual(len({c['id'] for c in cases}),len(cases))
        self.assertEqual({c['expected_endpoint'] for c in cases},set(CONTRACTS))
        for endpoint in CONTRACTS:
            seeds={(c['expected_entity_type'],c['expected_entity']) for c in cases if c['expected_endpoint']==endpoint and c['expected_status']=='success'}
            self.assertGreaterEqual(len(seeds),2,endpoint)
        for item in coverage:self.assertEqual(item['tested_branches'],item['observed_branches'],item['endpoint'])
        for case in cases:
            seed=next(n for n in oracle.nodes.values() if n['@type']==case['expected_entity_type'] and n['name']==case['expected_entity'])
            ref=oracle.reference(case['expected_endpoint'],seed)
            self.assertEqual(case['ground_truth_source']['sha256'],hashlib.sha256(raw.replace(b'\r\n', b'\n')).hexdigest())
            self.assertEqual(ref['rows'],case['expected_results'],case['id'])
            self.assertEqual(ref['audit'],case['expected_result_source_paths'],case['id'])
            self.assertEqual(ref['forbidden'],case['forbidden_results'],case['id'])
            self.assertEqual(len(ref['rows']),case['expected_result_count'])


    def test_all_allowlisted_endpoints_have_evaluation_cases(self):
        import re
        orchestrator_path = ROOT.parent.parent / 'core' / 'ChatbotOrchestrator.php'
        orchestrator_text = orchestrator_path.read_text(encoding='utf-8')
        allowlist = set(re.findall(r"'([a-z_]+)'\s*=>\s*'execute_\w+'", orchestrator_text))
        self.assertEqual(len(allowlist), 53)
        self.assertEqual(set(CONTRACTS), allowlist)
        cases = json.loads((ROOT / 'expanded_factual_cases_v2.json').read_text(encoding='utf-8'))
        covered_endpoints = {c['expected_endpoint'] for c in cases}
        missing = allowlist - covered_endpoints
        self.assertEqual(missing, set(), f"Allowlisted endpoints missing evaluation cases: {missing}")


if __name__=='__main__':unittest.main()
