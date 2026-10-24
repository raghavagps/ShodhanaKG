import { describe, it, expect } from 'vitest';
import type { ProvenancePath } from '@/lib/types';

describe('Provenance Types', () => {
  it('should validate ProvenancePath structure', () => {
    const path: ProvenancePath = {
      disease: {
        id: '36',
        label: 'Eczema',
        type: 'Disease',
        description: null
      },
      edge1: {
        id: '#70:0',
        type: 'PRETREATED_BY',
        pubmedId: '23723667'
      },
      therapy: {
        id: '1155',
        label: 'Abhyanga',
        type: 'Therapy',
        description: 'whole body oil massage'
      },
      edge2: {
        id: '#84:14',
        type: 'USES_FORMULATION',
        pubmedId: '23723667',
        dose: null,
        duration: '1 Day',
        adjuvant: null
      },
      formulation: {
        id: '1356',
        label: 'Bala Taila',
        type: 'Formulation',
        description: null
      },
      provenance: {
        pubmedId: '23723667',
        confidence: 'high'
      }
    };

    expect(path.disease.type).toBe('Disease');
    expect(path.edge1.type).toBe('PRETREATED_BY');
    expect(path.edge2.type).toBe('USES_FORMULATION');
    expect(path.provenance.pubmedId).toBe('23723667');
    expect(path.provenance.confidence).toBe('high');
  });
});
