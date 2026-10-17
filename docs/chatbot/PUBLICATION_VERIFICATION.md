# Publication and scientific-claim verification

**Historical v1 report.** [Revision 2 reconciliation](./PUBLICATION_RECONCILIATION.md)
supersedes the six PMID namespace classifications and withdraws both apparent
misattribution findings below: those references are AYUSH IDs. The DOI remains a
registry lookup issue, not an invalid identifier. Correction review is now out
of scope by user instruction. Read revision 2 for current counts and conclusions.

Initial audit: 2026-09-24 (local time). This evaluation does not change chatbot answers or database citations.

## Findings

The frozen independent graph snapshot contains 10,123 cited edges and 410 unique
normalized identifiers (398 numeric references interpreted as PMIDs, 12 DOIs).
NCBI PubMed and Crossref returned matching publication identities for **405/410**.
This is an identity result, **not a scientific-support pass rate**. Raw numeric
`ref` fields do not themselves establish that PubMed is the intended namespace.

| Finding | Scope | Required action |
|---|---|---|
| PMID 76448 resolves to a lymphoma case; graph uses it for seed-processing claims | 16 attached edges; one abstract-level mismatch screened | Verify original reference namespace and intended source |
| PMID 239250 has a cardiac-signalling title; graph uses it for seed-processing claims | 50 attached edges; one title-level mismatch screened | Obtain original reference and full text; title screening is preliminary |
| PMIDs 216742, 216736, 2201, 13110 not returned | 80 edges; both batch and individual requests omit them | Resolve identifiers with original data curator; absence is not proof of nonexistence |
| DOI 10.4103/joa.joa_262_22 not found in Crossref | 33 edges | Check publisher/DOI resolver and original citation; Crossref absence alone is inconclusive |
| PMID 39210691 has linked correction PMID 39308055 | 81 edges | Read correction and assess affected claims; a correction is not automatically a retraction |

Primary sources: [PMID 76448](https://pubmed.ncbi.nlm.nih.gov/76448/),
[PMID 239250](https://pubmed.ncbi.nlm.nih.gov/239250/),
[correction](https://pubmed.ncbi.nlm.nih.gov/39308055/).
Counts are affected-edge queues, not counts of individually disproven claims.
147 numeric references have six or fewer digits. They warrant namespace review,
but identifier length alone does not establish invalidity or an alternative registry.

## Source-document pilot

Eight selected edges were screened by the AI assistant. Selection was purposive,
not random; these results cannot estimate scientific accuracy across the graph.

| Result | Edges | Review scope |
|---|---|---|
| Supported as reported observations | 4 | Rashes, pain and scaling in PMID 34758243 abstract; round patches in PMID 23559794 full text |
| Requires full text | 1 | Hypersomnia is not established in the inspected PMID 34758243 abstract; absence is not contradiction |
| Requires scope qualification | 1 | Anger is assessed in PMID 23559794; expert review must judge the graph's symptom categorization |
| Suspected misattribution | 2 | PMID 76448 abstract and PMID 239250 title |

The [case report](https://www.degruyterbrill.com/document/doi/10.1515/jcim-2020-0433/html)
describes a single patient. The [full-text study](https://pmc.ncbi.nlm.nih.gov/articles/PMC3611636/)
uses an open prospective design. Observations in these sources do not establish
general clinical efficacy, causation, safety, or prevalence. Detailed dose,
duration, procedure and outcome properties remain unreviewed in this pilot.

**Coverage: 8/10,123 edges screened; 10,115 not screened. Zero expert approvals.**
Two reviews inspect full text, five inspect abstracts and one inspects metadata.
The identity audit intentionally retains all source-support statuses as pending;
the separately validated pilot contains the preliminary source judgments.

## Reproducible artifacts and checks

- `api/tests/verify_publications.py`: read-only registry audit against the frozen snapshot.
- `api/tests/fixtures/publication_verification_v1.json`: metadata, request timestamps/hashes, snapshot hash, and exact per-edge queue with raw properties and direction.
- `api/tests/fixtures/publication_followup_v1.json`: individual missing-PMID checks and correction metadata.
- `api/tests/fixtures/publication_claim_reviews_v1.json`: source URLs, section locators, paraphrased reasoning, limits, exact edge hashes, and priority queues.
- `api/tests/validate_publication_reviews.py`: validates provenance and review boundaries; it does not certify scientific judgments.

```sh
python api/tests/test_verify_publications.py
python api/tests/validate_publication_reviews.py
# Live registry refresh; always use a NEW output filename:
python api/tests/verify_publications.py --output api/tests/fixtures/publication_verification_v2.json
```

Audit files are versioned by filename and never overwritten by the CLI. The live
tool checkpoints its output during requests; consume it only after completion.
Metadata queries use NCBI EFetch (batches of 50) and Crossref; HTTP failures remain
separate from missing records. PubMed bibliography identifiers cannot replace the
article's own identifiers. No Llama judgment or keyword overlap is used to certify
source support. The pilot requires a new review when the snapshot/claim hash changes.

## Next review work

1. Resolve namespace and suspected source mismatches with the original data curator,
   before changing or replacing references. Expand title/abstract screening across
   the remaining sources; identity verification alone must never clear an edge.
2. Retrieve source documents and the correction, recording access scope and section,
   page or table locators. Check each directed association and each populated edge
   property independently, including units, population and treatment phase.
3. Have a domain expert assess study quality and whether source wording supports
   the graph's semantic relationship. Record reviewer identity, date, expertise,
   verdict and rationale. Disputed decisions need a second reviewer/adjudication.
4. Track identity, association support, property support and expert approval
   separately. Unavailable full text remains unresolved; it is neither a pass nor
   proof that the claim is false. Review all edges, not just one per publication.

The preliminary pilot intentionally refuses expert-approved labels. A future
human-review ingestion workflow must validate reviewer records and preserve
decision history before using those decisions as evaluation ground truth.
