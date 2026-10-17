import schema from '@/data/schemaReference.json';

const label = (value: string) => value.replace(/_/g, ' ');
export function SchemaVertexRows({ print = false }: { print?: boolean }) {
  return <>{schema.vertices.map(node => <tr key={node.type}>
    <td className={print ? 'p-2 border-r border-gray-300' : 'p-3'}>{node.domain}</td>
    <td className={print ? 'p-2 font-bold' : 'p-3 font-semibold text-foreground'}>{label(node.type)}</td>
  </tr>)}</>;
}
export function SchemaEdgeRows({ print = false }: { print?: boolean }) {
  return <>{schema.edges.map(edge => <tr key={edge.type}>
    <td className={print ? 'p-2 border-r border-gray-300' : 'p-3'}>{edge.domain}</td>
    <td className={print ? 'p-2 font-bold border-r border-gray-300' : 'p-3 font-semibold text-foreground'}>{edge.type}</td>
    <td className={print ? 'p-2 border-r border-gray-300' : 'p-3'}>{edge.pairs.map(pair => <div key={`${pair.source}:${pair.target}`}>{label(pair.source)} → {label(pair.target)}</div>)}</td>
    <td className={print ? 'p-2 text-gray-700' : 'p-3 text-muted-foreground'}>{edge.properties.map(key => key === 'ref' ? 'ref (Source ID)' : key).join(', ')}</td>
  </tr>)}</>;
}
