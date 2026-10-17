"""Offline guards: publication existence is not evidence of claim support."""
import copy
import json
import unittest
from verify_publications import ROOT, inventory, parse_pubmed, summary
from validate_publication_reviews import validate


class PublicationTests(unittest.TestCase):
    def test_pilot_provenance_and_review_boundaries(self):
        audit = (ROOT / 'publication_verification_v1.json').read_bytes()
        review = json.loads((ROOT / 'publication_claim_reviews_v1.json').read_bytes())
        self.assertEqual(validate(audit, review)['screened_edges'], 8)
        for field, value in [('claim_sha256', 'wrong'), ('publication_key', 'pubmed_id:76448'),
                             ('expert_review', {'status': 'approved', 'reviewer': 'AI'}),
                             ('review_scope', 'metadata')]:
            changed = copy.deepcopy(review)
            changed['reviews'][0][field] = value
            with self.subTest(field=field), self.assertRaises(ValueError):
                validate(audit, changed)

    def test_bibliography_ids_cannot_replace_article_ids(self):
        xml = b'''<PubmedArticleSet><PubmedArticle><MedlineCitation><PMID>1</PMID>
        <Article><ArticleTitle>A <i>title</i></ArticleTitle></Article></MedlineCitation>
        <PubmedData><ArticleIdList><ArticleId IdType="doi">10.1/actual</ArticleId></ArticleIdList>
        <ReferenceList><Reference><ArticleIdList><ArticleId IdType="doi">10.1/other</ArticleId>
        <ArticleId IdType="pmc">PMC999</ArticleId></ArticleIdList></Reference></ReferenceList>
        </PubmedData></PubmedArticle></PubmedArticleSet>'''
        result = parse_pubmed(xml)['1']
        self.assertEqual(result['doi'], '10.1/actual')
        self.assertIsNone(result['pmcid'])
        self.assertEqual(result['title'], 'A title')

    def test_frozen_inventory_preserves_every_edge(self):
        snapshot = json.loads((ROOT / 'factual_graph_snapshot_expanded.json').read_bytes())
        before = copy.deepcopy(snapshot)
        pubs, claims = inventory(snapshot)
        self.assertEqual(snapshot, before)
        self.assertEqual(len(claims), 10123)
        self.assertEqual(len(pubs), 410)
        self.assertEqual(len({c['edge_id'] for c in claims}), len(claims))
        self.assertTrue(all(c['source_support'] == 'not_reviewed' for c in claims))
        self.assertTrue(all(c['expert_review']['status'] == 'pending' for c in claims))

    def test_verified_publications_do_not_pass_claims(self):
        report = json.loads((ROOT / 'publication_verification_v1.json').read_bytes())
        result = summary(report)
        self.assertEqual(result['identity_statuses']['verified'], 405)
        self.assertEqual(result['source_support_reviewed'], 0)
        self.assertEqual(result['expert_reviewed'], 0)
        self.assertEqual(result['edges_with_unverified_identifiers'], 113)
        self.assertEqual(report['publications']['pubmed_id:2201']['identity_status'],
                         'not_returned_pending_confirmation')


if __name__ == '__main__':
    unittest.main()
