// Integrated-ShodhanaKG graph: merges Disease + Compound subgraphs and adds
// the two cross-domain bridge relationships:
//   PurifiedCompound --[USED_IN_FORMULATION]--> Formulation
//   PurifiedCompound --[USED_FOR_TREATMENT]--> Disease
//
// Node ids are namespaced (`d:` for disease side, `c:` for compound side) to
// avoid collisions when the two graphs share concept types like Disease /
// Formulation.

import {
  NODES as D_NODES_RAW,
  EDGES as D_EDGES_RAW,
  NodeType as DiseaseNodeType,
  EdgeType as DiseaseEdgeType,
} from "./diseaseGraph";
import {
  C_NODES as C_NODES_RAW,
  C_EDGES as C_EDGES_RAW,
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

// Map disease node ids -> integrated ids
const dId = (id: string) => `d:${id}`;
const cId = (id: string) => `c:${id}`;

const D_NODES: IntegratedGraphNode[] = D_NODES_RAW.map((n) => ({
  id: dId(n.id),
  label: n.label,
  type: n.type as IntegratedNodeType,
  description: n.description,
  side: "disease",
}));

const D_EDGES: IntegratedGraphEdge[] = D_EDGES_RAW.map((e) => ({
  id: `d:${e.id}`,
  source: dId(e.source),
  target: dId(e.target),
  type: e.type as IntegratedEdgeType,
  side: "disease",
  adjuvant: e.adjuvant,
  dose: e.dose,
  duration: e.duration,
  outputMeasure: e.outputMeasure,
  pubmedId: e.pubmedId,
}));

const C_NODES: IntegratedGraphNode[] = C_NODES_RAW.map((n) => ({
  id: cId(n.id),
  label: n.label,
  // Note: compound graph also has its own "Disease" + "Formulation" nodes;
  // they remain as separate nodes here (we don't auto-merge by label).
  type: n.type as IntegratedNodeType,
  description: n.description,
  side: "compound",
}));

const C_EDGES: IntegratedGraphEdge[] = C_EDGES_RAW
  // Drop USED_FOR_TREATMENT / USED_IN_FORMULATION from compound side â€” we re-add
  // them as explicit cross-domain bridges below.
  .filter((e) => e.type !== "USED_FOR_TREATMENT" && e.type !== "USED_IN_FORMULATION")
  .map((e) => ({
    id: `c:${e.id}`,
    source: cId(e.source),
    target: cId(e.target),
    type: e.type as IntegratedEdgeType,
    side: "compound",
    procedure: e.procedure,
    result: e.result,
    mediaUsed: e.mediaUsed,
    pubmedId: e.pubmedId,
  }));

// ---- Bridge edges: compound-side PurifiedCompound -> disease-side Disease/Formulation
// We hand-curate the bridge mapping using labels from both graphs.
const BRIDGE_NOTE_TREAT =
  "Bridge: a purified compound (post-Shodhana) is used therapeutically for this disease â€” connecting the Compound-ShodhanaKG to the Disease-ShodhanaKG.";
const BRIDGE_NOTE_FORM =
  "Bridge: a purified compound is an ingredient of this formulation, linking compound purification evidence to disease-side therapy.";

export const I_BRIDGE_EDGES: IntegratedGraphEdge[] = [
  // Shuddha Vatsanabha (pc1) -> Amavata disease (d:d1) and Simhanada Guggulu (d:f1)
  {
    id: "b1",
    source: cId("pc1"),
    target: dId("d1"),
    type: "USED_FOR_TREATMENT",
    side: "bridge",
    pubmedId: "30123456",
    bridgeNote: BRIDGE_NOTE_TREAT,
  },
  {
    id: "b2",
    source: cId("pc1"),
    target: dId("f1"),
    type: "USED_IN_FORMULATION",
    side: "bridge",
    pubmedId: "29876543",
    bridgeNote: BRIDGE_NOTE_FORM,
  },
  // Shuddha Bhallataka (pc3) -> Bronchial Asthma proxy (d:d3) + Vasa Avaleha (d:f2)
  {
    id: "b3",
    source: cId("pc3"),
    target: dId("d3"),
    type: "USED_FOR_TREATMENT",
    side: "bridge",
    pubmedId: "31234567",
    bridgeNote: BRIDGE_NOTE_TREAT,
  },
  {
    id: "b4",
    source: cId("pc3"),
    target: dId("f2"),
    type: "USED_IN_FORMULATION",
    side: "bridge",
    pubmedId: "30987654",
    bridgeNote: BRIDGE_NOTE_FORM,
  },
  // Shuddha Parada (pc2) -> Triphala Churna proxy (d:f3)
  {
    id: "b5",
    source: cId("pc2"),
    target: dId("f3"),
    type: "USED_IN_FORMULATION",
    side: "bridge",
    pubmedId: "32145678",
    bridgeNote: BRIDGE_NOTE_FORM,
  },
];

export const I_NODES: IntegratedGraphNode[] = [...D_NODES, ...C_NODES];
export const I_EDGES: IntegratedGraphEdge[] = [...D_EDGES, ...C_EDGES, ...I_BRIDGE_EDGES];

// Helper: traverse undirected k-hop neighborhood starting from `seedIds`.
export function kHopSubgraph(seedIds: string[], depth: 0 | 1 | 2) {
  const nodeIds = new Set<string>(seedIds);
  let frontier = new Set<string>(seedIds);
  for (let i = 0; i < depth; i++) {
    const next = new Set<string>();
    I_EDGES.forEach((e) => {
      if (frontier.has(e.source) && !nodeIds.has(e.target)) next.add(e.target);
      if (frontier.has(e.target) && !nodeIds.has(e.source)) next.add(e.source);
    });
    next.forEach((id) => nodeIds.add(id));
    frontier = next;
    if (next.size === 0) break;
  }
  const nodes = I_NODES.filter((n) => nodeIds.has(n.id));
  const edges = I_EDGES.filter((e) => nodeIds.has(e.source) && nodeIds.has(e.target));
  return { nodes, edges };
}

// Quick groups for "Start from" selectors
export const STARTERS = {
  diseases: I_NODES.filter((n) => n.type === "Disease"),
  compounds: I_NODES.filter((n) => n.type === "Compound"),
  purified: I_NODES.filter((n) => n.type === "PurifiedCompound"),
  formulations: I_NODES.filter((n) => n.type === "Formulation"),
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

