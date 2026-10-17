/** Presentation copy only; classifier metadata remains intact in API responses. */
export function referencePresentation(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(referencePresentation);
  if (!value || typeof value !== 'object') return value;
  const record = value as Record<string, unknown>;
  const reference = 'namespace' in record && ('identifier' in record || 'raw' in record);
  return Object.fromEntries(Object.entries(record)
    .filter(([key]) => !(reference && ['resolution_status', 'verification_status', 'lookup_error', 'status', 'verified_at', 'catalog_version'].includes(key)))
    .map(([key, entry]) => [key, referencePresentation(entry)]));
}
