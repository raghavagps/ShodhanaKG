/** Display-only connectivity after visibility filters/caps. Stored direction is unchanged. */
export function connectedGraph<N extends { id: string }, E extends { source: string; target: string }>(nodes: N[], edges: E[], seeds: readonly string[]) {
  const byId = new Set(nodes.map(n => n.id));
  const validEdges = edges.filter(e => byId.has(e.source) && byId.has(e.target));
  const neighbors = new Map<string, string[]>();
  for (const edge of validEdges) {
    neighbors.set(edge.source, [...(neighbors.get(edge.source) ?? []), edge.target]);
    neighbors.set(edge.target, [...(neighbors.get(edge.target) ?? []), edge.source]);
  }
  const reached = new Set(seeds.filter(id => byId.has(id)));
  const queue = [...reached];
  for (let i = 0; i < queue.length; i++) for (const id of neighbors.get(queue[i]) ?? []) {
    if (!reached.has(id)) { reached.add(id); queue.push(id); }
  }
  return { nodes: nodes.filter(n => reached.has(n.id)), edges: validEdges.filter(e => reached.has(e.source) && reached.has(e.target)) };
}
