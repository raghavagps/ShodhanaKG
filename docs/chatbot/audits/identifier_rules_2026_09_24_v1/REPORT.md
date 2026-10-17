# Identifier classification audit — 2026-09-24, version 1

**The proposed split matches this database snapshot, but the three rules are not
safe as general production classification rules.** Use the independently checked
reference-to-namespace mapping for these records; use evidence and explicit types
for new references rather than length or the presence of letters.

Complete requested table: [Markdown, 410 rows](./reference_classification.md) or
[CSV, 410 rows](./reference_classification.csv). Each row contains Reference,
Current type, Proposed type, Independently verified?, Source/evidence, and Conflict.
[Machine-readable audit](./audit.json) also retains exact edge IDs and registry
metadata. No production data, production code or existing fixture was rewritten.

## Counts

| Measure | Count |
|---|---:|
| Distinct stored references | **410** |
| Source-backed PMID assignments | **251** |
| Source-backed DOI assignments | **12** |
| Source-backed AYUSH assignments | **147** |
| Unclassified/ambiguous after registry and KG-context assessment | **0** |
| Confirmed exceptions to the proposed split in this snapshot | **0** |
| Current production-classifier conflicts | **147** |
| Numeric values that exist in both AYUSH and PubMed | **143** |
| DOI registration/resolution issues still unresolved | **1** |

Zero unresolved namespace assignments does not mean that raw strings are
unambiguous: 143 have cross-registry collisions. Nor does it mean every DOI
resolves, every reference denotes a different publication, or any claim is
scientifically validated. The zero-exception count is scoped to these stored
references and the interpretation of the proposed DOI rule described below.

Current production output is **398 PMID / 12 DOI / 0 AYUSH**. The KG itself stores
untyped `ref` values on edges; the table's Current type is what
`EvidenceCorrelator::extractIdentifiers()` returns today, not an explicit database
namespace field. The audit-only six-ID correction did not change this function.

## Complete scan and evidence

1. Queried all **39 schema types**, including the `Node` parent type; deduplicated
   records by RID. Checked table counts before and after reads. Found 10,123 edges,
   no missing/empty edge references and no vertex-level `ref` values. This is a
   read-only, per-table consistency check, not a transactional database snapshot.
2. Compared the complete RID-to-reference mapping with the prior live capture:
   **no changes**. Original strings and all edge IDs are preserved in
   [stored_references.json](./stored_references.json). No normalization merges were
   needed. The one uppercase DOI spelling remains visible in the table.
3. Queried the official [AYUSH Research Portal](https://arp.ayush.gov.in/research_advance_search)
   for **all 398 numeric values**, not just values assumed to be AYUSH IDs. All
   requests completed. The initial Art ID request parameter did not filter results;
   the actual working search field was verified before running the full scan.
   Every accepted result must have an exact numeric Art ID component; substring
   search hits do not count. Records such as `76448(Ay)` retain their explicit
   system suffix and their separate modern ARP identifier. Paging and response
   hashes are saved in [ayush_registry.json](./ayush_registry.json).
4. **All 147 short numbers returned one exact AYUSH Art ID each.** No eight-digit
   reference returned an exact AYUSH Art ID. Registry titles were inspected beside
   KG disease/compound subjects to assess the intended namespace, not to validate
   the edge's medical claim. This extends the six user-confirmed mappings to 147
   source-backed assignments without altering those historical mapping fixtures.
5. All **251 eight-digit references have exact PubMed records** in the saved NCBI
   EFetch audit. Returned article identifiers and titles were inspected, rather
   than accepting numeric shape. NCBI/Crossref evidence is reused from the prior
   live registry audit, begun **2026-09-23 23:20:35 UTC** (2026-09-24 local time);
   those request timestamps and hashes remain in the original artifact. The full
   current reference mapping matches the data for those registry checks.
6. All **12 nonnumeric values have DOI syntax**. Eleven have exact Crossref DOI
   metadata. The remaining DOI has new independent official-index evidence below.

Namespace verification is **not** verification that an article supports every
associated KG assertion. Title/context screening here does not constitute a
full-text review, expert review or scientific validation. A PubMed record's
existence alone is insufficient to override an AYUSH source match.

## Representative rows

| Reference | Current type | Proposed type | Independently verified? | Source/evidence | Conflict |
|---|---|---|---|---|---|
| 39210691 | PMID | PMID | Yes, exact PubMed record | [NCBI record](https://pubmed.ncbi.nlm.nih.gov/39210691/) | None for namespace; correction content not reviewed |
| 76448 | PMID | AYUSH | Yes, exact AYUSH Art ID | `76448(Ay)`, `ARP_AYU009603`, Raktagunja processing study | Current type wrong; also collides with a different PubMed paper |
| 239250 | PMID | AYUSH | Yes, exact AYUSH Art ID | Official AYUSH study of processed/unprocessed Strychnos seeds; full record in table | Current type wrong; also collides with PubMed |
| 216742 | PMID | AYUSH | Yes, exact AYUSH Art ID | Official AYUSH Churna Basti study | Current type wrong |
| 10.4103/joa.joa_262_22 | DOI | DOI | Yes, explicit DOI field in official AYUSH index | `258082(Ay)`, `ARP_AYU030291` | Registration/resolver lookup unresolved; not an invalid DOI finding |

## Bhallataka DOI: new namespace evidence

The official AYUSH index lists **10.4103/joa.joa_262_22 explicitly in its DOI
field**, attached to Art ID `258082(Ay)`, ARP ID `ARP_AYU030291`: the comparative
study of processed and unprocessed Bhallataka seeds, Journal of Ayurveda, 2024,
volume 18, issue 1, pages 44–50. The returned DOI is an exact match, not inferred
from the title or the user's statement.

[Saved official response evidence](./doi_source_evidence.json) includes the request
parameters, response hash and bibliographic fields. The earlier Crossref/doi.org
404 responses and publisher access failure remain historical lookup problems.
This new evidence supports **DOI namespace classification**, not successful
registration/resolution or verification of the study's scientific claims.

## Why the proposed rules should not be implemented as written

- **Eight or more digits → PMID:** all 251 current values are eight digits and have
  PubMed evidence. This is an observed dataset pattern, not a registry definition.
  A newly encountered eight-digit number could belong to another system or no
  registry. This dataset contains no nine-digit-or-longer test case.
- **Alphanumeric → DOI:** letters and numbers do not establish DOI syntax. DOI
  names have a prefix and suffix separated by `/`; they can have no alphabetic
  characters at all, as in the DOI Foundation's own `10.1000/182` example.
  Conversely, official AYUSH IDs such as `ARP_AYU009603` are alphanumeric and are
  not DOIs. See the [DOI Foundation's definition](https://www.doi.org/the-identifier/what-is-a-doi).
  For the proposed-count table, “alphanumeric” was interpreted generously as
  nonnumeric references containing letters **including punctuation**. Strict
  `[A-Za-z0-9]+` would not recognize any of the 12 stored DOIs; that literal
  interpretation would leave **12 unmatched values**, rather than zero.
- **Fewer than eight digits → AYUSH:** the 147 current values are supported by
  actual AYUSH records, not by their length. Of these, **143 also resolve as PMIDs**
  for other records. For example, [PMID 76448](https://pubmed.ncbi.nlm.nih.gov/76448/)
  exists, while this KG intends AYUSH Art ID 76448. Thus short valid PMIDs exist;
  the length rule would misclassify them if they were imported as PubMed references.

There are no seven-digit values in this KG. Numeric length distribution is 251
eight-digit, 94 six-digit, 45 five-digit and eight four-digit values. Assertions
about absent lengths or future references cannot be validated from this snapshot.

## Safe deterministic alternative

Use an explicit, provenance-backed mapping for these 410 references, retaining
the original `ref`, selected namespace, registry record and verification status.
For future references, prefer explicit imported namespace metadata and verified
registry/source mappings. DOI syntax may select a DOI candidate, not establish
registration. Numeric length may select lookup candidates, not finalize the type.
Preserve an unclassified/ambiguous state when evidence is missing or conflicting.
Do not select the first registry that returns a record.

**Decision: the split fits the current data, but the three rules are not safe
production rules as written. A reviewed mapping is safe to propose for this
snapshot; implementing it is a separate change.**

## Audit artifacts and checks

Only new files in this audit directory were created. `capture.php` performs the
read-only scan, `query_ayush.py` makes public read-only registry searches, and
`build_report.py` produces the tables. Outputs refuse existing destinations.
The build checks complete query coverage, exact NCBI/DOI identity, unchanged
RID-to-reference mappings and input hashes. No fixture regeneration or application
build is required for this audit. No correction-content or scientific evaluation
was performed.
