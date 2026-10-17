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

// ---- Sample data ----
export const C_NODES: CompoundGraphNode[] = [
  // Compounds (raw)
  { id: "c1", label: "Vatsanabha", type: "Compound", description: "Aconitum ferox root â€” highly toxic before purification, used after Shodhana." },
  { id: "c2", label: "Parada", type: "Compound", description: "Mercury (Hg) â€” a key Rasa dravya undergoing extensive Shodhana." },
  { id: "c3", label: "Bhallataka", type: "Compound", description: "Semecarpus anacardium nut â€” vesicant before purification." },

  // Toxic substances
  { id: "tx1", label: "Aconitine", type: "ToxicSubstance", description: "Cardiotoxic alkaloid present in raw Vatsanabha." },
  { id: "tx2", label: "Free Mercury", type: "ToxicSubstance", description: "Elemental mercury â€” toxic in unprocessed form." },
  { id: "tx3", label: "Bhilawanol", type: "ToxicSubstance", description: "Vesicant phenolic oil in raw Bhallataka pericarp." },

  // Shodhana methods
  { id: "sm1", label: "Godugdha Shodhana", type: "ShodhanaMethod", description: "Purification by boiling in cow's milk." },
  { id: "sm2", label: "Ashta Samskara", type: "ShodhanaMethod", description: "Eight-step classical purification of Parada." },
  { id: "sm3", label: "Ishtika Choorna Shodhana", type: "ShodhanaMethod", description: "Bhallataka rubbed with brick powder to remove vesicant oil." },
  { id: "sm4", label: "Swedana", type: "ShodhanaMethod", description: "Steaming/boiling step in classical Shodhana protocols." },

  // Purified compounds
  { id: "pc1", label: "Shuddha Vatsanabha", type: "PurifiedCompound", description: "Purified aconite suitable for therapeutic use." },
  { id: "pc2", label: "Shuddha Parada", type: "PurifiedCompound", description: "Purified mercury used in Rasaushadhi." },
  { id: "pc3", label: "Shuddha Bhallataka", type: "PurifiedCompound", description: "Purified marking nut, free of vesicant oil." },

  // Shodhana types
  { id: "st1", label: "Samunknowna Shodhana", type: "ShodhanaType", description: "General purification applicable to a class of substances." },
  { id: "st2", label: "Vishesha Shodhana", type: "ShodhanaType", description: "Specific purification for an individual substance." },

  // Media
  { id: "md1", label: "Godugdha", type: "Media", description: "Cow's milk used as a Shodhana medium." },
  { id: "md2", label: "Triphala Kwatha", type: "Media", description: "Decoction of Triphala used as a Shodhana medium." },
  { id: "md3", label: "Ishtika Choorna", type: "Media", description: "Powdered brick used as an absorptive medium." },

  // Confirmation studies
  { id: "cs1", label: "HPTLC Aconitine Study 2018", type: "ConfirmationStudy", description: "Demonstrated >90% reduction in aconitine after Godugdha Shodhana." },
  { id: "cs2", label: "Mercury Speciation Study 2020", type: "ConfirmationStudy", description: "ICP-MS showed bound mercury species post Ashta Samskara." },
  { id: "cs3", label: "Bhilawanol Reduction 2019", type: "ConfirmationStudy", description: "GC-MS confirmed vesicant removal after Ishtika Choorna treatment." },

  // Indications
  { id: "ds1", label: "Amavata", type: "Indication", description: "Rheumatic disorder treated by Shuddha Vatsanabha containing formulations." },
  { id: "ds2", label: "Kushtha", type: "Indication", description: "Skin disorders treated by Shuddha Bhallataka preparations." },

  // Ayurvedic Formulations
  { id: "fm1", label: "Mahavatavidhwansa Rasa", type: "AyurvedicFormulation", description: "Classical Rasaushadhi using Shuddha Vatsanabha and Shuddha Parada." },
  { id: "fm2", label: "Bhallataka Rasayana", type: "AyurvedicFormulation", description: "Rasayana preparation using Shuddha Bhallataka." },

  // Processes
  { id: "p1", label: "Heating", type: "Process", description: "Controlled application of heat during Swedana." },
  { id: "p2", label: "Mardana", type: "Process", description: "Trituration process used in Shodhana." },
  { id: "p3", label: "Filtration", type: "Process", description: "Separation of solid residue from medium post-treatment." },
];

export const C_EDGES: CompoundGraphEdge[] = [
  // Compound -> ShodhanaMethod
  { id: "ce1", source: "c1", target: "sm1", type: "UNDERGOES", procedure: "Boil in Godugdha for 3 hours" },
  { id: "ce2", source: "c2", target: "sm2", type: "UNDERGOES", procedure: "Sequential 8-step Samskara protocol" },
  { id: "ce3", source: "c3", target: "sm3", type: "UNDERGOES", procedure: "Rub with brick powder until oil exhausts" },

  // Compound -> ToxicSubstance
  { id: "ce4", source: "c1", target: "tx1", type: "CONTAINS" },
  { id: "ce5", source: "c2", target: "tx2", type: "CONTAINS" },
  { id: "ce6", source: "c3", target: "tx3", type: "CONTAINS" },

  // ShodhanaMethod -> Media
  { id: "ce7", source: "sm1", target: "md1", type: "USES_MEDIA", procedure: "Continuous Swedana" },
  { id: "ce8", source: "sm2", target: "md2", type: "USES_MEDIA", procedure: "Mardana with Triphala Kwatha" },
  { id: "ce9", source: "sm3", target: "md3", type: "USES_MEDIA", procedure: "Dry Mardana with Ishtika Choorna" },

  // ShodhanaMethod -> PurifiedCompound (PRODUCES)
  { id: "ce10", source: "sm1", target: "pc1", type: "PRODUCES" },
  { id: "ce11", source: "sm2", target: "pc2", type: "PRODUCES" },
  { id: "ce12", source: "sm3", target: "pc3", type: "PRODUCES" },

  // ShodhanaMethod -> ToxicSubstance (DETOXIFIES)
  { id: "ce13", source: "sm1", target: "tx1", type: "DETOXIFIES" },
  { id: "ce14", source: "sm2", target: "tx2", type: "DETOXIFIES" },
  { id: "ce15", source: "sm3", target: "tx3", type: "DETOXIFIES" },

  // ShodhanaMethod -> ConfirmationStudy
  { id: "ce16", source: "sm1", target: "cs1", type: "VALIDATED_BY", pubmedId: "30123456" },
  { id: "ce17", source: "sm2", target: "cs2", type: "VALIDATED_BY", pubmedId: "32145678" },
  { id: "ce18", source: "sm3", target: "cs3", type: "VALIDATED_BY", pubmedId: "31234567" },

  // ShodhanaMethod -> ShodhanaType
  { id: "ce19", source: "sm1", target: "st1", type: "CLASSIFIED_AS", mediaUsed: "Godugdha" },
  { id: "ce20", source: "sm2", target: "st2", type: "CLASSIFIED_AS", mediaUsed: "Multiple" },
  { id: "ce21", source: "sm3", target: "st2", type: "CLASSIFIED_AS", mediaUsed: "Ishtika Choorna" },

  // ShodhanaMethod -> ShodhanaMethod (FOLLOWED_BY)
  { id: "ce22", source: "sm4", target: "sm1", type: "FOLLOWED_BY" },

  // ShodhanaMethod -> Process (INVOLVES_PROCESS)
  { id: "ce23", source: "sm1", target: "p1", type: "INVOLVES_PROCESS" },
  { id: "ce24", source: "sm2", target: "p2", type: "INVOLVES_PROCESS" },
  { id: "ce25", source: "sm3", target: "p2", type: "INVOLVES_PROCESS" },

  // PurifiedCompound -> Compound (DERIVED_FROM)
  { id: "ce26", source: "pc1", target: "c1", type: "DERIVED_FROM" },
  { id: "ce27", source: "pc2", target: "c2", type: "DERIVED_FROM" },
  { id: "ce28", source: "pc3", target: "c3", type: "DERIVED_FROM" },

  // PurifiedCompound -> Disease (USED_FOR_TREATMENT)
  { id: "ce29", source: "pc1", target: "ds1", type: "USED_FOR_TREATMENT" },
  { id: "ce30", source: "pc3", target: "ds2", type: "USED_FOR_TREATMENT" },

  // PurifiedCompound -> Formulation (USED_IN_FORMULATION)
  { id: "ce31", source: "pc1", target: "fm1", type: "USED_IN_FORMULATION" },
  { id: "ce32", source: "pc2", target: "fm1", type: "USED_IN_FORMULATION" },
  { id: "ce33", source: "pc3", target: "fm2", type: "USED_IN_FORMULATION" },

  // ConfirmationStudy -> PurifiedCompound (CONFIRMS_SAFETY_OF)
  { id: "ce34", source: "cs1", target: "pc1", type: "CONFIRMS_SAFETY_OF", result: "Aconitine reduced by 92%", pubmedId: "30123456" },
  { id: "ce35", source: "cs2", target: "pc2", type: "CONFIRMS_SAFETY_OF", result: "Mercury bound as HgS species", pubmedId: "32145678" },
  { id: "ce36", source: "cs3", target: "pc3", type: "CONFIRMS_SAFETY_OF", result: "Bhilawanol below detection limit", pubmedId: "31234567" },

  // Process -> Media
  { id: "ce37", source: "p1", target: "md1", type: "USES_MEDIA", procedure: "Heating in milk bath" },
  { id: "ce38", source: "p2", target: "md3", type: "USES_MEDIA", procedure: "Mardana with brick powder" },

  // Process -> ConfirmationStudy
  { id: "ce39", source: "p2", target: "cs3", type: "VALIDATED_BY", pubmedId: "31234567" },

  // Process -> Process (FOLLOWED_BY)
  { id: "ce40", source: "p1", target: "p2", type: "FOLLOWED_BY" },
  { id: "ce41", source: "p2", target: "p3", type: "FOLLOWED_BY" },

  // Process -> PurifiedCompound (PRODUCES)
  { id: "ce42", source: "p3", target: "pc3", type: "PRODUCES" },
];

