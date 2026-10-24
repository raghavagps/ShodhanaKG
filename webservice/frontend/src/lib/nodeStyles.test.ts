import { describe, it, expect } from 'vitest';
import { NODE_STYLES, NODE_TEXT, nodeStyle, nodeColor } from './nodeStyles';
import { ENTITY_TYPES } from './entityNavigation';
import schema from '@/data/schemaReference.json';
import { entityType } from './entityNavigation';

describe('shared node category styles', () => {
  it('covers every schema category with the restored palette', () => {
    expect(Object.keys(NODE_STYLES).sort()).toEqual([...ENTITY_TYPES].sort());
    expect(schema.vertices.map(v => entityType(v.type)).sort()).toEqual([...ENTITY_TYPES].sort());
    for (const style of Object.values(NODE_STYLES)) expect(Object.keys(style).sort()).toEqual(['accessible', 'normal']);
  });
  it('resolves database aliases without conflating distinct KG categories', () => {
    for (const [alias, type] of [['Symptoms','Symptom'], ['Shodhana_Method','ShodhanaMethod'], ['Assessment Parameters','AssessmentParameter'], ['Ayurvedic_Formulation','AyurvedicFormulation']]) {
      expect(nodeStyle(alias, true)).toEqual(nodeStyle(type, true));
      expect(nodeColor(alias)).toEqual(nodeColor(type));
    }
    expect(nodeStyle('Disease').fill).toEqual(nodeStyle('Indication').fill);
    expect(nodeStyle('Formulation').fill).toEqual(nodeStyle('AyurvedicFormulation').fill);
    expect(nodeStyle('Unknown').fill).toBe('#777777');
  });
  it('retains diagnosis royal purple while restoring original category colors', () => {
    expect(nodeStyle('AyurvedicDiagnosis', true).fill).toBe('#56B4E9');
    expect(nodeStyle('AyurvedicDiagnosis').fill).toBe('hsl(266.5909090909, 35.2%, 49.0196078431%)');
    expect(nodeStyle('Disease').fill).toBe('hsl(4, 56%, 48%)');
    expect(nodeStyle('Compound').fill).toBe('hsl(120, 25%, 50%)');
    expect(nodeStyle('Dosha', true).fill).toBe('#000000');
  });
});
