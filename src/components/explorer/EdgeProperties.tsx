import type { DisplayProperty } from '@/lib/types';
import { PropertyList } from './PropertyList';
export function EdgeProperties({ properties, phase }: { properties?: DisplayProperty[]; phase?: string | null }) {
  return <PropertyList properties={[...(phase ? [{ key: '__phase', label: 'Treatment phase', value: phase }] : []), ...(properties ?? [])]} />;
}
