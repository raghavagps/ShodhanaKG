interface LegendProps {
  nodeTypes: { key: string; label: string }[];
  nodeColors: Record<string, string>;
  edgeTypes?: { key: string; label: string }[];
  edgeColors?: Record<string, string>;
}

const UNIFORM_EDGE_COLOR = "hsl(25, 18%, 45%)";

export const Legend = ({ nodeTypes, nodeColors, edgeTypes, edgeColors }: LegendProps) => {
  return (
    <div className="rounded-md border border-border bg-card/70 p-3 text-[11px] space-y-3">
      <div>
        <div className="font-semibold uppercase tracking-wider text-muted-foreground mb-1.5 text-[10px]">
          Node types
        </div>
        <div className="grid grid-cols-2 gap-1">
          {nodeTypes.map((t) => (
            <div key={t.key} className="flex items-center gap-1.5">
              <span
                className="h-2.5 w-2.5 rounded-full shrink-0"
                style={{ background: nodeColors[t.key] }}
              />
              <span className="text-foreground/80 truncate">{t.label}</span>
            </div>
          ))}
        </div>
      </div>
      {edgeTypes && edgeTypes.length > 0 && (
        <div>
          <div className="font-semibold uppercase tracking-wider text-muted-foreground mb-1.5 text-[10px]">
            Relationship types
          </div>
          <div className="grid grid-cols-1 gap-1">
            {edgeTypes.map((t) => (
              <div key={t.key} className="flex items-center gap-1.5">
                <span
                  className="h-1 w-4 shrink-0 rounded-sm"
                  style={{ background: edgeColors?.[t.key] ?? UNIFORM_EDGE_COLOR }}
                />
                <span className="text-foreground/80 truncate font-mono text-[10px]">{t.label}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};
