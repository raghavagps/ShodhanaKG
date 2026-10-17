import { describe, expect, it } from 'vitest';
import { referencePresentation } from './referencePresentation';
describe('reference presentation projection',()=>{
  it('hides diagnostics without mutating provenance or meaningful properties',()=>{
    const raw={edge_id:'#3:1',dose:0,result:'Stored result',references:[{namespace:'DOI',identifier:'10.1234/a',status:'declared',resolution_status:'unresolved'}]};
    const projected=referencePresentation(raw);
    expect(JSON.stringify(projected)).not.toMatch(/unresolved|declared/);
    expect(projected).toMatchObject({edge_id:'#3:1',dose:0,result:'Stored result'});
    expect(raw.references[0].resolution_status).toBe('unresolved');
  });
});
