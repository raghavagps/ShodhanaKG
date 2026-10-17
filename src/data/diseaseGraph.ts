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
