# Terminology coverage expansion ? 2026-09-25 v1

Implemented a source-backed expansion from 2 catalog-mapped KG nodes to **416 distinct nodes**. **357 nodes have alternate terms beyond their canonical labels.** All 2,534 canonical KG node identities remain available. No KG facts, node types, edges, evidence IDs or publication references were edited.

This adds coverage across eight node types. It does not establish universal terminology coverage; the remaining gaps are listed below and in the per-node audit.

## Sources and method

The pinned [NCI Thesaurus 26.08e release](https://evs.nci.nih.gov/ftp1/NCI_Thesaurus/Thesaurus_26.08e.FLAT.zip) supplies literal synonyms, semantic categories and stated parent relationships. Its [official release/format notes](https://evs.nci.nih.gov/ftp1/NCI_Thesaurus/ReadMe.txt) describe these fields. The archive hash and the exact source concepts used are retained locally. There are no runtime terminology downloads.

The catalog combines 238 KG-node-to-NCI mappings, stored alternate-name fields on 181 KG nodes, and 37 explicit parenthetical abbreviations whose initials match the stored label. These groups overlap. NCI mappings require a unique exact source-name/synonym match in a compatible semantic category. KG-listed alternate names and extracted abbreviations require confirmation. Short, qualified or uncertain NCI aliases also require confirmation.

The independent source checker verified **1,311 alias entries and 2,511 hierarchy-path bindings**, with no missing source evidence. These counts include duplicates and canonical-name entries; they are not counts of new distinct synonyms. Source checking is not domain-expert approval or scientific validation. NCI definitions are not imported into answers.

## Root causes addressed

| Finding | Change |
|---|---|
| External terminology covered only Cancer and ESCC | Reproducible offline scan and versioned catalog, with per-node coverage and rejected matches |
| Alternate-name support differed by node type; comma-separated names were treated as one string | Source-bound name-field entries across Compound, Media and Indication; individual listed names require confirmation |
| Parenthetical abbreviations in node labels were inaccessible | Recognize verified literal initialisms such as NMR, NDI and IBS, with confirmation |
| Alternate-name and assessment classification fields were treated as canonical labels | Canonical identity uses the actual node label; alternate names use catalog policy; classification fields are not node names |
| External aliases could compete with actual canonical labels | Canonical labels win within the explicitly requested type; all existing label/RID sets are preserved |
| Type qualifiers could lose a valid alias when another type had that canonical label | Apply explicit type qualification before canonical-versus-alias precedence |
| A known broader phrase could lose to a shorter contained node name or fuzzy guess | Match the complete broader phrase and preserve its source hierarchy; never retrieve a descendant automatically |
| The first expanded run dropped an unknown modifier in ?Zorblax Syndrome? | Preserve unresolved modifiers, suppress the broader suggestion, and prevent fuzzy matching of the discarded fragment |
| Spelling correction could bypass an alias confirmation policy | Uncertain aliases remain confirmation-only even when misspelled; unknown policy values fail closed |

Duplicate stored names remain distinct. For example, Vatsanabha identifies two Compound candidates, and Ardraka Swarasa appears on both Ginger Juice and Trikshara: Media records. These require selection/source review; the KG records were not corrected or merged.

## Coverage by KG type

?Mapped? includes either source terminology, stored name fields, or an explicit abbreviation. A mapped node may only gain broader-term discoverability rather than a new synonym.

| Node type | Total nodes | Mapped nodes | NCI-linked nodes | Stored-name nodes | Abbreviation nodes |
|---|---:|---:|---:|---:|---:|
| Therapy | 139 | 0 | 0 | 0 | 0 |
| Media | 91 | 48 | 6 | 47 | 0 |
| Shodhana_Method | 12 | 0 | 0 | 0 | 0 |
| Process | 10 | 0 | 0 | 0 | 0 |
| Purified_Compound | 71 | 0 | 0 | 0 | 0 |
| Toxic_Substance | 57 | 11 | 11 | 0 | 0 |
| Compound | 52 | 51 | 5 | 51 | 0 |
| Disease | 134 | 57 | 57 | 0 | 0 |
| Formulation | 777 | 0 | 0 | 0 | 0 |
| Symptom | 393 | 86 | 86 | 0 | 0 |
| Dosha | 3 | 0 | 0 | 0 | 0 |
| Ayurvedic_Diagnosis | 107 | 0 | 0 | 0 | 0 |
| Assessment_Parameter | 279 | 23 | 11 | 0 | 12 |
| Confirmation_Study | 163 | 29 | 5 | 0 | 24 |
| Indication | 188 | 111 | 57 | 83 | 1 |
| Ayurvedic_Formulation | 56 | 0 | 0 | 0 | 0 |
| Shodhana_Type | 2 | 0 | 0 | 0 | 0 |

The complete NCI scan left 120 ambiguous source-concept matches and 103 incompatible-category matches unapproved; 2,073 nodes had no usable exact source match. Some of these still gain KG-listed alternate names or abbreviations. Overall, 2,118 nodes have no catalog mapping yet. Their canonical names and conservative typo handling remain usable.

The largest remaining gaps are formulation names and Ayurveda-specific therapies, diagnoses and purification terminology. Those need additional reviewed nomenclature sources. Descriptions, ingredients, translations or LLM guesses were not promoted into synonyms. The hierarchy expansion is bounded to four stated parent hops and does not claim exhaustive clinical category membership.

## Sample before/after behavior

The baseline responses were saved before switching the runtime catalog. Eight previously unmatched terms now have relevant candidates or broader-category choices in this sample.

| Input | Before | After |
|---|---|---|
| Lou Gehrig Disease | unmatched | resolved |
| Chronic Renal Insufficiency | unmatched | resolved |
| irritable colon | unmatched | resolved |
| Eczematous Dermatitis | unmatched | resolved |
| Tamra | resolved | ambiguous |
| Gomutra | resolved | ambiguous |
| Vatsanabha | ambiguous | ambiguous |
| Abhraka | ambiguous | ambiguous |
| Ardraka Swarasa | unmatched | ambiguous |
| Cardiovascular Disorder | unmatched | broader |
| Autoimmune Disorder | unmatched | broader |
| Skin Disorder | unmatched | broader |
| ESCC | resolved | resolved |
| Copper | resolved | resolved |
| zzzzunknown | unmatched | unmatched |

Examples of resolved aliases are Lou Gehrig Disease ? Amyotrophic Lateral Sclerosis and chronic renal insufficiency ? Chronic Kidney Disease. Broader categories such as cardiovascular disorder and autoimmune disorder offer specific existing KG entities. IBS offers separate Disease and Indication candidates.

The Gomutra baseline automatically corrected to the **Go Mutra Formulation** node. The expanded terminology instead asks for confirmation of the KG-listed **Cow Urine Media** name; explicit type qualification remains available. Tamra was already recognized, but is now correctly identified as a stored alternate name requiring confirmation.

## Validation

- **6,386 PHP assertions passed**: all 2,534 canonical identities, source aliases, type qualification, stale bindings, ambiguity, policy enforcement and hierarchy-target availability. Of 1,311 alias entries, 1,238 are exposed and 73 intentionally yield to canonical KG labels. All 2,511 hierarchy targets remain available.
- **39 live checks passed**: aliases, abbreviations, broad categories, unknown modifiers, JSON/SSE selection, canonical-answer/evidence equivalence, node-property fidelity and search in all three Explorer modes.
- Compiler policy test passed, including retired/ambiguous concepts, category mismatch, unsafe aliases and explicit abbreviations. Independent source verification found zero evidence errors.
- Existing resolver assertions (55) and node-property assertions (218) passed.
- Final versioned benchmark: **82/82 routing, 168/168 factual, 121/121 attribution, 24/24 presentation, 108/108 conversation**. Zero source changes during the run, zero KG drift and zero JSON/SSE parity failures. No false refusals or false acceptances in its labelled cases.
- Lint: zero errors, 19 existing warnings. Build passed. Frontend unit tests: **43/43**.
- Selected Chromium suite: **16 passed, 9 failed**. The terminology, entity-resolution, node-property and route-smoke tests passed; failures are the nine pre-existing Explorer mobile-overflow cases.

The first expanded benchmark is retained with its 106/108 conversation result, showing the unknown-modifier bug that was subsequently fixed. `benchmark_final` is the superseding stable-source run. This is not a claim that the entire application is ready for final paper evaluation: the mobile failures and scientific/source-review limitations remain.

### Evaluation contract change

The unchanged version-6 routing fixture scored 81/82 after the canonical-label correction. Version 7 changes only the Guggulu property-question expectation: **Guggulu is an actual Formulation label (#65:664)**, which takes precedence over a Compound's alternate name. Its missing chemical-formula property returns `no_data`. Explicit ?compound Guggulu? can still select the Compound alias. Original fixtures remain archived, and `contract_changes.json` records the exact change. Independent factual expectations were not altered.

### Timing

Final local full-body HTTP p50/p95: presentation JSON **625/1,031 ms**, conversation JSON **765/1,672 ms**, conversation SSE **781/1,515 ms**. These are sequential local measurements, not load tests or first-token latency; no performance improvement is claimed.

## Files and reproducibility

Runtime catalog: `api/core/terminology_catalog_v2.json`. The original v1 catalog is preserved.

All source archives, source verification, per-node coverage, baseline responses, compatibility change, live-test outputs and benchmark artifacts are in `api/tests/fixtures/terminology_expansion_2026_09_25_v1/`. `coverage.json` identifies every unmapped or ambiguous source match. `SOURCE.md` provides attribution. The final benchmark records source hashes, commands, reference hashes, drift and parity results.

[file_manifest.json](file_manifest.json) lists every implementation file and generated audit artifact with SHA-256 hashes. This report and manifest are additional documentation outputs. No git repository is available in the workspace.

Implementation files:

- `api/core/EntityResolution.php`
- `api/core/QuestionRouter.php`
- `api/core/terminology_catalog_v2.json`
- `api/tests/build_terminology_catalog.py`
- `api/tests/verify_terminology_sources.py`
- `api/tests/test_build_terminology_catalog.py`
- `api/tests/test_terminology_expansion.php`
- `api/tests/evaluate_terminology_expansion.py`
- `api/tests/run_current_benchmark.py`
- `api/tests/fixtures/chatbot_evaluation_cases_v7.json`
- `tests/terminology-expansion.spec.ts`
- `docs/chatbot/entity_resolution.md`
