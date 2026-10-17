export interface DisplayProperty { key: string; label: string; value: unknown }
export interface ConversationContext {
  version: 1 | 2;
  subject?: { id: string; name: string; type: string };
  last_question?: string;
  result_type?: string;
  requested_result_types?: string[];
  constraints?: Array<{ id?: string; name: string; type: string }>;
  resolved_entities?: Array<{ id: string; name: string; type: string }>;
  intent?: string;
  property?: string;
  pending_clarification?: { result_type?: string; property?: string };
  pending_question?: string;
}
export interface EntityCandidate {
  id: string;
  label: string;
  type: string;
  result_type?: string;
  mention?: string;
  match_method: string;
  source: string;
}
export interface EntityResolution {
  mention: string;
  normalized: string;
  outcome: 'resolved' | 'ambiguous' | 'broader' | 'unmatched';
  candidates: EntityCandidate[];
}
export interface EntityClarification { requested_result_type?: string; candidates: EntityCandidate[] }
export interface ResultPagination { offset: number; limit: number; total: number; has_more: boolean; next_offset: number | null }

export interface PublicationReference {
  raw: string;
  identifier: string;
  namespace: 'PMID' | 'DOI' | 'AYUSH' | null;
  status: 'verified' | 'declared' | 'unclassified' | 'conflict';
  resolution_status: 'confirmed' | 'not_checked' | 'unresolved';
  catalog_version: string;
  source?: string;
  verified_at?: string;
  record_url?: string;
}

export interface ApiNode {
  id: string;
  label: string;
  type: string;
  description?: string;
  [key: string]: unknown;
}

export interface ApiEdge {
  [key: string]: unknown;
  id: string;
  source: string;
  target: string;
  type: string;
  properties?: Record<string, unknown>;
  display_properties?: DisplayProperty[];
  treatment_phase?: string | null;
  raw_type?: string;
  references?: PublicationReference[];
  reference?: string;
  pubmedId?: string;
  adjuvant?: string;
  dose?: string;
  duration?: string;
  outputMeasure?: string;
  procedure?: string;
  result?: string;
  mediaUsed?: string;
  bridgeNote?: string;
}

export interface ApiGraphResponse {
  ok: boolean;
  nodes: ApiNode[];
  edges: ApiEdge[];
  meta?: {
    depth: number;
    truncated: boolean;
    totalNodes?: number;
    totalEdges?: number;
    queryMs?: number;
    cursor?: string | null;
    hasMore?: boolean;
    appendOnly?: boolean;
    seedKey?: string;
    evidencePaths?: Array<{ nodes: Array<{id: string; label: string; type: string}>; edges: Array<{id: string; type: string; source: string; target: string; properties: Record<string, unknown>; display_properties?: DisplayProperty[]; treatment_phase?: string | null; evidence: Record<string, unknown>}>; evidence_groups?: Array<{edge_ids: string[]; reference: {type: string; value: string} | null}> }>;
    continuations?: Array<{source_type: string; entity: string; result_type: string; offset: number}>;
  };
}

export interface ApiSearchResponse {
  ok: boolean;
  nodes: ApiNode[];
}

export interface ApiHealthResponse {
  ok: boolean;
  service: string;
  version: string;
  arcade?: { connected: boolean };
}

export interface ApiStatsResponse {
  ok: boolean;
  rows: Record<string, unknown>[];
  total?: number;
  limit?: number;
  offset?: number;
}

export interface ApiNodeResponse {
  ok: boolean;
  node: ApiNode;
  neighbors: ApiNode[];
  edges?: ApiEdge[];
  meta?: { truncated: boolean; neighborLimit: number };
  evidence: { edgeId: string; references?: PublicationReference[]; pubmedId?: string; reference?: string }[];
}

export type Domain = "disease" | "compound" | "integrated";

export type CategoryType = 
  // Disease domain
  | "Disease" | "Symptom" | "AyurvedicDiagnosis" | "Dosha" | "Therapy" | "Formulation" | "AssessmentParameter"
  // Compound domain
  | "Compound" | "ShodhanaMethod" | "ToxicSubstance" | "PurifiedCompound" | "ShodhanaType" | "Media" | "ConfirmationStudy" | "Process";

// Provenance subgraph types for Diseaseâ†’Therapyâ†’Formulation paths
export interface ProvenancePathNode {
  id: string;
  label: string;
  type: string;
  description?: string | null;
}

export interface ProvenancePathEdge {
  id: string;
  type: string;
  properties?: Record<string, unknown>;
  display_properties?: DisplayProperty[];
  treatment_phase?: string | null;
  raw_type?: string;
  references?: PublicationReference[];
  reference?: string;
  pubmedId?: string;
  dose?: string | null;
  duration?: string | null;
  adjuvant?: string | null;
}

export interface ProvenancePath {
  disease: ProvenancePathNode;
  edge1: Omit<ProvenancePathEdge, 'dose' | 'duration' | 'adjuvant'>;
  therapy: ProvenancePathNode;
  edge2: ProvenancePathEdge;
  formulation: ProvenancePathNode;
  provenance: {
    properties?: Record<string, unknown>;
  display_properties?: DisplayProperty[];
  treatment_phase?: string | null;
  raw_type?: string;
  references?: PublicationReference[];
    reference?: string;
    pubmedId?: string;
    confidence: 'high';
  };
}

export interface ProvenanceSubgraphResponse {
  ok: boolean;
  paths: ProvenancePath[];
  meta?: {
    totalPaths: number;
    uniqueReferences?: PublicationReference[];
    uniquePubmedIds: string[];
    queryMs: number;
    diseaseId: string;
  };
}

// Provenance subgraph types for Compound→ShodhanaMethod→Media paths
export interface CompoundProvenancePath {
  compound: ProvenancePathNode;
  edge1: {
    id: string;
    type: string;
    procedure?: string | null;
    properties?: Record<string, unknown>;
  display_properties?: DisplayProperty[];
  treatment_phase?: string | null;
  raw_type?: string;
  references?: PublicationReference[];
    pubmedId?: string | null;
    doi?: string | null;
    ayushId?: string | null;
  };
  shodhanaMethod: ProvenancePathNode;
  edge2: {
    id: string;
    type: string;
    procedure?: string | null;
    result?: string | null;
    properties?: Record<string, unknown>;
  display_properties?: DisplayProperty[];
  treatment_phase?: string | null;
  raw_type?: string;
  references?: PublicationReference[];
    pubmedId?: string | null;
    doi?: string | null;
    ayushId?: string | null;
  };
  media: ProvenancePathNode;
  provenance: {
    properties?: Record<string, unknown>;
  display_properties?: DisplayProperty[];
  treatment_phase?: string | null;
  raw_type?: string;
  references?: PublicationReference[];
    reference?: string;
    pubmedId?: string;
    confidence: 'high';
  };
}

export interface CompoundProvenanceResponse {
  ok: boolean;
  paths: CompoundProvenancePath[];
  meta?: {
    totalPaths: number;
    uniqueReferences?: PublicationReference[];
    uniquePubmedIds?: string[];
    queryMs: number;
    compoundId: string;
  };
}

export type DiseaseTherapyEdgeType = 'PRETREATED_BY' | 'POSTTREATED_BY' | 'TREATED_BY';
export type TherapyFormulationEdgeType = 'USES_FORMULATION';

// Chatbot Response Backend Types
export type ChatStatus = 'error' | 'success' | 'no_data' | 'needs_clarification' | 'unsupported' | 'entity_not_found' | 'llm_error' | 'grounding_error' | 'network_error';

export interface OrchestratorRouting {
  domain: string;
  intent: string;
  entity: string;
  entity_type: string;
  endpoint: string;
  confidence: number;
  needs_clarification: boolean;
}

export interface OrchestratorRetrieval {
  status: string;
  entity?: { name: string; type?: string };
  path: string[];
  results?: Array<Record<string, unknown>>;
}

export interface OrchestratorContext {
  conversation_context?: ConversationContext;
  status: string;
  question: string;
  routing?: OrchestratorRouting;
  retrieval?: OrchestratorRetrieval;
  message?: string;
  reason?: string;
}

export interface ChatbotBackendResponse {
  resolution?: EntityResolution[];
  clarification?: EntityClarification;
  conversation_context?: ConversationContext;
  pagination?: ResultPagination;
  question: string;
  answer: string;
  status: ChatStatus | 'network_error' | 'llm_error' | 'grounding_error' | 'error';
  context?: OrchestratorContext;
  message?: string;
  answer_intro?: string;
  answer_heading?: string;
  answer_results?: AnswerResult[];
  answer_blocks?: AnswerBlock[];
  citations?: AnswerCitation[];
  generation?: { mode: 'hybrid' | 'fallback' | 'deterministic'; fallback_reason: string | null; latency_ms: number };
}

export interface AnswerResult { id: string; label: string; fact_ids?: string[]; citation_ids: string[]; record_indices: number[] }
export interface EvidenceEntity { type: string; label: string; id?: string }

export interface AnswerBlock { text: string; citation_ids: string[] }
export interface AnswerCitation {
  id: string;
  fact_ids: string[];
  record_indices: number[];
  statement: string;
  attribution: 'per_edge' | 'record';
  entities?: EvidenceEntity[];
  relationships?: string[];
  evidence_groups?: Array<{ edge_ids: string[]; reference: { type: string; value: string } | null }>;
  scopes: Array<{ display_properties?: DisplayProperty[]; treatment_phase?: string | null; description: string; identifiers: { pubmed_id?: string; doi?: string; ayush_id?: string; unclassified_reference?: string; references?: PublicationReference[] }; properties: Record<string, unknown>; relationship?: string; entities?: EvidenceEntity[]; edge_id?: string; edge_type?: string; source_id?: string; target_id?: string }>;
}

export interface ChatHistoryTurn {
  role: "user" | "bot";
  text: string;
}

// Chatbot UI Types
export interface ChatMessage {
  resolution?: EntityResolution[];
  clarification?: EntityClarification;
  conversation_context?: ConversationContext;
  pagination?: ResultPagination;
  role: 'user' | 'bot';
  text: string;
  isStreaming?: boolean;
  status?: ChatStatus;
  context?: OrchestratorContext;
  error?: string;
  timestamp?: number;
  answer_intro?: string;
  answer_heading?: string;
  answer_results?: AnswerResult[];
  answer_blocks?: AnswerBlock[];
  citations?: AnswerCitation[];
}


