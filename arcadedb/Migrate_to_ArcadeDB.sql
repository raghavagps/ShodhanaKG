# =============================================================================
# Migrate_to_ArcadeDB.sql
# Create the ArcadeDB database "shodhana", import the Neo4j JSON export,
# and run sanity checks.
#
# RUN (from the ArcadeDB home directory, server must be running):
#     bin/console.sh Migrate_to_ArcadeDB.sql
# or open bin/console.sh and paste the commands one by one.
#
# Lines starting with '#' are comments. If your console version rejects them,
# delete the comment lines.
#
# Before running, replace <user> and <password> in the CONNECT line.
# =============================================================================


# -----------------------------------------------------------------------------
# STEP 1 : CONNECT TO THE ARCADEDB SERVER AND CREATE THE DATABASE
# -----------------------------------------------------------------------------
CONNECT remote:localhost <user> <password>;
CREATE DATABASE shodhana;

# Re-connect directly to the new database
DISCONNECT;
CONNECT remote:localhost/shodhana <user> <password>;


# -----------------------------------------------------------------------------
# STEP 2 : IMPORT THE NEO4J JSON EXPORT (file created by apoc.export.json.all)
# Neo4j labels become vertex types, relationship types become edge types.
# The path is on the machine where the ArcadeDB SERVER runs.
# -----------------------------------------------------------------------------
IMPORT DATABASE file:///data/graph_export/neo4j-export.json;


# =============================================================================
# STEP 3 : SANITY CHECKS AFTER MIGRATION
# Compare every result with the counts recorded in Neo4j before the export:
#   Neo4j:  MATCH (n) RETURN labels(n), count(*);
#           MATCH ()-[r]->() RETURN type(r), count(*);
# =============================================================================

# 3.1 Schema: all vertex and edge types created by the import
SELECT name, type FROM schema:types ORDER BY type, name;

# 3.2 Total vertices  (must equal total nodes in Neo4j)
SELECT count(*) AS total_vertices FROM V;

# 3.3 Total edges  (must equal total relationships in Neo4j)
SELECT count(*) AS total_edges FROM E;

# 3.4 Vertices per type  (must match the Neo4j label counts)
SELECT @type AS label, count(*) AS total FROM V GROUP BY @type ORDER BY label;

# 3.5 Edges per type  (must match the Neo4j relationship-type counts)
SELECT @type AS relationship, count(*) AS total FROM E GROUP BY @type ORDER BY relationship;

# 3.6 Edges per reference type  (PubMed / AYUSH / DOI)
SELECT ref_type, count(*) AS total FROM E GROUP BY ref_type;

# 3.7 Edges missing a reference  (should return 0)
SELECT count(*) AS edges_without_ref FROM E WHERE ref IS NULL OR ref = '';

# 3.8 Vertices missing a name  (should return 0)
SELECT count(*) AS vertices_without_name FROM V WHERE name IS NULL;

# 3.9 Property check: one Compound vertex with all its properties
SELECT FROM Compound LIMIT 1;

# 3.10 Relationship property check: edges that carry extra properties
SELECT FROM UNDERGOES LIMIT 5;
SELECT FROM USES_FORMULATION LIMIT 5;
SELECT FROM CONFIRMS_SAFETY_OF LIMIT 5;

# 3.11 Traversal check: Compound -> Shodhana_Method -> Purified_Compound
SELECT name, out('UNDERGOES').name AS shodhana_methods FROM Compound LIMIT 10;
SELECT name, out('PRODUCES').name AS purified_compounds FROM Shodhana_Method LIMIT 10;

# 3.12 Reference check: references supporting each Compound -> Method link
SELECT out.name AS compound, in.name AS method, ref, ref_type FROM UNDERGOES LIMIT 20;

# 3.13 Orphan vertices (no edges at all) - compare with the Neo4j orphan check
SELECT @type AS label, count(*) AS orphans FROM V WHERE both().size() = 0 GROUP BY @type;
