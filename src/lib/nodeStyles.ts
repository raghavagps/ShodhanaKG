import registry from '@/data/nodeStyles.json' with { type: 'json' };
import { entityType } from './entityNavigation';

export type NodeCategory = keyof typeof registry;
export const NODE_STYLES = registry;
export const NODE_TEXT = '#30251D';
const fallback = { normal: '#777777', accessible: '#777777' };
export function nodeStyle(type: string, accessible = false) {
  const style = registry[entityType(type) as NodeCategory] ?? fallback;
  return { ...style, fill: accessible ? style.accessible : style.normal };
}
export function nodeColor(type: string) {
  const category = entityType(type) as NodeCategory;
  return registry[category] ? `var(--node-${category}, ${registry[category].normal})` : fallback.normal;
}
export function nodeTint(color: string) {
  return `color-mix(in srgb, ${color} 10%, transparent)`;
}
export function categoryPalette<T extends string>(types: readonly T[]): Record<T, string> {
  return Object.fromEntries(types.map(type => [type, nodeColor(type)])) as Record<T, string>;
}

