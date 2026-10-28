# Ayurvedic Shodhana & Disease Knowledge Graph (Neo4j)

A Neo4j knowledge graph built with Cypher from CSV files. It links **Ayurvedic compounds, Shodhana (purification) methods, processes, media, purified compounds, formulations, diseases, therapies, symptoms, doshas and assessment parameters**. Every relationship stores the **literature reference** that supports it: a **PubMed ID**, an **AYUSH ID** or a **DOI**.

The source CSV files are provided in 'data/csv/nodes/' and 'data/csv/relations/'

The graph can be exported as JSON for migration to another database (for example **ArcadeDB**).

---

## Repository contents

| File | Purpose |
|------|---------|
| `Create_graph.Cypher` | Creates constraints, loads all nodes and relationships (PubMed, AYUSH, DOI references) and runs verification queries |
| `Export.Cypher` | Exports the graph (nodes, relationships, properties) and schema to JSON |
| `README_Neo4j.md` | This documentation |

---

## Prerequisites

- Neo4j latest (Desktop or Server)
- **APOC** plugin (needed only for export)
- Access to the Neo4j **`import`** folder
- `cypher-shell` (optional, for running scripts from the terminal)

---

## Graph model

### Nodes

**Compound Shodhana side** (source: `Compound_Nodes.csv`)

| Label | Key property | Other properties |
|-------|--------------|------------------|
| `Compound` | `name` | Ayurvedic_Name, Common_Name, Compound_Type, Source, Adverse_Effect, Pharmacological_Activity, Chemical_Composition |
| `Toxic_Substance` | `name` | – |
| `Shodhana_Method` | `name` | Method_Description |
| `Shodhana_Type` | `name` | Description |
| `Media` | `name` | Sanskrit_Name, Preparation, Media_Type |
| `Purified_Compound` | `name` | – |
| `Confirmation_Study` | `name` | – |
| `Indication` | `name` | Ayurvedic_Name |
| `Ayurvedic_Formulation` | `name` | – |
| `Process` | `name` | Process_Description |

**Disease Shodhana side** (source: `disease_nodes.csv`)

| Label | Key property | Other properties |
|-------|--------------|------------------|
| `Assessment_Parameter` | `name` | Description, Type |
| `Therapy` | `name` | Description |
| `Formulation` | `name` | Description |
| `Disease` | `name` | Description |
| `Ayurvedic_Diagnosis` | `name` | Description |
| `Dosha` | `name` | Description |
| `Symptom` | `name` | – |

### Relationships

Every relationship has `ref` (the reference ID) and `ref_type` (`PubMed`, `AYUSH` or `DOI`).

**Compound Shodhana side**

| Relationship | Pattern | Extra properties |
|--------------|---------|------------------|
| `UNDERGOES` | `(Compound)->(Shodhana_Method)` | Procedure |
| `CONTAINS` | `(Compound)->(Toxic_Substance)` | – |
| `DERIVED_FROM` | `(Purified_Compound)->(Compound)` | – |
| `TREATS` | `(Purified_Compound)->(Indication)` | – |
| `PRODUCES` | `(Process)->(Purified_Compound)` and `(Shodhana_Method)->(Purified_Compound)` | – |
| `CONFIRMS_SAFETY_OF` | `(Confirmation_Study)->(Purified_Compound)` | Result |
| `USES_MEDIA` | `(Shodhana_Method)->(Media)` and `(Process)->(Media)` | Procedure |
| `VALIDATED_BY` | `(Shodhana_Method)->(Confirmation_Study)` and `(Process)->(Confirmation_Study)` | – |
| `DETOXIFIES` | `(Shodhana_Method)->(Toxic_Substance)` | – |
| `CLASSIFIED_AS` | `(Shodhana_Method)->(Shodhana_Type)` | Media_Used |
| `USED_IN_FORMULATION` | `(Purified_Compound)->(Ayurvedic_Formulation)` | – |
| `FOLLOWED_BY` | `(Shodhana_Method)->(Process)` and `(Process)->(Process)` | – |

**Disease Shodhana side**

| Relationship | Pattern | Extra properties |
|--------------|---------|------------------|
| `ASSESS` | `(Assessment_Parameter)->(Disease)` | Outcome_Measure |
| `USES_FORMULATION` | `(Therapy)->(Formulation)` | Adjuvant, Dose, Duration |
| `TREATED_BY` | `(Disease)->(Therapy)` | – |
| `PRE_TREATED_BY` | `(Disease)->(Therapy)` | – |
| `POST_TREATED_BY` | `(Disease)->(Therapy)` | – |
| `HAS_SYMPTOM` | `(Disease)->(Symptom)` | – |
| `HAS_DOSHA_IMBALANCE` | `(Disease)->(Dosha)` | – |
| `HAS_AYURVEDIC_DIAGNOSIS` | `(Disease)->(Ayurvedic_Diagnosis)` | – |
| `USED_FOR_TREATMENT` | `(Purified_Compound)->(Disease)` | – |

### How references work

Each relationship CSV has one reference column, and its value is stored on the relationship:

| Reference type | CSV column | `ref_type` value |
|----------------|-----------|------------------|
| PubMed | `Pubmed_ID` | `PubMed` |
| AYUSH | `Ayush_ID` | `AYUSH` |
| DOI | `DOI` | `DOI` |

`ref` is part of the `MERGE` key, so the same two nodes can be connected by several relationships, one per supporting paper, without duplicates.

---

## Step-by-step: building the knowledge graph

### Step 1: Prepare the CSV files

Copy all CSVs into the Neo4j **import** folder.

**Node files**
- `Compound_Nodes.csv`: all compound Shodhana nodes
- `disease_nodes.csv`: all disease Shodhana nodes

**Relationship files (PubMed_ID)**

`Comp_Sho_Meth.csv`, `Comp_Toxic.csv`, `Purified_Comp.csv`, `Purified_Indication.csv`, `Confirm_Purified.csv`, `Sho_Meth_Media.csv`, `Sho_Meth_Confirm.csv`, `Sho_Meth_Toxic.csv`, `Sho_Meth_Sho_Type.csv`, `Sho_Meth_Purified.csv`, `Purified_For.csv`, `Sho_Meth_Process.csv`, `Process_Media.csv`, `Process_Confirm.csv`, `Process_Process.csv`, `Purified_Disease.csv`, `Assessment-Disease(Pubmed).csv`, `Therapy-Formulation(Pubmed).csv`, `Treated_by(Pubmed).csv`, `Pre-treated_by(Pubmed).csv`, `Post-treated_by(Pubmed).csv`, `Disease-Symptoms(Pubmed).csv`, `Disease-Dosha(Pubmed).csv`, `Disease-diagnosis(Pubmed).csv`

**Relationship files (Ayush_ID and DOI)**

The same relationship files, one set per reference type, named with a suffix:
- Compound-side: `<name>_Ayush.csv` and `<name>_DOI.csv` (for example `Comp_Sho_Meth_DOI.csv`)
- Disease-side: `<name>(Ayush).csv` (for example `Treated_by(Ayush).csv`)

### Step 2: Create constraints

A uniqueness constraint on `name` for each label makes `MERGE` and `MATCH` fast and prevents duplicate nodes. This is done first in the script.

```cypher
CREATE CONSTRAINT compound_name_unique IF NOT EXISTS
FOR (n:Compound) REQUIRE n.name IS UNIQUE;
```

### Step 3: Load the nodes

Each node type is loaded from the CSV with `MERGE`, skipping blank cells and trimming whitespace.

```cypher
LOAD CSV WITH HEADERS FROM 'file:///Compound_Nodes.csv' AS row
WITH row WHERE trim(row.Compound) <> ""
MERGE (c:Compound {name: trim(row.Compound)})
SET c.Ayurvedic_Name = trim(row.Ayurvedic_Name),
    c.Common_Name    = trim(row.Common_Name);   // ...and the other properties
```

`Compound_Nodes.csv` is read first, then `Disease_Nodes.csv` (see the label table above).

### Step 4: Verify the nodes

```cypher
MATCH (n) RETURN labels(n), count(*);
MATCH (m:Media) RETURN m;      // view all nodes of one label
```

You should see one count per label.

### Step 5: Load relationships with the PubMed_ID reference

Each relationship CSV is loaded by matching the two end nodes by `name` and merging the relationship with the reference stored in `ref`:

```cypher
LOAD CSV WITH HEADERS FROM 'file:///Comp_Sho_Meth.csv' AS row
MATCH (a:Compound {name: trim(row.Compound)})
MATCH (b:Shodhana_Method {name: trim(row.Shodhana_Method)})
MERGE (a)-[r:UNDERGOES {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed',
    r.Procedure = trim(row.Procedure);
```

### Step 6: Load relationships with the Ayush_ID reference

Same pattern, using the `Ayush_ID` column:

```cypher
LOAD CSV WITH HEADERS FROM 'file:///Comp_Sho_Meth_Ayush.csv' AS row
MATCH (a:Compound {name: trim(row.Compound)})
MATCH (b:Shodhana_Method {name: trim(row.Shodhana_Method)})
MERGE (a)-[r:UNDERGOES {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH',
    r.Procedure = trim(row.Procedure);
```

### Step 7: Load relationships with the DOI reference

Same pattern, using the `DOI` column:

```cypher
LOAD CSV WITH HEADERS FROM 'file:///Comp_Sho_Meth_DOI.csv' AS row
MATCH (a:Compound {name: trim(row.Compound)})
MATCH (b:Shodhana_Method {name: trim(row.Shodhana_Method)})
MERGE (a)-[r:UNDERGOES {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI',
    r.Procedure = trim(row.Procedure);
```

### Step 8: Verify the relationships

```cypher
// Counts per relationship type and reference type
MATCH ()-[r]->() RETURN type(r), r.ref_type, count(*);

// Visual check
MATCH (c:Compound)-[r:UNDERGOES]->(s:Shodhana_Method) RETURN c, r, s;
MATCH (c:Compound)-[r:CONTAINS]->(s:Toxic_Substance) RETURN c, r, s;

// Relationships with no reference (should return nothing)
MATCH ()-[r]->() WHERE r.ref IS NULL OR r.ref = "" RETURN type(r), count(*);
```

---

## Running everything at once

```bash
cat Create_graph.Cypher | cypher-shell -u <user> -p <password> -d <database>
```

Or open `Create_graph.Cypher` in Neo4j Browser and run it section by section. The script uses `MERGE` throughout, so it is safe to re-run.

---

## Example queries

```cypher
// How is a compound purified, and which paper supports it?
MATCH (c:Compound)-[r:UNDERGOES]->(m:Shodhana_Method)
RETURN c.name, m.name, r.ref_type, r.ref;

// Toxic substances removed by each Shodhana method
MATCH (m:Shodhana_Method)-[r:DETOXIFIES]->(t:Toxic_Substance)
RETURN m.name, t.name, r.ref;

// Therapies for a disease with formulation, dose and duration
MATCH (d:Disease)-[:TREATED_BY]->(t:Therapy)-[u:USES_FORMULATION]->(f:Formulation)
RETURN d.name, t.name, f.name, u.Dose, u.Duration, u.ref;

// Full path: compound -> method -> purified compound -> disease
MATCH p = (c:Compound)-[:UNDERGOES]->(:Shodhana_Method)-[:PRODUCES]->(pc:Purified_Compound)-[:USED_FOR_TREATMENT]->(d:Disease)
RETURN p LIMIT 25;
```

---

## Exporting the graph to JSON (for migration)

### Step 1: Enable file export

Add this line to `<neo4j>/conf/apoc.conf` and restart Neo4j:

```
apoc.export.file.enabled=true
```

### Step 2: Run the export

```cypher
CALL apoc.export.json.all("neo4j-export.json", {});
```

This writes `neo4j-export.json` into the Neo4j **import** folder. The file is in JSON Lines format, one node or relationship per line, with all labels, types and properties (including `ref` and `ref_type`).

### Step 3: Verify the export

The procedure returns the number of exported nodes, relationships and properties. Compare them with:

```cypher
MATCH (n) RETURN count(n);
MATCH ()-[r]->() RETURN count(r);
```

## Notes and troubleshooting

| Problem | Fix |
|---------|-----|
| `Couldn't load the external resource at file:/...` | The CSV is not in the Neo4j `import` folder, or the name or case does not match |
| Relationships not created (0 rows) | `name` values in the relationship CSV differ from the node CSV (spelling, spaces, case) |
| `apoc.export.json.all` fails with "export to files not enabled" | Set `apoc.export.file.enabled=true` in `apoc.conf` and restart |
| Duplicate relationships | Check that `ref` values are consistent. The same node pair with a different `ref` creates a separate relationship by design |
| Column header not found | Relationship CSV headers must exactly match the names used in the script (for example `Process_1` in `Process_Process.csv`) |

---
