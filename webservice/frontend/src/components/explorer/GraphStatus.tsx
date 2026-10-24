/** Content for the existing in-graph status box; pagination is not truncation. */
export function GraphStatus({ nodes, edges, meta, loading, error }: {
  nodes: number; edges: number; loading: boolean; error: boolean;
  meta?: { totalNodes?: number; totalEdges?: number; truncated?: boolean; withinBudget?: boolean };
}) {
  const truncated = !loading && !error && meta?.truncated && !meta.withinBudget;
  return <span role="status">
    Nodes {nodes}/{meta?.totalNodes ?? nodes} · Edges {edges}/{meta?.totalEdges ?? edges}
    {truncated && ' · Truncated'}
    {loading && ' · Loading…'}
    {error && ' · Loading failed; retry by reselecting filters.'}
  </span>;
}
