// Shared export helpers for graph views (CSV, JSON, PNG).
// Keeps the export buttons consistent across Disease, Compound and Integrated modes.

interface GenericNodeLike {
  id: string;
  label: string;
  type: string;
  description?: string;
}
interface GenericEdgeLike {
  id: string;
  source: string;
  target: string;
  type: string;
  [k: string]: unknown;
}

const triggerDownload = (blob: Blob, filename: string) => {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(url), 0);
};

const csvEscape = (v: unknown) => {
  if (v === null || v === undefined) return "";
  const s = (typeof v === "object" ? JSON.stringify(v) : String(v)).replace(/"/g, '""');
  return /[,"\n\r]/.test(s) ? `"${s}"` : s;
};

export const exportGraphJSON = (
  nodes: GenericNodeLike[],
  edges: GenericEdgeLike[],
  prefix: string,
  extra: Record<string, unknown> = {},
) => {
  const payload = { ...extra, nodes, edges, exportedAt: new Date().toISOString() };
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: "application/json" });
  triggerDownload(blob, `${prefix}-${Date.now()}.json`);
};

export const exportGraphCSV = (
  nodes: GenericNodeLike[],
  edges: GenericEdgeLike[],
  prefix: string,
) => {
  const nodeRows = [
    ["id", "label", "type", "description"].join(","),
    ...nodes.map((n) =>
      [csvEscape(n.id), csvEscape(n.label), csvEscape(n.type), csvEscape(n.description ?? "")].join(","),
    ),
  ].join("\n");

  const edgeRows = [
    ["id", "source", "target", "type", "references"].join(","),
    ...edges.map((e) =>
      [csvEscape(e.id), csvEscape(e.source), csvEscape(e.target), csvEscape(e.type), csvEscape(e.references ?? [])].join(","),
    ),
  ].join("\n");

  const csv = `# Nodes\n${nodeRows}\n\n# Edges\n${edgeRows}\n`;
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
  triggerDownload(blob, `${prefix}-${Date.now()}.csv`);
};

/**
 * Export the current graph viewport as PNG.
 * Looks for a Cytoscape <canvas> inside the provided container element.
 */
export const exportGraphPNG = (container: HTMLElement | null, prefix: string) => {
  if (!container) return false;
  const canvases = container.querySelectorAll("canvas");
  if (canvases.length === 0) return false;

  // Composite all cytoscape layered canvases into one
  const first = canvases[0] as HTMLCanvasElement;
  const w = first.width;
  const h = first.height;
  const out = document.createElement("canvas");
  out.width = w;
  out.height = h;
  const ctx = out.getContext("2d");
  if (!ctx) return false;

  // background to match card
  ctx.fillStyle = "#faf6ee";
  ctx.fillRect(0, 0, w, h);
  canvases.forEach((c) => {
    try {
      ctx.drawImage(c as HTMLCanvasElement, 0, 0, w, h);
    } catch { /* ignore tainted */ }
  });

  out.toBlob((blob) => {
    if (!blob) return;
    triggerDownload(blob, `${prefix}-${Date.now()}.png`);
  }, "image/png");
  return true;
};
