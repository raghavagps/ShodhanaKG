import { describe, it, expect } from 'vitest';
import { ENTITY_TYPES, entityGraphUrl, entityType, entityDomain } from './entityNavigation';
import { parseGraphParams } from './api';
describe('Typed graph navigation', () => {
  it.each(ENTITY_TYPES)('preserves %s identity and type without a URL fragment', type => {
    const url = new URL(entityGraphUrl({ id: '#45:124', type }), 'http://localhost');
    expect(url.hash).toBe('');
    expect(url.searchParams.get('node')).toBe('#45:124');
    expect(url.searchParams.get('type')).toBe(type);
    expect(parseGraphParams(url.searchParams).seedIds).toEqual(['#45:124']);
    expect(url.pathname).toContain(`/explorer/${entityDomain(type)}/graph`);
  });
  it('normalizes schema types without changing identity', () => {
    expect(entityType('Assessment Parameters')).toBe('AssessmentParameter');
    expect(entityType('Purified_Compound')).toBe('PurifiedCompound');
    expect(entityType('Symptoms')).toBe('Symptom');
  });
});
