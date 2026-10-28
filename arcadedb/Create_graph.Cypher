// =============================================================================
// Create_graph.Cypher
// Builds the Ayurvedic Compound / Disease Shodhana Knowledge Graph in Neo4j
//
// HOW TO RUN
//   1. Copy ALL CSV files into the Neo4j <import> directory.
//   2. Run the whole file in order (top to bottom), e.g.
//        cat Create_graph.Cypher | cypher-shell -u <user> -p <password>
//      or paste section by section in Neo4j Browser.
//   3. Statements are idempotent (MERGE) - safe to re-run.
//
// ORDER OF EXECUTION  (must be respected)
//   STEP 0  Constraints & indexes
//   STEP 1  Compound-side nodes      (Compound_Nodes.csv)
//   STEP 2  Disease-side nodes       (disease_nodes.csv)
//   STEP 3  Verify nodes
//   STEP 4  Relationships - PubMed_ID reference
//   STEP 5  Relationships - Ayush_ID reference
//   STEP 6  Relationships - DOI reference
//   STEP 7  Verification queries
//
// Every relationship carries:
//   r.ref       -> the reference identifier (PubMed_ID / Ayush_ID / DOI)
//   r.ref_type  -> 'PubMed' | 'AYUSH' | 'DOI'   (tells you which kind of ID r.ref is)
// Because `ref` is part of the MERGE key, the same pair of nodes can be linked
// by several references (one relationship per paper) without duplication.
// =============================================================================


// =============================================================================
// STEP 0 : CONSTRAINTS & INDEXES  (do this BEFORE loading - speeds up MERGE/MATCH)
// =============================================================================
CREATE CONSTRAINT compound_name_unique IF NOT EXISTS FOR (n:Compound) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT toxic_substance_name_unique IF NOT EXISTS FOR (n:Toxic_Substance) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT shodhana_method_name_unique IF NOT EXISTS FOR (n:Shodhana_Method) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT shodhana_type_name_unique IF NOT EXISTS FOR (n:Shodhana_Type) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT media_name_unique IF NOT EXISTS FOR (n:Media) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT purified_compound_name_unique IF NOT EXISTS FOR (n:Purified_Compound) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT confirmation_study_name_unique IF NOT EXISTS FOR (n:Confirmation_Study) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT indication_name_unique IF NOT EXISTS FOR (n:Indication) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT ayurvedic_formulation_name_unique IF NOT EXISTS FOR (n:Ayurvedic_Formulation) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT process_name_unique IF NOT EXISTS FOR (n:Process) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT assessment_parameter_name_unique IF NOT EXISTS FOR (n:Assessment_Parameter) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT therapy_name_unique IF NOT EXISTS FOR (n:Therapy) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT formulation_name_unique IF NOT EXISTS FOR (n:Formulation) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT disease_name_unique IF NOT EXISTS FOR (n:Disease) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT ayurvedic_diagnosis_name_unique IF NOT EXISTS FOR (n:Ayurvedic_Diagnosis) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT dosha_name_unique IF NOT EXISTS FOR (n:Dosha) REQUIRE n.name IS UNIQUE;
CREATE CONSTRAINT symptom_name_unique IF NOT EXISTS FOR (n:Symptom) REQUIRE n.name IS UNIQUE;

// =============================================================================
// STEP 1 : COMPOUND / SHODHANA NODES        source: import/Compound_Nodes.csv
// (each block reads the same CSV and MERGEs one node type; blank cells skipped)
// =============================================================================

// ---- Compound ----
LOAD CSV WITH HEADERS FROM 'file:///Compound_Nodes.csv' AS row
WITH row WHERE trim(row.Compound) <> ""
MERGE (c:Compound {name: trim(row.Compound)})
SET c.Ayurvedic_Name            = trim(row.Ayurvedic_Name),
    c.Common_Name               = trim(row.Common_Name),
    c.Compound_Type             = trim(row.Compound_Type),
    c.Source                    = trim(row.Source),
    c.Adverse_Effect            = trim(row.Adverse_Effect),
    c.Pharmacological_Activity  = trim(row.Pharmacological_Activity),
    c.Chemical_Composition      = trim(row.Chemical_Composition);

// ---- Toxic_Substance ----
LOAD CSV WITH HEADERS FROM 'file:///Compound_Nodes.csv' AS row
WITH row WHERE trim(row.Toxic_Substance) <> ""
MERGE (t:Toxic_Substance {name: trim(row.Toxic_Substance)});

// ---- Shodhana_Method ----
LOAD CSV WITH HEADERS FROM 'file:///Compound_Nodes.csv' AS row
WITH row WHERE trim(row.Shodhana_Method) <> ""
MERGE (m:Shodhana_Method {name: trim(row.Shodhana_Method)})
SET m.Method_Description = trim(row.Method_Description);

// ---- Shodhana_Type ----
LOAD CSV WITH HEADERS FROM 'file:///Compound_Nodes.csv' AS row
WITH row WHERE trim(row.Shodhana_Type) <> ""
MERGE (st:Shodhana_Type {name: trim(row.Shodhana_Type)})
SET st.Description = trim(row.Description);

// ---- Media ----
LOAD CSV WITH HEADERS FROM 'file:///Compound_Nodes.csv' AS row
WITH row WHERE trim(row.Media) <> ""
MERGE (me:Media {name: trim(row.Media)})
SET me.Sanskrit_Name = trim(row.Sanskrit_Name),
    me.Preparation   = trim(row.Preparation),
    me.Media_Type    = trim(row.Media_Type);

// ---- Purified_Compound ----
LOAD CSV WITH HEADERS FROM 'file:///Compound_Nodes.csv' AS row
WITH row WHERE trim(row.Purified_Compound) <> ""
MERGE (p:Purified_Compound {name: trim(row.Purified_Compound)});

// ---- Confirmation_Study ----
LOAD CSV WITH HEADERS FROM 'file:///Compound_Nodes.csv' AS row
WITH row WHERE trim(row.Confirmation_Study) <> ""
MERGE (co:Confirmation_Study {name: trim(row.Confirmation_Study)});

// ---- Indication ----
LOAD CSV WITH HEADERS FROM 'file:///Compound_Nodes.csv' AS row
WITH row WHERE trim(row.Indication) <> ""
MERGE (i:Indication {name: trim(row.Indication)})
SET i.Ayurvedic_Name = trim(row.Ayurvedic_Name);

// ---- Ayurvedic_Formulation ----
LOAD CSV WITH HEADERS FROM 'file:///Compound_Nodes.csv' AS row
WITH row WHERE trim(row.Ayurvedic_Formulation) <> ""
MERGE (f:Ayurvedic_Formulation {name: trim(row.Ayurvedic_Formulation)});

// ---- Process ----
LOAD CSV WITH HEADERS FROM 'file:///Compound_Nodes.csv' AS row
WITH row WHERE trim(row.Process) <> ""
MERGE (p:Process {name: trim(row.Process)})
SET p.Process_Description = trim(row.Process_Description);


// =============================================================================
// STEP 2 : DISEASE NODES                    source: import/disease_nodes.csv
// (each block reads the same CSV and MERGEs one node type; blank cells skipped)
// =============================================================================

// ---- Assessment_Parameter ----
LOAD CSV WITH HEADERS FROM 'file:///disease_nodes.csv' AS row
WITH row WHERE trim(row.Assessment_Parameter) <> ""
MERGE (p:Assessment_Parameter {name: trim(row.Assessment_Parameter)})
SET p.Description = trim(row.Description_3),
    p.Type        = trim(row.Type);

// ---- Therapy ----
LOAD CSV WITH HEADERS FROM 'file:///disease_nodes.csv' AS row
WITH row WHERE trim(row.Therapy) <> ""
MERGE (p:Therapy {name: trim(row.Therapy)})
SET p.Description = trim(row.Therapy_Description);

// ---- Formulation ----
LOAD CSV WITH HEADERS FROM 'file:///disease_nodes.csv' AS row
WITH row WHERE trim(row.Formulation) <> ""
MERGE (p:Formulation {name: trim(row.Formulation)})
SET p.Description = trim(row.Formulation_Description);

// ---- Disease ----
LOAD CSV WITH HEADERS FROM 'file:///disease_nodes.csv' AS row
WITH row WHERE trim(row.Disease) <> ""
MERGE (i:Disease {name: trim(row.Disease)})
SET i.Description = trim(row.Description);

// ---- Ayurvedic_Diagnosis ----
LOAD CSV WITH HEADERS FROM 'file:///disease_nodes.csv' AS row
WITH row WHERE trim(row.Ayurvedic_Diagnosis) <> ""
MERGE (p:Ayurvedic_Diagnosis {name: trim(row.Ayurvedic_Diagnosis)})
SET p.Description = trim(row.Description_1);

// ---- Dosha ----
LOAD CSV WITH HEADERS FROM 'file:///disease_nodes.csv' AS row
WITH row WHERE trim(row.Dosha) <> ""
MERGE (p:Dosha {name: trim(row.Dosha)})
SET p.Description = trim(row.Description_2);

// ---- Symptom ----
LOAD CSV WITH HEADERS FROM 'file:///disease_nodes.csv' AS row
WITH row WHERE trim(row.Symptom) <> ""
MERGE (p:Symptom {name: trim(row.Symptom)});


// =============================================================================
// STEP 3 : VERIFY NODES - expect one row per label with its count
// =============================================================================
MATCH (n) RETURN labels(n) AS label, count(*) AS total ORDER BY label;

// Inspect all nodes of one label, e.g. Media
MATCH (m:Media) RETURN m;


// =============================================================================
// STEP 4 : RELATIONSHIPS - PubMed_ID reference
// Reference column in each CSV : Pubmed_ID   ->  stored as r.ref, r.ref_type = 'PubMed'
// CSV names follow the original files (e.g. Comp_Sho_Meth.csv, Treated_by(Pubmed).csv).
// =============================================================================

LOAD CSV WITH HEADERS FROM 'file:///Comp_Sho_Meth.csv' AS row
MATCH (a:Compound {name: trim(row.Compound)})
MATCH (b:Shodhana_Method {name: trim(row.Shodhana_Method)})
MERGE (a)-[r:UNDERGOES {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed',
    r.Procedure = trim(row.Procedure);

LOAD CSV WITH HEADERS FROM 'file:///Comp_Toxic.csv' AS row
MATCH (a:Compound {name: trim(row.Compound)})
MATCH (b:Toxic_Substance {name: trim(row.Toxic_Substance)})
MERGE (a)-[r:CONTAINS {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Purified_Comp.csv' AS row
MATCH (a:Purified_Compound {name: trim(row.Purified_Compound)})
MATCH (b:Compound {name: trim(row.Compound)})
MERGE (a)-[r:DERIVED_FROM {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Purified_Indication.csv' AS row
MATCH (a:Purified_Compound {name: trim(row.Purified_Compound)})
MATCH (b:Indication {name: trim(row.Indication)})
MERGE (a)-[r:TREATS {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Confirm_Purified.csv' AS row
MATCH (a:Confirmation_Study {name: trim(row.Confirmation_Study)})
MATCH (b:Purified_Compound {name: trim(row.Purified_Compound)})
MERGE (a)-[r:CONFIRMS_SAFETY_OF {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed',
    r.Result = trim(row.Result);

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Media.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Media {name: trim(row.Media)})
MERGE (a)-[r:USES_MEDIA {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed',
    r.Procedure = trim(row.Procedure);

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Confirm.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Confirmation_Study {name: trim(row.Confirmation_Study)})
MERGE (a)-[r:VALIDATED_BY {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Toxic.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Toxic_Substance {name: trim(row.Toxic_Substance)})
MERGE (a)-[r:DETOXIFIES {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Sho_Type.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Shodhana_Type {name: trim(row.Shodhana_Type)})
MERGE (a)-[r:CLASSIFIED_AS {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed',
    r.Media_Used = trim(row.Media_Used);

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Purified.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Purified_Compound {name: trim(row.Purified_Compound)})
MERGE (a)-[r:PRODUCES {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Purified_For.csv' AS row
MATCH (a:Purified_Compound {name: trim(row.Purified_Compound)})
MATCH (b:Ayurvedic_Formulation {name: trim(row.Ayurvedic_Formulation)})
MERGE (a)-[r:USED_IN_FORMULATION {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Process.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Process {name: trim(row.Process)})
MERGE (a)-[r:FOLLOWED_BY {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Process_Media.csv' AS row
MATCH (a:Process {name: trim(row.Process)})
MATCH (b:Media {name: trim(row.Media)})
MERGE (a)-[r:USES_MEDIA {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed',
    r.Procedure = trim(row.Procedure);

LOAD CSV WITH HEADERS FROM 'file:///Process_Confirm.csv' AS row
MATCH (a:Process {name: trim(row.Process)})
MATCH (b:Confirmation_Study {name: trim(row.Confirmation_Study)})
MERGE (a)-[r:VALIDATED_BY {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Process_Process.csv' AS row
MATCH (a:Process {name: trim(row.Process)})
MATCH (b:Process {name: trim(row.Process_1)})
MERGE (a)-[r:FOLLOWED_BY {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Assessment-Disease(Pubmed).csv' AS row
MATCH (a:Assessment_Parameter {name: trim(row.Assessment_Parameter)})
MATCH (b:Disease {name: trim(row.Disease)})
MERGE (a)-[r:ASSESS {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed',
    r.Outcome_Measure = trim(row.Outcome_Measure);

LOAD CSV WITH HEADERS FROM 'file:///Therapy-Formulation(Pubmed).csv' AS row
MATCH (a:Therapy {name: trim(row.Therapy)})
MATCH (b:Formulation {name: trim(row.Formulation)})
MERGE (a)-[r:USES_FORMULATION {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed',
    r.Adjuvant = trim(row.Adjuvant),
    r.Dose = trim(row.Dose),
    r.Duration = trim(row.Duration);

LOAD CSV WITH HEADERS FROM 'file:///Treated_by(Pubmed).csv' AS row
MATCH (a:Disease {name: trim(row.Disease)})
MATCH (b:Therapy {name: trim(row.Therapy)})
MERGE (a)-[r:TREATED_BY {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Pre-treated_by(Pubmed).csv' AS row
MATCH (a:Disease {name: trim(row.Disease)})
MATCH (b:Therapy {name: trim(row.Therapy)})
MERGE (a)-[r:PRE_TREATED_BY {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Post-treated_by(Pubmed).csv' AS row
MATCH (a:Disease {name: trim(row.Disease)})
MATCH (b:Therapy {name: trim(row.Therapy)})
MERGE (a)-[r:POST_TREATED_BY {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Disease-Symptoms(Pubmed).csv' AS row
MATCH (a:Disease {name: trim(row.Disease)})
MATCH (b:Symptom {name: trim(row.Symptom)})
MERGE (a)-[r:HAS_SYMPTOM {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Disease-Dosha(Pubmed).csv' AS row
MATCH (a:Disease {name: trim(row.Disease)})
MATCH (b:Dosha {name: trim(row.Dosha)})
MERGE (a)-[r:HAS_DOSHA_IMBALANCE {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Disease-diagnosis(Pubmed).csv' AS row
MATCH (a:Disease {name: trim(row.Disease)})
MATCH (b:Ayurvedic_Diagnosis {name: trim(row.Ayurvedic_Diagnosis)})
MERGE (a)-[r:HAS_AYURVEDIC_DIAGNOSIS {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

LOAD CSV WITH HEADERS FROM 'file:///Purified_Disease.csv' AS row
MATCH (a:Purified_Compound {name: trim(row.Purified_Compound)})
MATCH (b:Disease {name: trim(row.Disease)})
MERGE (a)-[r:USED_FOR_TREATMENT {ref: trim(row.`Pubmed_ID`)}]->(b)
SET r.ref_type = 'PubMed';

// =============================================================================
// STEP 5 : RELATIONSHIPS - Ayush_ID reference
// Reference column in each CSV : Ayush_ID   ->  stored as r.ref, r.ref_type = 'AYUSH'
// CSV names: <name>_Ayush.csv (compound files) / <name>(Ayush).csv (disease files).
// Rename the file names below if your Ayush CSVs are named differently.
// =============================================================================

LOAD CSV WITH HEADERS FROM 'file:///Comp_Sho_Meth_Ayush.csv' AS row
MATCH (a:Compound {name: trim(row.Compound)})
MATCH (b:Shodhana_Method {name: trim(row.Shodhana_Method)})
MERGE (a)-[r:UNDERGOES {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH',
    r.Procedure = trim(row.Procedure);

LOAD CSV WITH HEADERS FROM 'file:///Comp_Toxic_Ayush.csv' AS row
MATCH (a:Compound {name: trim(row.Compound)})
MATCH (b:Toxic_Substance {name: trim(row.Toxic_Substance)})
MERGE (a)-[r:CONTAINS {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Purified_Comp_Ayush.csv' AS row
MATCH (a:Purified_Compound {name: trim(row.Purified_Compound)})
MATCH (b:Compound {name: trim(row.Compound)})
MERGE (a)-[r:DERIVED_FROM {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Purified_Indication_Ayush.csv' AS row
MATCH (a:Purified_Compound {name: trim(row.Purified_Compound)})
MATCH (b:Indication {name: trim(row.Indication)})
MERGE (a)-[r:TREATS {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Process_Purified_Ayush.csv' AS row
MATCH (a:Process {name: trim(row.Process)})
MATCH (b:Purified_Compound {name: trim(row.Purified_Compound)})
MERGE (a)-[r:PRODUCES {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Confirm_Purified_Ayush.csv' AS row
MATCH (a:Confirmation_Study {name: trim(row.Confirmation_Study)})
MATCH (b:Purified_Compound {name: trim(row.Purified_Compound)})
MERGE (a)-[r:CONFIRMS_SAFETY_OF {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH',
    r.Result = trim(row.Result);

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Media_Ayush.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Media {name: trim(row.Media)})
MERGE (a)-[r:USES_MEDIA {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH',
    r.Procedure = trim(row.Procedure);

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Confirm_Ayush.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Confirmation_Study {name: trim(row.Confirmation_Study)})
MERGE (a)-[r:VALIDATED_BY {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Toxic_Ayush.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Toxic_Substance {name: trim(row.Toxic_Substance)})
MERGE (a)-[r:DETOXIFIES {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Sho_Type_Ayush.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Shodhana_Type {name: trim(row.Shodhana_Type)})
MERGE (a)-[r:CLASSIFIED_AS {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH',
    r.Media_Used = trim(row.Media_Used);

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Purified_Ayush.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Purified_Compound {name: trim(row.Purified_Compound)})
MERGE (a)-[r:PRODUCES {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Purified_For_Ayush.csv' AS row
MATCH (a:Purified_Compound {name: trim(row.Purified_Compound)})
MATCH (b:Ayurvedic_Formulation {name: trim(row.Ayurvedic_Formulation)})
MERGE (a)-[r:USED_IN_FORMULATION {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Process_Ayush.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Process {name: trim(row.Process)})
MERGE (a)-[r:FOLLOWED_BY {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Process_Media_Ayush.csv' AS row
MATCH (a:Process {name: trim(row.Process)})
MATCH (b:Media {name: trim(row.Media)})
MERGE (a)-[r:USES_MEDIA {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH',
    r.Procedure = trim(row.Procedure);

LOAD CSV WITH HEADERS FROM 'file:///Process_Confirm_Ayush.csv' AS row
MATCH (a:Process {name: trim(row.Process)})
MATCH (b:Confirmation_Study {name: trim(row.Confirmation_Study)})
MERGE (a)-[r:VALIDATED_BY {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Process_Process_Ayush.csv' AS row
MATCH (a:Process {name: trim(row.Process)})
MATCH (b:Process {name: trim(row.Process_1)})
MERGE (a)-[r:FOLLOWED_BY {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Assessment-Disease(Ayush).csv' AS row
MATCH (a:Assessment_Parameter {name: trim(row.Assessment_Parameter)})
MATCH (b:Disease {name: trim(row.Disease)})
MERGE (a)-[r:ASSESS {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH',
    r.Outcome_Measure = trim(row.Outcome_Measure);

LOAD CSV WITH HEADERS FROM 'file:///Therapy-Formulation(Ayush).csv' AS row
MATCH (a:Therapy {name: trim(row.Therapy)})
MATCH (b:Formulation {name: trim(row.Formulation)})
MERGE (a)-[r:USES_FORMULATION {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH',
    r.Adjuvant = trim(row.Adjuvant),
    r.Dose = trim(row.Dose),
    r.Duration = trim(row.Duration);

LOAD CSV WITH HEADERS FROM 'file:///Treated_by(Ayush).csv' AS row
MATCH (a:Disease {name: trim(row.Disease)})
MATCH (b:Therapy {name: trim(row.Therapy)})
MERGE (a)-[r:TREATED_BY {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Pre-treated_by(Ayush).csv' AS row
MATCH (a:Disease {name: trim(row.Disease)})
MATCH (b:Therapy {name: trim(row.Therapy)})
MERGE (a)-[r:PRE_TREATED_BY {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Post-treated_by(Ayush).csv' AS row
MATCH (a:Disease {name: trim(row.Disease)})
MATCH (b:Therapy {name: trim(row.Therapy)})
MERGE (a)-[r:POST_TREATED_BY {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Disease-Symptoms(Ayush).csv' AS row
MATCH (a:Disease {name: trim(row.Disease)})
MATCH (b:Symptom {name: trim(row.Symptom)})
MERGE (a)-[r:HAS_SYMPTOM {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Disease-Dosha(Ayush).csv' AS row
MATCH (a:Disease {name: trim(row.Disease)})
MATCH (b:Dosha {name: trim(row.Dosha)})
MERGE (a)-[r:HAS_DOSHA_IMBALANCE {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Disease-diagnosis(Ayush).csv' AS row
MATCH (a:Disease {name: trim(row.Disease)})
MATCH (b:Ayurvedic_Diagnosis {name: trim(row.Ayurvedic_Diagnosis)})
MERGE (a)-[r:HAS_AYURVEDIC_DIAGNOSIS {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

LOAD CSV WITH HEADERS FROM 'file:///Purified_Disease_Ayush.csv' AS row
MATCH (a:Purified_Compound {name: trim(row.Purified_Compound)})
MATCH (b:Disease {name: trim(row.Disease)})
MERGE (a)-[r:USED_FOR_TREATMENT {ref: trim(row.`Ayush_ID`)}]->(b)
SET r.ref_type = 'AYUSH';

// =============================================================================
// STEP 6 : RELATIONSHIPS - DOI reference
// Reference column in each CSV : DOI   ->  stored as r.ref, r.ref_type = 'DOI'
// CSV names: <name>_DOI.csv (compound files) / <name>(DOI).csv (disease files).
// Rename the file names below if your DOI CSVs are named differently.
// =============================================================================

LOAD CSV WITH HEADERS FROM 'file:///Comp_Sho_Meth_DOI.csv' AS row
MATCH (a:Compound {name: trim(row.Compound)})
MATCH (b:Shodhana_Method {name: trim(row.Shodhana_Method)})
MERGE (a)-[r:UNDERGOES {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI',
    r.Procedure = trim(row.Procedure);

LOAD CSV WITH HEADERS FROM 'file:///Comp_Toxic_DOI.csv' AS row
MATCH (a:Compound {name: trim(row.Compound)})
MATCH (b:Toxic_Substance {name: trim(row.Toxic_Substance)})
MERGE (a)-[r:CONTAINS {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI';

LOAD CSV WITH HEADERS FROM 'file:///Purified_Comp_DOI.csv' AS row
MATCH (a:Purified_Compound {name: trim(row.Purified_Compound)})
MATCH (b:Compound {name: trim(row.Compound)})
MERGE (a)-[r:DERIVED_FROM {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI';

LOAD CSV WITH HEADERS FROM 'file:///Purified_Indication_DOI.csv' AS row
MATCH (a:Purified_Compound {name: trim(row.Purified_Compound)})
MATCH (b:Indication {name: trim(row.Indication)})
MERGE (a)-[r:TREATS {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI';

LOAD CSV WITH HEADERS FROM 'file:///Process_Purified_DOI.csv' AS row
MATCH (a:Process {name: trim(row.Process)})
MATCH (b:Purified_Compound {name: trim(row.Purified_Compound)})
MERGE (a)-[r:PRODUCES {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI';

LOAD CSV WITH HEADERS FROM 'file:///Confirm_Purified_DOI.csv' AS row
MATCH (a:Confirmation_Study {name: trim(row.Confirmation_Study)})
MATCH (b:Purified_Compound {name: trim(row.Purified_Compound)})
MERGE (a)-[r:CONFIRMS_SAFETY_OF {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI',
    r.Result = trim(row.Result);

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Media_DOI.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Media {name: trim(row.Media)})
MERGE (a)-[r:USES_MEDIA {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI',
    r.Procedure = trim(row.Procedure);

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Confirm_DOI.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Confirmation_Study {name: trim(row.Confirmation_Study)})
MERGE (a)-[r:VALIDATED_BY {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI';

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Toxic_DOI.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Toxic_Substance {name: trim(row.Toxic_Substance)})
MERGE (a)-[r:DETOXIFIES {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI';

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Sho_Type_DOI.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Shodhana_Type {name: trim(row.Shodhana_Type)})
MERGE (a)-[r:CLASSIFIED_AS {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI',
    r.Media_Used = trim(row.Media_Used);

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Purified_DOI.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Purified_Compound {name: trim(row.Purified_Compound)})
MERGE (a)-[r:PRODUCES {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI';

LOAD CSV WITH HEADERS FROM 'file:///Purified_For_DOI.csv' AS row
MATCH (a:Purified_Compound {name: trim(row.Purified_Compound)})
MATCH (b:Ayurvedic_Formulation {name: trim(row.Ayurvedic_Formulation)})
MERGE (a)-[r:USED_IN_FORMULATION {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI';

LOAD CSV WITH HEADERS FROM 'file:///Sho_Meth_Process_DOI.csv' AS row
MATCH (a:Shodhana_Method {name: trim(row.Shodhana_Method)})
MATCH (b:Process {name: trim(row.Process)})
MERGE (a)-[r:FOLLOWED_BY {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI';

LOAD CSV WITH HEADERS FROM 'file:///Process_Media_DOI.csv' AS row
MATCH (a:Process {name: trim(row.Process)})
MATCH (b:Media {name: trim(row.Media)})
MERGE (a)-[r:USES_MEDIA {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI',
    r.Procedure = trim(row.Procedure);

LOAD CSV WITH HEADERS FROM 'file:///Process_Confirm_DOI.csv' AS row
MATCH (a:Process {name: trim(row.Process)})
MATCH (b:Confirmation_Study {name: trim(row.Confirmation_Study)})
MERGE (a)-[r:VALIDATED_BY {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI';

LOAD CSV WITH HEADERS FROM 'file:///Process_Process_DOI.csv' AS row
MATCH (a:Process {name: trim(row.Process)})
MATCH (b:Process {name: trim(row.Process_1)})
MERGE (a)-[r:FOLLOWED_BY {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI';

LOAD CSV WITH HEADERS FROM 'file:///Purified_Disease_DOI.csv' AS row
MATCH (a:Purified_Compound {name: trim(row.Purified_Compound)})
MATCH (b:Disease {name: trim(row.Disease)})
MERGE (a)-[r:USED_FOR_TREATMENT {ref: trim(row.`DOI`)}]->(b)
SET r.ref_type = 'DOI';

// =============================================================================
// STEP 7 : VERIFICATION
// =============================================================================

// 7.1 Relationship counts per type and reference type
MATCH ()-[r]->()
RETURN type(r) AS relationship, r.ref_type AS reference_type, count(*) AS total
ORDER BY relationship, reference_type;

// 7.2 Sample relationship checks
MATCH (c:Compound)-[r:UNDERGOES]->(s:Shodhana_Method) RETURN c, r, s LIMIT 25;
MATCH (c:Compound)-[r:CONTAINS]->(s:Toxic_Substance) RETURN c, r, s LIMIT 25;

// 7.3 Every reference supporting one compound
MATCH (c:Compound)-[r]->(x)
RETURN c.name AS compound, type(r) AS relationship, x.name AS target, r.ref AS reference, r.ref_type AS reference_type
LIMIT 50;

// 7.4 Relationships that are missing a reference (should return 0 rows)
MATCH ()-[r]->() WHERE r.ref IS NULL OR r.ref = "" RETURN type(r), count(*) ;

// 7.5 Orphan nodes (no relationships)
MATCH (n) WHERE NOT (n)--() RETURN labels(n) AS label, count(*) AS orphans;

// 7.6 Overall totals
MATCH (n) RETURN count(n) AS total_nodes;
MATCH ()-[r]->() RETURN count(r) AS total_relationships;
