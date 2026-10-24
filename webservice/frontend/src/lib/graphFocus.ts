import type { Core } from 'cytoscape';

/** Display-only classes. Node and edge identities, endpoints and data are untouched. */
export function applyGraphFocus(cy: Core, selectedId?: string, paths: { edge1Id: string; edge2Id: string }[] = [], dimEdges = true) {
  cy.elements().removeClass('highlighted faded provenance-highlight provenance-highlight-dim');
  const selected = selectedId ? cy.getElementById(selectedId) : cy.collection();
  const evidence = new Set(paths.flatMap(path => [path.edge1Id, path.edge2Id]));
  const neighborhood = selected.closedNeighborhood();
  cy.edges().forEach(edge => {
    if (evidence.has(edge.id())) edge.addClass('provenance-highlight');
    else if (dimEdges && evidence.size && !neighborhood.contains(edge)) edge.addClass('provenance-highlight-dim');
  });
  if (selected.length) {
    const visibleFocus = neighborhood.union(cy.edges('.provenance-highlight')).union(cy.edges('.provenance-highlight').connectedNodes());
    if (dimEdges) cy.elements().not(visibleFocus).addClass('faded');
    neighborhood.addClass('highlighted');
  }
}
