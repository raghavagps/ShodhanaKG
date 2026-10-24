import { it, expect } from 'vitest';
import { connectedGraph } from './connectedGraph';
const nodes = ['seed','method','media','orphan','x','y','isolated'].map(id => ({ id, label: id }));
const edges = [
  { id: 'a', source: 'seed', target: 'method', properties: { ref: 'unchanged' } },
  { id: 'b', source: 'method', target: 'media' },
  { id: 'c', source: 'x', target: 'y' },
];
it('removes background components and cap-orphaned nodes without changing edges', () => {
  const result = connectedGraph(nodes, edges, ['seed']);
  expect(result.nodes.map(n => n.id)).toEqual(['seed','method','media']);
  expect(result.edges).toEqual(edges.slice(0,2));
  expect(result.edges[0]).toBe(edges[0]);
});
it('recomputes reachability after an edge or node visibility change', () => {
  expect(connectedGraph(nodes, edges.slice(1), ['seed']).nodes.map(n => n.id)).toEqual(['seed']);
  expect(connectedGraph(nodes.filter(n => n.id !== 'method'), edges, ['seed']).edges).toEqual([]);
});
it('keeps explicit isolated seeds and both selected components, using either traversal direction', () => {
  const result = connectedGraph(nodes, edges, ['media','x','isolated']);
  expect(result.nodes.map(n => n.id)).toEqual(['seed','method','media','x','y','isolated']);
  expect(result.edges).toEqual(edges);
});
it('cannot show an old graph under a new or hidden seed', () => {
  expect(connectedGraph(nodes, edges, ['new-selection'])).toEqual({ nodes: [], edges: [] });
  expect(connectedGraph(nodes, edges, [])).toEqual({ nodes: [], edges: [] });
});
