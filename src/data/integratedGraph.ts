// Integrated-ShodhanaKG graph: merges Disease + Compound subgraphs and adds
// the two cross-domain bridge relationships:
//   PurifiedCompound --[USED_IN_FORMULATION]--> Formulation
//   PurifiedCompound --[USED_FOR_TREATMENT]--> Disease
//
// Node ids are namespaced (`d:` for disease side, `c:` for compound side) to
// avoid collisions when the two graphs share concept types like Disease /
// Formulation.

import type {
  NodeType as DiseaseNodeType,
  EdgeType as DiseaseEdgeType,
} from "./diseaseGraph";
import type {
  CompoundNodeType,
  CompoundEdgeType,
} from "./compoundGraph";

export type IntegratedNodeType =
  // Disease-side
  | "Disease"
  | "AyurvedicDiagnosis"
  | "Therapy"
  | "Formulation"
  | "Symptom"
  | "AssessmentParameter"
  | "Dosha"
  // Compound-side (Formulation + Disease are shared with disease-side)
  | "Compound"
  | "ShodhanaMethod"
  | "ToxicSubstance"
  | "PurifiedCompound"
  | "ShodhanaType"
  | "Media"
  | "ConfirmationStudy"
  | "Process"
  | "Indication"
  | "AyurvedicFormulation";

export type IntegratedEdgeType =
  // Disease side
  | "HAS_SYMPTOM"
  | "HAS_AYURVEDIC_DIAGNOSIS"
  | "HAS_DOSHA_IMBALANCE"
  | "TREATED_BY"
  | "PRETREATED_BY"
  | "POSTTREATED_BY"
  | "RESOLVED_BY"
  | "USES_FORMULATION"
  | "ASSESS"
  // Compound side
  | "UNDERGOES"
  | "CONTAINS"
  | "USES_MEDIA"
  | "PRODUCES"
  | "DETOXIFIES"
  | "VALIDATED_BY"
  | "CLASSIFIED_AS"
  | "FOLLOWED_BY"
  | "INVOLVES_PROCESS"
  | "DERIVED_FROM"
  | "CONFIRMS_SAFETY_OF"
  // Bridge
  | "USED_FOR_TREATMENT"
  | "USED_IN_FORMULATION"
  | "TREATS";

export interface IntegratedGraphNode {
  id: string;
  label: string;
  type: IntegratedNodeType;
  description: string;
  side: "disease" | "compound" | "bridge";
  [k: string]: unknown;
}

export interface IntegratedGraphEdge {
  id: string;
  source: string;
  target: string;
  type: IntegratedEdgeType;
  side: "disease" | "compound" | "bridge";
  procedure?: string;
  result?: string;
  mediaUsed?: string;
  outputMeasure?: string;
  adjuvant?: string;
  dose?: string;
  duration?: string;
  references?: import("@/lib/types").PublicationReference[];
  pubmedId?: string;
  bridgeNote?: string;
  [k: string]: unknown;
}

export const I_NODE_TYPE_LABEL: Record<IntegratedNodeType, string> = {
  Indication: "Indication", AyurvedicFormulation: "Ayurvedic Formulation",
  Disease: "Disease",
  AyurvedicDiagnosis: "Ayurvedic Diagnosis",
  Therapy: "Therapy",
  Formulation: "Formulation",
  Symptom: "Symptom",
  AssessmentParameter: "Assessment Parameter",
  Dosha: "Dosha",
  Compound: "Compound",
  ShodhanaMethod: "Shodhana Method",
  ToxicSubstance: "Toxic Substance",
  PurifiedCompound: "Purified Compound",
  ShodhanaType: "Shodhana Type",
  Media: "Media",
  ConfirmationStudy: "Confirmation Study",
  Process: "Process",
};

export const I_EDGE_TYPE_LABEL: Record<IntegratedEdgeType, string> = {
  TREATS: "TREATS",
  HAS_SYMPTOM: "HAS_SYMPTOM",
  HAS_AYURVEDIC_DIAGNOSIS: "HAS_AYURVEDIC_DIAGNOSIS",
  HAS_DOSHA_IMBALANCE: "HAS_DOSHA_IMBALANCE",
  TREATED_BY: "TREATED_BY",
  PRETREATED_BY: "PRETREATED_BY",
  POSTTREATED_BY: "POSTTREATED_BY",
  RESOLVED_BY: "RESOLVED_BY",
  USES_FORMULATION: "USES_FORMULATION",
  ASSESS: "ASSESS",
  UNDERGOES: "UNDERGOES",
  CONTAINS: "CONTAINS",
  USES_MEDIA: "USES_MEDIA",
  PRODUCES: "PRODUCES",
  DETOXIFIES: "DETOXIFIES",
  VALIDATED_BY: "VALIDATED_BY",
  CLASSIFIED_AS: "CLASSIFIED_AS",
  FOLLOWED_BY: "FOLLOWED_BY",
  INVOLVES_PROCESS: "INVOLVES_PROCESS",
  DERIVED_FROM: "DERIVED_FROM",
  CONFIRMS_SAFETY_OF: "CONFIRMS_SAFETY_OF",
  USED_FOR_TREATMENT: "USED_FOR_TREATMENT",
  USED_IN_FORMULATION: "USED_IN_FORMULATION",
};

export const I_NODE_COLORS: Record<IntegratedNodeType, string> = {
  Indication: "hsl(4, 56%, 48%)", AyurvedicFormulation: "hsl(320, 44%, 68%)",
  // Disease-side palette
  Disease: "hsl(4, 56%, 48%)",
  AyurvedicDiagnosis: "hsl(266.5909090909, 35.2%, 49.0196078431%)",
  Therapy: "hsl(162, 45%, 42%)",
  Formulation: "hsl(320, 44%, 68%)",
  Symptom: "hsl(40, 90%, 62%)",
  AssessmentParameter: "hsl(208, 54%, 48%)",
  Dosha: "hsl(20, 53%, 37%)",
  // Compound-side palette
  Compound: "hsl(120, 25%, 50%)",
  ShodhanaMethod: "hsl(28, 75%, 55%)",
  ToxicSubstance: "hsl(0, 70%, 55%)",
  PurifiedCompound: "hsl(205, 65%, 55%)",
  ShodhanaType: "hsl(270, 60%, 60%)",
  Media: "hsl(180, 40%, 50%)",
  ConfirmationStudy: "hsl(48, 85%, 60%)",
  Process: "hsl(35, 30%, 50%)",
};

export const I_EDGE_COLORS: Record<IntegratedEdgeType, string> = {
  TREATS: "hsl(4, 56%, 48%)",
  HAS_SYMPTOM: "hsl(40, 90%, 62%)",
  HAS_AYURVEDIC_DIAGNOSIS: "#7851A9",
  HAS_DOSHA_IMBALANCE: "hsl(20, 53%, 37%)",
  TREATED_BY: "hsl(162, 45%, 42%)",
  PRETREATED_BY: "hsl(162, 35%, 45%)",
  POSTTREATED_BY: "hsl(162, 25%, 35%)",
  RESOLVED_BY: "hsl(208, 45%, 50%)",
  USES_FORMULATION: "hsl(320, 44%, 68%)",
  ASSESS: "hsl(208, 54%, 48%)",
  UNDERGOES: "hsl(28, 75%, 55%)",
  CONTAINS: "hsl(0, 70%, 55%)",
  USES_MEDIA: "hsl(180, 40%, 50%)",
  PRODUCES: "hsl(205, 65%, 55%)",
  DETOXIFIES: "hsl(0, 40%, 60%)",
  VALIDATED_BY: "hsl(48, 85%, 60%)",
  CLASSIFIED_AS: "hsl(270, 40%, 50%)",
  FOLLOWED_BY: "hsl(35, 30%, 50%)",
  INVOLVES_PROCESS: "hsl(35, 30%, 50%)",
  DERIVED_FROM: "hsl(120, 25%, 50%)",
  CONFIRMS_SAFETY_OF: "hsl(48, 85%, 60%)",
  // Bridges â€” consistent with source side colors
  USED_FOR_TREATMENT: "hsl(4, 56%, 48%)",
  USED_IN_FORMULATION: "hsl(320, 44%, 68%)",
};

// Refinement filter groups â€” only active after a starting node is chosen.
export const REFINEMENT_GROUPS: { key: IntegratedNodeType; label: string; accent: string }[] = [
  { key: "Symptom", label: "Symptoms", accent: "bg-[var(--royal-gold)]" },
  { key: "AyurvedicDiagnosis", label: "Ayurvedic Diagnosis", accent: "bg-bark" },
  { key: "Dosha", label: "Dosha", accent: "bg-[var(--deep-walnut)]" },
  { key: "Therapy", label: "Therapy", accent: "bg-[var(--muted-teal)]" },
  { key: "AssessmentParameter", label: "Assessment Parameter", accent: "bg-[var(--yale-blue)]" },
  { key: "ShodhanaMethod", label: "Shodhana Method", accent: "bg-[var(--c-terracotta)]" },
  { key: "ToxicSubstance", label: "Toxic Substance", accent: "bg-[var(--c-toxic)]" },
  { key: "ShodhanaType", label: "Shodhana Type", accent: "bg-[var(--c-type)]" },
  { key: "Media", label: "Media", accent: "bg-[var(--c-media)]" },
  { key: "ConfirmationStudy", label: "Confirmation Study", accent: "bg-[var(--c-evidence)]" },
  { key: "Process", label: "Process", accent: "bg-[var(--c-process)]" },
];

export const ALL_INTEGRATED_NODE_TYPES: IntegratedNodeType[] = [
  "Disease", "AyurvedicDiagnosis", "Therapy", "Formulation", "Symptom",
  "AssessmentParameter", "Dosha", "Compound", "ShodhanaMethod", "ToxicSubstance",
  "PurifiedCompound", "ShodhanaType", "Media", "ConfirmationStudy", "Process",
];

export const ALL_INTEGRATED_EDGE_TYPES: IntegratedEdgeType[] = [
  "HAS_SYMPTOM", "HAS_AYURVEDIC_DIAGNOSIS", "HAS_DOSHA_IMBALANCE", "TREATED_BY", "PRETREATED_BY",
  "POSTTREATED_BY", "RESOLVED_BY", "USES_FORMULATION", "ASSESS", "UNDERGOES", "CONTAINS", "USES_MEDIA", "PRODUCES", "DETOXIFIES",
  "VALIDATED_BY", "CLASSIFIED_AS", "FOLLOWED_BY", "INVOLVES_PROCESS",
  "DERIVED_FROM", "CONFIRMS_SAFETY_OF", "USED_FOR_TREATMENT", "USED_IN_FORMULATION",
];

export const BRIDGE_EDGE_TYPES: IntegratedEdgeType[] = ["USED_FOR_TREATMENT", "USED_IN_FORMULATION"];

// Re-exports for convenience
export type { DiseaseNodeType, DiseaseEdgeType, CompoundNodeType, CompoundEdgeType };

