// =============================================================================
// Export.Cypher
// Export the complete Knowledge Graph (nodes + relationships + properties)
// to JSON so it can be migrated to another database (e.g. ArcadeDB).
//
// PREREQUISITES
//   1. APOC plugin installed (APOC Core is enough for apoc.export.json.*).
//   2. File export enabled in  <neo4j>/conf/apoc.conf :
//          apoc.export.file.enabled=true
//      (Restart Neo4j after changing it.)
//   3. Exported files are written to the Neo4j <import> directory
//      (unless you changed server.directories.import / dbms.directories.import).
// =============================================================================


// -----------------------------------------------------------------------------
// STEP 1 : PRE-EXPORT SANITY CHECK  (counts you should see again after migration)
// -----------------------------------------------------------------------------
MATCH (n) RETURN labels(n) AS label, count(*) AS total ORDER BY label;
MATCH ()-[r]->() RETURN type(r) AS relationship, count(*) AS total ORDER BY relationship;


// -----------------------------------------------------------------------------
// STEP 2 : EXPORT THE ENTIRE GRAPH  (main command)
// Output : <import>/neo4j-export.json   (JSON Lines: one node / relationship per line)
// -----------------------------------------------------------------------------
CALL apoc.export.json.all("neo4j-export.json", {});


// -----------------------------------------------------------------------------
// STEP 3 : VERIFY THE EXPORT
// apoc.export.json.all returns file, nodes, relationships, properties, time...
// The nodes / relationships figures must match the counts from STEP 1.
// -----------------------------------------------------------------------------
MATCH (n) RETURN count(n) AS total_nodes;
MATCH ()-[r]->() RETURN count(r) AS total_relationships;
