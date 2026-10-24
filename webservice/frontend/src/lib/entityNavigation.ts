import type { ApiNode, Domain } from './types';

export const ENTITY_TYPES = ['Disease', 'Dosha', 'AyurvedicDiagnosis', 'Symptom', 'Therapy', 'Formulation', 'AssessmentParameter', 'Compound', 'ShodhanaMethod', 'ToxicSubstance', 'ConfirmationStudy', 'Media', 'PurifiedCompound', 'ShodhanaType', 'Process', 'Indication', 'AyurvedicFormulation'];
export function entityType(type: string): string {
  const compact = type.replace(/[ _]/g, '');
  return ({ Symptoms: 'Symptom', Doshas: 'Dosha', Formulations: 'Formulation', AssessmentParameters: 'AssessmentParameter' } as Record<string, string>)[compact] ?? compact;
}
export function entityDomain(type: string): Domain {
  const normalized = entityType(type);
  return ENTITY_TYPES.slice(0, 7).includes(normalized) ? 'disease' : ENTITY_TYPES.includes(normalized) ? 'compound' : 'integrated';
}
export function entityGraphUrl(node: Pick<ApiNode, 'id' | 'type'>, domain: Domain = entityDomain(node.type)): string {
  return `/explorer/${domain}/graph?${new URLSearchParams({ node: node.id, type: entityType(node.type) })}`;
}
