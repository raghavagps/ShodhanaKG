> Current implementation and release requirements: [CURRENT_SYSTEM_STATUS.md](CURRENT_SYSTEM_STATUS.md). Older counts, LLM requirements and link behavior below describe historical versions.

# Reference classification

The chatbot and Explorer use `api/core/ReferenceClassifier.php` and the versioned
`reference_catalog_v1.json`. Classification identifies a namespace; it does not
establish scientific validity or support for a KG claim.

The 2026-09-24 audit supports 251 PMIDs, 147 AYUSH IDs and 12 DOIs on 10,123 edges.
Mappings are bound to exact stored reference strings and edge RIDs. New untyped
edges do not inherit a classification merely because their numbers match an
existing reference. Changed bindings and contradictory explicit identifiers are
flagged for review. Neither numeric length nor letters determine a namespace.

Explicit fields and recognized namespace prefixes/URLs are syntax-checked and
marked `declared`, not registry-verified. DOI casing and known wrappers are
normalized; numeric strings and original edge properties are preserved. AYUSH
Art ID and ARP aliases come only from reviewed source records. Unrelated PubMed
collision records remain in the historical audit, never in runtime aliases.

## API and display

Evidence identifiers and graph/provenance edges carry `references`, an array of
`raw`, `identifier`, `namespace`, `status`, `resolution_status`, `catalog_version`
and, for audited mappings, source/verification metadata. Namespace is `PMID`,
`DOI`, `AYUSH` or null. Status is `verified`, `declared`, `unclassified` or
`conflict`. Registry resolution is a separate property. The Bhallataka DOI stays
DOI with `unresolved` resolution; it is not declared invalid.

Existing `pubmedId` / `pubmed_id` fields now contain only PMIDs. AYUSH and DOI
use their own fields. Consumers that previously treated `pubmedId` as a generic
reference must use `references` or the generic `reference` field instead.
Provenance `uniquePubmedIds` remains PMID-only; `uniqueReferences` covers all
namespaces. Both provenance endpoints accept paired `referenceType` and
`referenceId` query parameters. The existing disease `pubmedId` filter is
PMID-only. Cross-namespace equal numbers cannot correlate evidence.

The UI trusts backend classification metadata. Old responses without it display
generic references without guessed registry links. AYUSH links open the official
search page with the identifier visible for searching. CSV/JSON graph exports
retain structured classification. No registry calls occur during answer creation.

## Review queue

Capture a fresh read-only snapshot, then create a new report:

```text
php api/tests/capture_expanded_snapshot.php NEW_SNAPSHOT.json
php api/tests/review_references.php NEW_SNAPSHOT.json NEW_REVIEW.json
```

The queue contains missing, unclassified, changed/conflicting and declared-only
references with exact edge records. A reviewer must check exact source identifiers
and bibliographic/KG context, especially numeric collisions. Preserve raw inputs,
source URLs, capture dates and evidence hashes in a new audit. Publish a new
catalog version only after reviewing its edge bindings and regression results.
The generator refuses to overwrite an existing catalog or historical fixture.
Runtime files are deployable together; no KG migration is required.

## Verification

`reference_classification.test.php` checks every audited edge and adversarial
namespace inputs. Version 3 factual fixtures apply only reviewed identifier
corrections to historical expectations; entities, counts and supporting paths
stay frozen. Python evaluators independently consult the audit, never the PHP
classifier. Fresh benchmark directories remain write-once. Earlier reports and
fixtures are retained, including unsuccessful development captures.

Publication merging, the hardcoded study-count fallback, correction-notice
review and scientific claim review are outside this change.
