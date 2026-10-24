# Retrieval Core Architecture

The `api/core` directory contains the foundational, LLM-free deterministic retrieval layer for querying the Shodhana Knowledge Graph. It provides modular components that resolve entities, traverse the graph safely, and normalize outputs.

## The Cross-Join Problem

A naive multi-hop graph traversal (e.g., `Compound -> UNDERGOES -> Shodhana_Method -> VALIDATED_BY -> Confirmation_Study`) in ArcadeDB inherently causes a **cross-join hallucination**. 

**Why?** The `Shodhana_Method` nodes (e.g., "Swedana") act as generic conceptual hubs. Swedana connects to hundreds of compounds and hundreds of studies. A blind traversal through the "Swedana" node will mix the studies validating *Croton tiglium Linn.* with studies validating completely unrelated compounds (like *Haratala*).

## Evidence Correlation

To reconstruct true factual paths, the Core implements **Evidence Correlation**. A graph path is only considered valid if the evidence reference keys along the edges structurally match.

### How Identity is Determined
The `EvidenceCorrelator::correlate()` function enforces strict identity rules:
1. **PubMed ID:** If both edges have a `pubmedId` (or legacy `ref`), they must match exactly.
2. **DOI:** If PubMed is missing but both have a `doi`, they must match exactly.
3. **Ayush ID:** If both have an `ayush_id`, they must match exactly.
4. **Strict Fallback:** If both edges completely lack references, they **CANNOT** be deterministically correlated. They are dropped to prevent the silent introduction of cross-joined hallucinations.

## Deduplication
Graph edges often duplicate properties for bidirectional links. `KnowledgeNormalizer::generateDeduplicationKey()` deterministically hashes the canonical node names and JSON-encoded properties of a retrieved fact to filter identical records while safely preserving distinct facts.

## In-Process Static Caching
To minimize latency and prevent redundant ArcadeDB SQL queries within the same request:
- **`GraphRead.php`**: Caches node property dictionaries, type indices, and schema lookups in static in-memory properties. Subsequent reads of identical entity nodes reuse in-memory structures without issuing additional network roundtrips.
- **`SemanticQuery.php`**: Caches parsed semantic schema definitions and allowed edge traversals in memory.
- **`bridge-stats.php`**: Applies bounded limits (`LIMIT 5000`) on integration bridge joins to prevent memory exhaustion and database lockups during extensive cross-domain traversals.

## Canonical Knowledge Object
The `KnowledgeNormalizer::formatCanonical()` wraps retrieved facts in a standard schema:
```json
{
  "status": "success",
  "entity": { "name": "...", "type": "Compound" },
  "path": ["Compound", "UNDERGOES", "Shodhana_Method", "..."],
  "results": [ ... deduplicated facts ... ]
}
```
This guarantees UI components can uniformly parse the graph context regardless of the depth or complexity of the underlying query.

## How to use the Retrieval Core

When building a new graph query endpoint:
1. Include the Core: `require_once __DIR__ . '/core/...';`
2. Resolve the starting node: `$node = EntityResolver::resolve('Compound', $name, $debug);`
3. Execute SQL traversals: `$edges = GraphRetriever::execute($sql, $params, $debug);`
4. Apply correlation in a loop: `if (!EvidenceCorrelator::correlate($propsA, $propsB)) continue;`
5. Map missing names: `$names = GraphRetriever::fetchNodeNames($rids, $debug, 'Type');`
6. Output: `KnowledgeNormalizer::formatCanonical(...)`
