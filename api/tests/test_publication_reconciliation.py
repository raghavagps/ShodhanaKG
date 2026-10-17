import copy
import json
import unittest
from verify_publications import ROOT, inventory
from reconcile_publications import reconcile
from validate_publication_reviews import validate


class ReconciliationTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.old = json.loads((ROOT / 'factual_graph_snapshot_expanded.json').read_bytes())
        cls.live = json.loads((ROOT / 'publication_live_snapshot_v2.json').read_bytes())
        cls.mapping = json.loads((ROOT / 'publication_namespace_overrides_v2.json').read_bytes())
        cls.audit_bytes = (ROOT / 'publication_verification_v2.json').read_bytes()
        cls.audit = json.loads(cls.audit_bytes)
        cls.result = reconcile(cls.old, cls.live, cls.mapping, cls.audit)

    def test_overlay_preserves_raw_graph_and_edge_hashes(self):
        before = copy.deepcopy(self.live)
        pubs, claims = inventory(self.live, self.mapping['mappings'])
        self.assertEqual(self.live, before)
        _, originals = inventory(self.old)
        self.assertEqual({c['edge_id']: c['claim_sha256'] for c in claims},
                         {c['edge_id']: c['claim_sha256'] for c in originals})
        counts = {'76448': 16, '239250': 50, '216742': 18, '216736': 20, '2201': 24, '13110': 18}
        for value, count in counts.items():
            self.assertNotIn('pubmed_id:' + value, pubs)
            self.assertEqual(len(pubs['ayush_id:' + value]['edge_ids']), count)
            self.assertNotIn('metadata', self.audit['publications']['ayush_id:' + value])

    def test_count_is_not_floor_or_normalization_loss(self):
        c = self.result['count_reconciliation']
        self.assertEqual(c['raw_nonempty_identifiers'], 410)
        self.assertEqual(c['normalized_identifiers'], 410)
        self.assertEqual(c['identifier_categories'], {'pubmed_id': 392, 'doi': 12, 'ayush_id': 6})
        self.assertEqual(c['stats_pre_floor_count'], 410)
        self.assertEqual(c['stats_display_count'], 412)
        self.assertEqual(c['edges_missing_reference'], [])
        self.assertEqual(c['normalization_collision_groups'], {})
        self.assertEqual(c['stats_normalization_collisions'], {})
        self.assertEqual(c['repeated_reference_occurrences_beyond_first'], 9713)
        self.assertEqual(len(c['short_numeric_namespace_review_queue']), 141)

    def test_original_pmid_preserved_without_correction_judgments(self):
        c = self.result['original_study_reference_check']
        self.assertEqual(len(c['edge_review_queue']), 81)
        self.assertTrue(c['all_raw_refs_match_original_pmid'])
        self.assertEqual(c['edges_marked_incorrect'], [])
        self.assertTrue(all(x['publication_keys'] == ['pubmed_id:39210691'] for x in c['edge_review_queue']))

    def test_withdrawn_misattribution_reviews_are_not_current(self):
        r = json.loads((ROOT / 'publication_claim_reviews_v2.json').read_bytes())
        self.assertEqual(validate(self.audit_bytes, r)['screened_edges'], 6)
        self.assertEqual(len(r['withdrawn_reviews']), 2)
        self.assertFalse(any(x['verdict'] == 'suspected_misattribution' for x in r['reviews']))
        self.assertEqual(self.result['doi_followup']['invalid_doi_finding'], False)
        self.assertEqual(self.audit['publications']['doi:10.4103/joa.joa_262_22']['identity_status'], 'registry_lookup_unresolved')


if __name__ == '__main__':
    unittest.main()
