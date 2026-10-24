import { it, expect } from 'vitest';
import cytoscape from 'cytoscape';
import { applyGraphFocus } from './graphFocus';
it('never dims any Disease edge during selection, provenance or expansion', () => {
  const cy = cytoscape({ headless: true, elements: [
    ...['a','b','c','d'].map(id => ({ data: { id } })),
    { data: { id: 'incident', source: 'a', target: 'b' } },
    { data: { id: 'unrelated', source: 'c', target: 'd' } },
  ] });
  for (const selected of ['a', 'c', undefined]) {
    applyGraphFocus(cy, selected, [{ edge1Id: 'incident', edge2Id: 'incident' }], false);
    expect(cy.edges('.faded,.provenance-highlight-dim').length).toBe(0);
    expect(cy.nodes('.faded').length).toBe(0);
  }
  cy.add({ data: { id: 'expanded', source: 'b', target: 'd' } });
  applyGraphFocus(cy, 'a', [], false);
  expect(cy.edges('.faded,.provenance-highlight-dim').length).toBe(0);
  expect(cy.$id('expanded').data()).toMatchObject({ source: 'b', target: 'd' });
  cy.destroy();
});
it('keeps incident edges visible independently of provenance, expansion and direction', () => {
  const cy = cytoscape({ headless: true, elements: [
    ...['a','b','c','isolated'].map(id => ({ data: { id } })),
    { data: { id: 'in', source: 'b', target: 'a' } },
    { data: { id: 'out', source: 'a', target: 'c' } },
    { data: { id: 'other', source: 'b', target: 'c' } },
  ] });
  const before = cy.edges().map(e => e.data());
  applyGraphFocus(cy, 'a', [{ edge1Id: 'other', edge2Id: 'other' }]);
  for (const id of ['in', 'out']) {
    expect(cy.$id(id).hasClass('highlighted')).toBe(true);
    expect(cy.$id(id).hasClass('provenance-highlight-dim')).toBe(false);
    expect(cy.$id(id).hasClass('faded')).toBe(false);
  }
  cy.remove(cy.$id('out'));
  applyGraphFocus(cy, 'a');
  expect(cy.$id('in').hasClass('highlighted')).toBe(true);
  cy.add({ data: before[1] });
  applyGraphFocus(cy, 'a');
  expect(cy.edges().map(e => e.data()).sort((a,b) => a.id.localeCompare(b.id))).toEqual(before.sort((a,b) => a.id.localeCompare(b.id)));
  applyGraphFocus(cy);
  expect(cy.$('.highlighted,.faded,.provenance-highlight-dim').length).toBe(0);
  expect(cy.$id('isolated').length).toBe(1);
  cy.destroy();
});
