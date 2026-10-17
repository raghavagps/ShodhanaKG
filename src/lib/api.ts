import { useQuery } from "@tanstack/react-query";
import { useState, useEffect } from "react";
import type { ApiNode, ApiSearchResponse, ApiGraphResponse, ApiEdge, Domain, CategoryType, ProvenancePath, ProvenanceSubgraphResponse, CompoundProvenancePath, CompoundProvenanceResponse } from "./types";

const API_BASE = import.meta.env.VITE_API_BASE || import.meta.env.BASE_URL.replace(/\/$/, "");

export async function fetchApi<T>(path: string): Promise<T> {
  const res = await fetch(`${API_BASE}/api/${path}`);
  if (!res.ok) throw new Error(`API ${res.status} ${res.statusText}`);
  const body = await res.json();
  if (body && typeof body === "object" && "ok" in body && !body.ok) {
    throw new Error(body.error?.message || `API request failed`);
  }
  return body as T;
}

function nodeMatches(q: string, label: string): boolean {
  return label.toLowerCase().includes(q.toLowerCase());
}

export function searchFallback<T extends ApiNode>(
  nodes: T[], q: string, limit: number
): T[] {
  const trimmed = q.trim();
  if (!trimmed) return [];
  return nodes.filter((n) => nodeMatches(trimmed, n.label)).slice(0, limit);
}

export async function searchApi(domain: Domain, q: string, limit = 30, type?: string): Promise<ApiNode[]> {
  const params = new URLSearchParams({ domain, q, limit: String(limit) });
  if (type) params.set("type", type);
  const res = await fetchApi<ApiSearchResponse>(`search.php?${params.toString()}`);
  return res.nodes || [];
}

export async function searchWithFallback<T extends ApiNode>(
  domain: Domain,
  q: string,
  limit: number,
  fallbackNodes: T[]
): Promise<T[]> {
  const trimmed = q.trim();
  if (!trimmed) return [];
  try {
    return (await searchApi(domain, trimmed, limit)) as T[];
  } catch {
    return searchFallback(fallbackNodes, trimmed, limit);
  }
}

export function useDebounce<T>(value: T, delay: number): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(timer);
  }, [value, delay]);
  return debounced;
}

export function useSearch<T extends ApiNode>(
  domain: Domain,
  query: string,
  limit: number,
  fallbackNodes?: T[],
  type?: string
) {
  const debouncedQuery = useDebounce(query, 250);

  return useQuery<T[]>({
    queryKey: ["search", domain, debouncedQuery, limit, type] as const,
    queryFn: async () => {
      const trimmed = debouncedQuery.trim();
      if (!trimmed) return [];
      try {
        const results = await searchApi(domain, trimmed, limit, type);
        return results as T[];
      } catch {
        const fallback = fallbackNodes || [];
        const typedFallback = type
          ? fallback.filter((n) => n.type.toLowerCase() === type.toLowerCase())
          : fallback;
        return searchFallback(typedFallback, trimmed, limit);
      }
    },
    enabled: debouncedQuery.trim().length > 0,
    staleTime: 30000,
    placeholderData: (prev) => prev,
  });
}

export type IntegratedMode = 'bridge' | '1hop' | 'paths';

export async function graphApi(
  domain: Domain,
  seeds: string[],
  depth: number,
  limitNodes: number = 120,
  limitEdges: number = 300,
  integratedMode: IntegratedMode = '1hop'
): Promise<{ nodes: ApiNode[]; edges: ApiEdge[]; meta?: ApiGraphResponse["meta"] }> {
  const seedsParam = seeds.join(",");
  const params = new URLSearchParams({
    domain,
    seeds: seedsParam,
    depth: String(depth),
    limitNodes: String(limitNodes),
    limitEdges: String(limitEdges),
  });
  if (domain === 'integrated') params.set("integratedMode", integratedMode);
  const res = await fetchApi<ApiGraphResponse>(`graph.php?${params.toString()}`);
  return { nodes: res.nodes || [], edges: res.edges || [], meta: res.meta };
}

export function useGraph(
  domain: Domain,
  seeds: string[],
  depth: number = 1,
  limitNodes: number = 120,
  limitEdges: number = 300,
  integratedMode: IntegratedMode = '1hop'
) {
  return useQuery({
    queryKey: ["graph", domain, seeds, depth, limitNodes, limitEdges, integratedMode] as const,
    queryFn: async () => {
      if (!seeds || seeds.length === 0) return { nodes: [], edges: [], meta: undefined };
      return graphApi(domain, seeds, depth, limitNodes, limitEdges, integratedMode);
    },
    enabled: seeds.length > 0,
    staleTime: 60000,
    placeholderData: (prev) => prev,
  });
}

export interface KgStats {
  totalNodes: number;
  totalRelations: number;
  totalStudies: number;
}

export async function kgStatsApi(): Promise<KgStats> {
  const res = await fetchApi<{ ok: boolean } & KgStats>("kg-stats.php");
  return {
    totalNodes: res.totalNodes ?? 0,
    totalRelations: res.totalRelations ?? 0,
    totalStudies: res.totalStudies ?? 0,
  };
}

export function useKgStats() {
  return useQuery({
    queryKey: ["kg-stats"] as const,
    queryFn: kgStatsApi,
    staleTime: 300000, // 5 min â€” these change rarely
    placeholderData: (prev) => prev,
  });
}

export async function countsApi(domain: Domain): Promise<Record<string, number>> {
  const res = await fetchApi<{ ok: boolean; counts: Record<string, number> }>(`counts.php?domain=${domain}`);
  return res.counts || {};
}

export function useCounts(domain: Domain) {
  return useQuery({
    queryKey: ["counts", domain] as const,
    queryFn: () => countsApi(domain),
    staleTime: 60000,
  });
}

export interface CategoryStatsRow {
  id: string;
  name: string;
  description: string;
  diseases?: number;
  total: number;
  [key: string]: unknown;
}

export interface CategoryStatsResponse {
  rows: CategoryStatsRow[];
  total: number;
  limit: number;
  offset: number;
}

export async function categoryStatsApi(
  domain: Domain,
  category: CategoryType,
  q: string = "",
  sort: string = "name",
  dir: "asc" | "desc" = "asc",
  limit: number = 25,
  offset: number = 0
): Promise<CategoryStatsResponse> {
  const params = new URLSearchParams({
    domain,
    category,
    q,
    sort,
    dir,
    limit: String(limit),
    offset: String(offset),
  });
  return fetchApi<CategoryStatsResponse>(`category-stats.php?${params.toString()}`);
}

export interface ParsedGraphParams {
  seedIds: string[];
  filterMap: Record<string, string[]>;
  depth: number;
}

export function parseGraphParams(
  searchParams: URLSearchParams,
  defaultDepth: number = 1
): ParsedGraphParams {
  const filterRaw = searchParams.get("filter");
  const seedsRaw = searchParams.get("seeds");
  const nodeRaw = searchParams.get("node");
  const diseaseRaw = searchParams.get("disease");
  const compoundRaw = searchParams.get("compound");

  const filterMap: Record<string, string[]> = {};

  if (filterRaw) {
    filterRaw.split(",").forEach((pair) => {
      const colonIdx = pair.indexOf(":");
      if (colonIdx > 0 && colonIdx < pair.length - 1) {
        const type = pair.substring(0, colonIdx);
        const id = pair.substring(colonIdx + 1);
        if (!filterMap[type]) filterMap[type] = [];
        filterMap[type].push(id);
      }
    });
  }

  const seedIds: string[] = [];
  if (seedsRaw) {
    seedsRaw.split(",").forEach((s) => {
      const id = s.trim();
      if (id && !seedIds.includes(id)) seedIds.push(id);
    });
  }
  if (nodeRaw && !seedIds.includes(nodeRaw)) seedIds.push(nodeRaw);
  if (diseaseRaw && !seedIds.includes(diseaseRaw)) seedIds.push(diseaseRaw);
  if (compoundRaw && !seedIds.includes(compoundRaw)) seedIds.push(compoundRaw);

  Object.values(filterMap).forEach((ids) => {
    ids.forEach((id) => {
      if (!seedIds.includes(id)) seedIds.push(id);
    });
  });

  const depth = (() => {
    const depthStr = searchParams.get("depth");
    if (depthStr === null) return defaultDepth;
    const d = Number(depthStr);
    return d === 0 || d === 1 || d === 2 ? (d as 0 | 1 | 2) : defaultDepth;
  })();

  return { seedIds, filterMap, depth };
}

export function useCategoryStats(
  domain: Domain,
  category: CategoryType,
  q: string = "",
  sort: string = "name",
  dir: "asc" | "desc" = "asc",
  limit: number = 25,
  offset: number = 0
) {
  return useQuery({
    queryKey: ["category-stats", domain, category, q, sort, dir, limit, offset] as const,
    queryFn: () => categoryStatsApi(domain, category, q, sort, dir, limit, offset),
    staleTime: 30000,
    placeholderData: (prev) => prev,
  });
}

// Provenance subgraph API: Diseaseâ†’Therapyâ†’Formulation with PMID matching
export async function provenanceSubgraphApi(
  diseaseId: string,
  pubmedId?: string,
  limit: number = 2000,
  reference?: { namespace: "PMID" | "DOI" | "AYUSH"; identifier: string }
): Promise<{ paths: ProvenancePath[]; meta?: ProvenanceSubgraphResponse["meta"] }> {
  const params = new URLSearchParams({ diseaseId });
  if (reference) { params.set("referenceType", reference.namespace); params.set("referenceId", reference.identifier); }
  if (pubmedId) params.set("pubmedId", pubmedId);
  params.set("limit", String(limit));

  const res = await fetchApi<ProvenanceSubgraphResponse>(
    `provenance-subgraph.php?${params.toString()}`
  );
  return { paths: res.paths || [], meta: res.meta };
}

export function useProvenanceSubgraph(
  diseaseId: string | null,
  pubmedId?: string,
  limit: number = 2000
) {
  return useQuery({
    queryKey: ["provenance-subgraph", diseaseId, pubmedId, limit] as const,
    queryFn: async () => {
      if (!diseaseId) return { paths: [] as ProvenancePath[], meta: undefined };
      return provenanceSubgraphApi(diseaseId, pubmedId, limit);
    },
    enabled: !!diseaseId,
    staleTime: 60000,
    placeholderData: (prev) => prev,
  });
}

export async function compoundProvenanceApi(
  compoundId: string,
  limit: number = 2000,
  reference?: { namespace: "PMID" | "DOI" | "AYUSH"; identifier: string }
): Promise<{ paths: CompoundProvenancePath[]; meta?: CompoundProvenanceResponse["meta"] }> {
  const params = new URLSearchParams({ compoundId });
  if (reference) { params.set("referenceType", reference.namespace); params.set("referenceId", reference.identifier); }
  params.set("limit", String(limit));

  const res = await fetchApi<CompoundProvenanceResponse>(
    `compound-provenance.php?${params.toString()}`
  );
  return { paths: res.paths || [], meta: res.meta };
}

export function useCompoundProvenance(
  compoundId: string | null,
  limit: number = 2000
) {
  return useQuery({
    queryKey: ["compound-provenance", compoundId, limit] as const,
    queryFn: async () => {
      if (!compoundId) return { paths: [] as CompoundProvenancePath[], meta: undefined };
      return compoundProvenanceApi(compoundId, limit);
    },
    enabled: !!compoundId,
    staleTime: 60000,
    placeholderData: (prev) => prev,
  });
}

