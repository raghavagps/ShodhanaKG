# Publication reconciliation, revision 2

Updated 2026-09-24. Supersedes the namespace, missing-PMID and suspected-misattribution
findings in the initial publication audit. KG records and production code are unchanged.
Correction-content review is excluded by the user's subsequent instruction.

## Where the study count comes from

A read-only capture of **all 39 database schema types** found 2,534 unique vertices
and 10,123 unique edges. The `Node` parent table repeats child vertices; it does not
add publications. There is no dedicated publication/study-record table. Despite its
name, `Confirmation_Study` contains method/category nodes such as cytotoxicity,
HPTLC and biochemical analysis, not one bibliographic record per paper.

The study references are stored in edge `ref` properties. All 10,123 edges have a
nonempty reference. Their **actual distinct study-reference count is 410**, both
in the previous snapshot and the current database. Every edge ID, direction and
property matches the prior snapshot; no edge type or citation was omitted.

| Reconciliation item | Count |
|---|---:|
| Distinct raw references | 410 |
| Distinct normalized, namespaced identifiers | 410 |
| User-confirmed AYUSH identifiers | 6 |
| Remaining numeric references, provisionally queried as PMIDs | 392 |
| DOI references | 12 |
| Edges with missing/unclassified references | 0 |
| References merged through normalization | 0 |
| Repeated uses of references beyond their first edge | 9,713 |
| Duplicate aliases established from returned DOI metadata | 0 |

One DOI is lowercased (`10.4103/AYU.AYU_87_20`); this changes spelling but does not
merge references. Repeated references across edges are not additional studies.
The six namespace corrections change categories, not the total.

`api/kg-stats.php:65` raises any computed count below 412 to **412**;
`src/pages/ModeSelection.tsx:341` also uses 412 as a fallback. The current computed
count before the floor is 410. Thus **412 is a display floor, not 412 study records
that were reduced to 410 by this audit**. No evidence of two missing studies was found.

There is also a separate stats normalization defect: trimming trailing zeroes
alters 41 raw references, including legitimate PMID endings and one DOI. It causes
no collisions in this dataset and does not explain the two-count difference. The
audit preserves those identifiers. Neither this defect nor the floor was changed.

410 is the count of distinct study references represented in this KG, not an
independently established count of unique real-world publications. No master
bibliography exists locally; the sample CSV contains only 56 reference values,
all already present in the graph. Cross-registry aliases without matching returned
DOI metadata cannot yet be excluded. Of the 392 numeric candidates, 141 have at
most six digits and need intended-namespace confirmation. Length alone is not an
AYUSH classification rule, and a matching PubMed record does not establish that
PubMed was the intended namespace.

## Corrected identifiers and rerun

| AYUSH ID | Explorer | Exact attached edges |
|---|---|---:|
| 76448 | Compound | 16 |
| 239250 | Compound | 50 |
| 216742 | Disease | 18 |
| 216736 | Disease | 20 |
| 2201 | Compound | 24 |
| 13110 | Compound | 18 |

The six mappings are an audit-only overlay attributed to the user's corrections.
They are never queried as PMIDs in the new audit. The unrelated lymphoma and
cardiac-paper findings are **withdrawn audit errors**, not evidence against the
66 KG edges. The other four references are no longer missing-PMID findings.
AYUSH source-document retrieval remains pending; namespace confirmation is separate
from confirming source contents.

The live registry rerun returned **403 matching records** (392 numeric candidates
in PubMed and 11 DOIs in Crossref), **six confirmed AYUSH namespaces with source
verification pending**, and **one DOI registry lookup issue**. These are registry
results, not a scientific-accuracy score. The 179 edges attached to the six AYUSH
IDs and the unresolved DOI are a retrieval queue, not failed scientific claims.

DOI `10.4103/joa.joa_262_22` is retained exactly as supplied. The user identifies
it as the Journal of Ayurveda comparison of purified and unpurified Bhallataka
seeds. Crossref and the DOI resolver returned 404, while the
[publisher endpoint](https://journals.lww.com/10.4103/joa.joa_262_22) returned 403.
These access/registry results **do not establish an invalid DOI**. Publisher-level
verification remains pending; all 33 attached edges are retained.

## PMID 39210691: reference check only

All **81 edges** have raw `ref = "39210691"` and map to `pubmed_id:39210691`.
No edge references the correction as its replacement, and no edge was modified,
removed, marked incorrect, or judged affected/unaffected by the correction.

The exact preserved list is in `publication_reconciliation_v2.json`, including
source/target IDs, direction, hashes and complete properties. It includes 28
`CONFIRMS_SAFETY_OF`, 23 `TREATS`, eight `USES_MEDIA`, seven `VALIDATED_BY`, four
each of `PRODUCES`, `UNDERGOES`, `DERIEVED_FROM`, two `USED_FOR_TREATMENT`, and one
`FOLLOWED_BY` edge. The stored properties include 28 results and eight procedures.
Earlier correction-access attempts are historical only; correction review is out
of scope and is not a remaining requirement for this task.

## Artifacts and validation

- `publication_live_snapshot_v2.json`: read-only complete schema capture.
- `publication_namespace_overrides_v2.json`: user-supplied mappings and authority.
- `publication_verification_v2.json`: fresh registry requests and edge attribution.
- `publication_reconciliation_v2.json`: exact identifier ledger, count explanation,
  withdrawn findings and the 81-edge original-PMID check.
- `publication_access_checks_v2.json`: DOI/publisher access outcomes; correction
  attempts recorded before the scope clarification are historical only.
- `publication_claim_reviews_v2.json`: six retained preliminary reviews and two
  explicitly withdrawn reviews; zero expert approvals. Old v1 JSON is preserved.

All artifacts are under `api/tests/fixtures/`. Run:

```sh
python api/tests/test_verify_publications.py
python api/tests/test_publication_reconciliation.py
python api/tests/validate_publication_reviews.py
# Registry rerun with preserved KG data and explicit namespace overlay:
python api/tests/verify_publications.py --snapshot api/tests/fixtures/publication_live_snapshot_v2.json --output api/tests/fixtures/publication_verification_v3.json
# Reproduce v2 reconciliation into a new file:
python api/tests/reconcile_publications.py --output api/tests/fixtures/publication_reconciliation_check.json
```

Remaining work: verify AYUSH source documents and the DOI through the publisher;
confirm other ambiguous numeric namespaces; obtain expert source/claim review.
Neither identifier corrections nor registry responses automatically approve claims.
