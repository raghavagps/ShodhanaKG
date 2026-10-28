# Migrating the Shodhana Knowledge Graph to ArcadeDB

This guide covers moving the Neo4j knowledge graph (see the `README_Neo4j.md`) into **ArcadeDB**. It starts with creating the database `shodhana`, then imports the JSON exported from Neo4j, and ends with sanity checks.

---

## Repository contents

| File | Purpose |
|------|---------|
| `Migrate_to_ArcadeDB.sql` | Console script: creates the `shodhana` database, imports the JSON, runs sanity checks |
| `README_ArcadeDB.md` | This documentation |
| `neo4j-export.json` | JSON export from Neo4j (produced by `Export.Cypher`) |

---

## Overview of the process

```
Neo4j  --apoc.export.json.all-->  neo4j-export.json  --IMPORT DATABASE-->  ArcadeDB "shodhana"
                                                                                  |
                                                                          sanity checks
```

How Neo4j concepts map to ArcadeDB:

| Neo4j | ArcadeDB |
|-------|----------|
| Node | Vertex |
| Label (e.g. `Compound`) | Vertex type |
| Relationship | Edge |
| Relationship type (e.g. `UNDERGOES`) | Edge type |
| Properties (`ref`, `ref_type`, `Procedure`, ...) | Properties on the vertex or edge |

---

## Prerequisites

- ArcadeDB installed and the **server running**
- A user with permission to create databases (for example `root`)
- `neo4j-export.json` created in Neo4j with `CALL apoc.export.json.all("neo4j-export.json", {});`
- The JSON file placed on the machine where the **ArcadeDB server** runs. 

```
data\graph_export\neo4j-export.json
```

- The ArcadeDB server process must be able to read that file.

Before migrating, note these Neo4j counts. You will compare them afterwards:

```cypher
MATCH (n) RETURN labels(n) AS label, count(*) AS total;
MATCH ()-[r]->() RETURN type(r) AS relationship, count(*) AS total;
MATCH (n) RETURN count(n);
MATCH ()-[r]->() RETURN count(r);
```

---

## Step 1: Start the ArcadeDB server

From the ArcadeDB home directory:

```bash
bin/server.sh
```

Wait for the server to report it is started. The Studio web UI is then at `http://localhost:2480`.

## Step 2: Open the ArcadeDB console

```bash
bin/console.sh
```

## Step 3: Connect to the server

```sql
CONNECT remote:localhost <user> <password>;
```

Replace `<user>` and `<password>` with your ArcadeDB credentials.

## Step 4: Create the database `shodhana`

```sql
CREATE DATABASE shodhana;
```

Then reconnect directly to the new database:

```sql
DISCONNECT;
CONNECT remote:localhost/shodhana <user> <password>;
```

Alternatively, create it in Studio: open `http://localhost:2480`, choose **Create new database**, enter `shodhana`, and open it.

## Step 5: Import the Neo4j JSON

```sql
IMPORT DATABASE file:///data/graph_export/neo4j-export.json;
```

The importer reads the file written by `apoc.export.json.all` and creates:
- one **vertex type** per Neo4j label,
- one **edge type** per Neo4j relationship type,
- all node and relationship properties, including `ref` and `ref_type`.

## Step 6: Sanity checks after migration

Run each query and compare the result with the Neo4j counts recorded earlier.

**Schema: all types created**

```sql
SELECT name, type FROM schema:types ORDER BY type, name;
```

You should see the 17 vertex types (`Compound`, `Toxic_Substance`, `Shodhana_Method`, `Shodhana_Type`, `Media`, `Purified_Compound`, `Confirmation_Study`, `Indication`, `Ayurvedic_Formulation`, `Process`, `Assessment_Parameter`, `Therapy`, `Formulation`, `Disease`, `Ayurvedic_Diagnosis`, `Dosha`, `Symptom`) and the edge types from the relationship tables in the main README.

**Totals**

```sql
SELECT count(*) AS total_vertices FROM V;
SELECT count(*) AS total_edges FROM E;
```

**Counts per type**

```sql
SELECT @type AS label, count(*) AS total FROM V GROUP BY @type ORDER BY label;
SELECT @type AS relationship, count(*) AS total FROM E GROUP BY @type ORDER BY relationship;
```

**Counts per reference type**

```sql
SELECT ref_type, count(*) AS total FROM E GROUP BY ref_type;
```

**Data quality checks (both should return 0)**

```sql
SELECT count(*) AS edges_without_ref FROM E WHERE ref IS NULL OR ref = '';
SELECT count(*) AS vertices_without_name FROM V WHERE name IS NULL;
```

**Property checks**

```sql
SELECT FROM Compound LIMIT 1;
SELECT FROM UNDERGOES LIMIT 5;
SELECT FROM USES_FORMULATION LIMIT 5;
SELECT FROM CONFIRMS_SAFETY_OF LIMIT 5;
```

Check that vertex properties such as `Ayurvedic_Name` and edge properties such as `Procedure`, `Dose` and `Result` survived the import.

**Traversal and reference checks**

```sql
SELECT name, out('UNDERGOES').name AS shodhana_methods FROM Compound LIMIT 10;
SELECT name, out('PRODUCES').name AS purified_compounds FROM Shodhana_Method LIMIT 10;
SELECT out.name AS compound, in.name AS method, ref, ref_type FROM UNDERGOES LIMIT 20;
```

**Orphan check**

```sql
SELECT @type AS label, count(*) AS orphans FROM V WHERE both().size() = 0 GROUP BY @type;
```

Compare with the Neo4j orphan check: `MATCH (n) WHERE NOT (n)--() RETURN labels(n), count(*);`

### Sanity check checklist

| Check | Expected result |
|-------|-----------------|
| Total vertices | Equal to the Neo4j node count |
| Total edges | Equal to the Neo4j relationship count |
| Vertices per type | Same as the Neo4j count per label |
| Edges per type | Same as the Neo4j count per relationship type |
| Edges per `ref_type` | PubMed, AYUSH and DOI counts match Neo4j |
| Edges without `ref` | 0 |
| Vertices without `name` | 0 |
| Sample vertex and edge properties | Present and with the right values |
| Traversals | Return the same targets as the Neo4j queries |

If any count differs, do not use the database yet. Check the import log, then see the troubleshooting table below.

---

## Run everything with one script

```bash
bin/console.sh Migrate_to_ArcadeDB.sql
```

Edit `<user>` and `<password>` in the script first. The script does Steps 3 to 6 in order. Lines starting with `#` are comments; if your console version rejects them, remove those lines.

---

## Troubleshooting

| Problem | Likely cause and fix |
|---------|----------------------|
| `CREATE DATABASE` says the database already exists | Connect to it instead, or drop it first with `DROP DATABASE shodhana;` if you want a clean import |
| Import cannot find the file | The path is read on the **server** machine. Check the path and that the server user can read it |
| Vertex or edge counts lower than Neo4j | Compare per-type counts (3.4 and 3.5) to find which type lost data, and check the import log |
| `ref` or `ref_type` missing on edges | Confirm they exist in Neo4j and in the exported JSON, and re-export |
| A query in Step 6 gives a syntax error | Query syntax can differ slightly between ArcadeDB versions. Check your version's SQL reference |
| Re-running the import duplicates data | Use `DROP DATABASE shodhana;` then repeat from Step 4 for a clean import |

---

## Notes

- The Neo4j uniqueness constraints are not part of the JSON export. If you want uniqueness on `name` in ArcadeDB, create an index on each vertex type after the import, for example `CREATE INDEX ON Compound (name) UNIQUE;`.
- Keep `neo4j-export.json` with the repository as the source of truth for the migration.
