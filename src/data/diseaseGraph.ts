// Sample Disease-ShodhanaKG graph data.
// Schema:
//   Nodes: Disease, AyurvedicDiagnosis, Therapy, Formulation, Symptom, AssessmentParameter, Dosha
//   Edges: HAS_SYMPTOM, HAS_AYURVEDIC_DIAGNOSIS, HAS_DOSHA_IMBALANCE, TREATED_BY, PRETREATED_BY, POSTTREATED_BY, ASSESS

export type NodeType =
  | "Disease"
  | "AyurvedicDiagnosis"
  | "Therapy"
  | "Formulation"
  | "Symptom"
  | "AssessmentParameter"
  | "Dosha";

export type EdgeType =
  | "HAS_SYMPTOM"
  | "HAS_AYURVEDIC_DIAGNOSIS"
  | "HAS_DOSHA_IMBALANCE"
  | "TREATED_BY"
  | "PRETREATED_BY"
  | "POSTTREATED_BY"
  | "ASSESS"
  | "USES_FORMULATION";

export interface GraphNode {
  id: string;
  label: string;
  type: NodeType;
  description: string;
  [k: string]: unknown;
}

export interface GraphEdge {
  id: string;
  source: string;
  target: string;
  type: EdgeType;
  // optional metadata
  adjuvant?: string;
  dose?: string;
  duration?: string;
  outputMeasure?: string;
  pubmedId?: string;
  [k: string]: unknown;
}

export const NODE_TYPE_LABEL: Record<NodeType, string> = {
  Disease: "Disease",
  AyurvedicDiagnosis: "Ayurvedic Diagnosis",
  Therapy: "Therapy",
  Formulation: "Formulation",
  Symptom: "Symptom",
  AssessmentParameter: "Assessment Parameter",
  Dosha: "Dosha",
};

export const EDGE_TYPE_LABEL: Record<EdgeType, string> = {
  HAS_SYMPTOM: "HAS_SYMPTOM",
  HAS_AYURVEDIC_DIAGNOSIS: "HAS_AYURVEDIC_DIAGNOSIS",
  HAS_DOSHA_IMBALANCE: "HAS_DOSHA_IMBALANCE",
  TREATED_BY: "TREATED_BY",
  PRETREATED_BY: "PRETREATED_BY",
  POSTTREATED_BY: "POSTTREATED_BY",
  ASSESS: "ASSESS",
  USES_FORMULATION: "USES_FORMULATION",
};

// HSL strings (for cytoscape styling â€” cytoscape needs full css colors)
export const NODE_COLORS: Record<NodeType, string> = {
  Disease: "hsl(4, 56%, 48%)",             // rich terracotta
  AyurvedicDiagnosis: "hsl(266.5909090909, 35.2%, 49.0196078431%)", // bark
  Therapy: "hsl(162, 45%, 42%)",            // deep sage
  Formulation: "hsl(320, 44%, 68%)",        // vibrant orchid
  Symptom: "hsl(40, 90%, 62%)",             // rich amber
  AssessmentParameter: "hsl(208, 54%, 48%)",// strong cerulean
  Dosha: "hsl(20, 53%, 37%)",               // deep sienna
};

export const EDGE_COLORS: Record<EdgeType, string> = {
  HAS_SYMPTOM: "hsl(35, 50%, 55%)",
  HAS_AYURVEDIC_DIAGNOSIS: "#7851A9",
  HAS_DOSHA_IMBALANCE: "hsl(280, 18%, 50%)",
  TREATED_BY: "hsl(130, 22%, 35%)",
  PRETREATED_BY: "hsl(130, 22%, 40%)",
  POSTTREATED_BY: "hsl(130, 22%, 45%)",
  ASSESS: "hsl(200, 25%, 45%)",
  USES_FORMULATION: "hsl(140, 20%, 50%)",
};

// ---- Sample data ----
export const NODES: GraphNode[] = [
  // Diseases
  { id: "d1", label: "Rheumatoid Arthritis", type: "Disease", description: "Chronic autoimmune inflammatory joint disorder; correlates with Amavata in Ayurveda." },
  { id: "d2", label: "Type 2 Diabetes", type: "Disease", description: "Metabolic disorder of insulin resistance; correlates with Madhumeha." },
  { id: "d3", label: "Bronchial Asthma", type: "Disease", description: "Chronic airway inflammation; correlates with Tamaka Shvasa." },

  // Ayurvedic Diagnoses
  { id: "ad1", label: "Amavata", type: "AyurvedicDiagnosis", description: "Disorder due to accumulation of Ama with Vata aggravation." },
  { id: "ad2", label: "Madhumeha", type: "AyurvedicDiagnosis", description: "Vataja Prameha characterized by sweet urine." },
  { id: "ad3", label: "Tamaka Shvasa", type: "AyurvedicDiagnosis", description: "Type of Shvasa Roga marked by paroxysmal dyspnoea." },

  // Doshas
  { id: "dh1", label: "Vata", type: "Dosha", description: "Bio-element governing movement and nervous functions." },
  { id: "dh2", label: "Pitta", type: "Dosha", description: "Bio-element governing metabolism and transformation." },
  { id: "dh3", label: "Kapha", type: "Dosha", description: "Bio-element governing structure and lubrication." },

  // Symptoms
  { id: "s1", label: "Joint Pain", type: "Symptom", description: "Pain localized to one or more joints." },
  { id: "s2", label: "Morning Stiffness", type: "Symptom", description: "Stiffness lasting >30 min after waking." },
  { id: "s3", label: "Polyuria", type: "Symptom", description: "Excessive urine production." },
  { id: "s4", label: "Wheezing", type: "Symptom", description: "High-pitched breath sound on expiration." },

  // Therapies
  { id: "t1", label: "Virechana", type: "Therapy", description: "Therapeutic purgation â€” Panchakarma procedure." },
  { id: "t2", label: "Basti", type: "Therapy", description: "Medicated enema therapy primarily for Vata disorders." },
  { id: "t3", label: "Nasya", type: "Therapy", description: "Nasal administration of medicated substances." },

  // Formulations
  { id: "f1", label: "Simhanada Guggulu", type: "Formulation", description: "Classical Guggulu formulation indicated in Amavata." },
  { id: "f2", label: "Vasa Avaleha", type: "Formulation", description: "Avaleha indicated in respiratory disorders." },
  { id: "f3", label: "Triphala Churna", type: "Formulation", description: "Equal parts of Haritaki, Bibhitaki, Amalaki." },

  // Assessment Parameters
  { id: "a1", label: "DAS-28 Score", type: "AssessmentParameter", description: "Disease Activity Score in 28 joints." },
  { id: "a2", label: "HbA1c", type: "AssessmentParameter", description: "Glycated hemoglobin reflecting 3-month glycemia." },
  { id: "a3", label: "PEFR", type: "AssessmentParameter", description: "Peak Expiratory Flow Rate." },
];

export const EDGES: GraphEdge[] = [
  // Disease -> Symptoms
  { id: "e1", source: "d1", target: "s1", type: "HAS_SYMPTOM" },
  { id: "e2", source: "d1", target: "s2", type: "HAS_SYMPTOM" },
  { id: "e3", source: "d2", target: "s3", type: "HAS_SYMPTOM" },
  { id: "e4", source: "d3", target: "s4", type: "HAS_SYMPTOM" },

  // Disease -> Ayurvedic Diagnosis
  { id: "e5", source: "d1", target: "ad1", type: "HAS_AYURVEDIC_DIAGNOSIS" },
  { id: "e6", source: "d2", target: "ad2", type: "HAS_AYURVEDIC_DIAGNOSIS" },
  { id: "e7", source: "d3", target: "ad3", type: "HAS_AYURVEDIC_DIAGNOSIS" },

  // Disease -> Dosha
  { id: "e8", source: "d1", target: "dh1", type: "HAS_DOSHA_IMBALANCE" },
  { id: "e9", source: "d2", target: "dh3", type: "HAS_DOSHA_IMBALANCE" },
  { id: "e10", source: "d3", target: "dh3", type: "HAS_DOSHA_IMBALANCE" },

  // Disease -> Therapy
  { id: "e11", source: "d1", target: "t2", type: "TREATED_BY" },
  { id: "e12", source: "d2", target: "t1", type: "TREATED_BY" },
  { id: "e13", source: "d3", target: "t3", type: "TREATED_BY" },

  // Disease -> Assessment Parameter
  { id: "e17", source: "d1", target: "a1", type: "ASSESS", outputMeasure: "Joint count + ESR", pubmedId: "28765432" },
  { id: "e18", source: "d2", target: "a2", type: "ASSESS", outputMeasure: "% glycated Hb", pubmedId: "29345671" },
  { id: "e19", source: "d3", target: "a3", type: "ASSESS", outputMeasure: "L/min", pubmedId: "31876543" },
];

