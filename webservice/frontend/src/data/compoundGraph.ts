// Compound-ShodhanaKG sample graph data.

export type CompoundNodeType =
  | "Compound"
  | "ShodhanaMethod"
  | "ToxicSubstance"
  | "PurifiedCompound"
  | "ShodhanaType"
  | "Media"
  | "ConfirmationStudy"
  | "Indication"
  | "AyurvedicFormulation"
  | "Process";

export type CompoundEdgeType =
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
  | "USED_FOR_TREATMENT"
  | "USED_IN_FORMULATION"
  | "TREATS"
  | "CONFIRMS_SAFETY_OF";

export interface CompoundGraphNode {
  id: string;
  label: string;
  type: CompoundNodeType;
  description: string;
  [k: string]: unknown;
}

export interface CompoundGraphEdge {
  id: string;
  source: string;
  target: string;
  type: CompoundEdgeType;
  procedure?: string;       // UNDERGOES, USES_MEDIA
  result?: string;          // CONFIRMS_SAFETY_OF
  mediaUsed?: string;       // CLASSIFIED_AS
  pubmedId?: string;
  [k: string]: unknown;
}

export const C_NODE_TYPE_LABEL: Record<CompoundNodeType, string> = {
  Compound: "Compound",
  ShodhanaMethod: "Shodhana Method",
  ToxicSubstance: "Toxic Substance",
  PurifiedCompound: "Purified Compound",
  ShodhanaType: "Shodhana Type",
  Media: "Media",
  ConfirmationStudy: "Confirmation Study",
  Indication: "Indication",
  AyurvedicFormulation: "Ayurvedic Formulation",
  Process: "Process",
};

export const C_EDGE_TYPE_LABEL: Record<CompoundEdgeType, string> = {
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
  USED_FOR_TREATMENT: "USED_FOR_TREATMENT",
  USED_IN_FORMULATION: "USED_IN_FORMULATION",
  TREATS: "TREATS",
  CONFIRMS_SAFETY_OF: "CONFIRMS_SAFETY_OF",
};

// Restrained earthy palette
export const C_NODE_COLORS: Record<CompoundNodeType, string> = {
  Compound:           "hsl(120, 25%, 50%)", // green
  ShodhanaMethod:     "hsl(28, 75%, 55%)",  // orange
  ToxicSubstance:     "hsl(0, 70%, 55%)",   // red
  PurifiedCompound:   "hsl(205, 65%, 55%)", // blue
  ShodhanaType:       "hsl(270, 60%, 60%)", // purple
  Media:              "hsl(180, 40%, 50%)", // teal
  ConfirmationStudy:  "hsl(48, 85%, 60%)",  // gold
  Indication:         "hsl(4, 56%, 48%)",   // terracotta (matches disease)
  AyurvedicFormulation: "hsl(320, 44%, 68%)", // orchid (matches formulation)
  Process:            "hsl(35, 30%, 50%)",  // brown
};

export const C_EDGE_COLORS: Record<CompoundEdgeType, string> = {
  UNDERGOES:           "hsl(28, 75%, 55%)",
  CONTAINS:            "hsl(0, 70%, 55%)",
  USES_MEDIA:          "hsl(180, 40%, 50%)",
  PRODUCES:            "hsl(205, 65%, 55%)",
  DETOXIFIES:          "hsl(0, 40%, 60%)",
  VALIDATED_BY:        "hsl(48, 85%, 60%)",
  CLASSIFIED_AS:       "hsl(270, 40%, 50%)",
  FOLLOWED_BY:         "hsl(35, 30%, 50%)",
  INVOLVES_PROCESS:    "hsl(35, 30%, 50%)",
  DERIVED_FROM:        "hsl(120, 25%, 50%)",
  USED_FOR_TREATMENT:  "hsl(4, 56%, 48%)",
  USED_IN_FORMULATION: "hsl(320, 44%, 68%)",
  TREATS:              "hsl(4, 56%, 48%)",
  CONFIRMS_SAFETY_OF:  "hsl(48, 85%, 60%)",
};
