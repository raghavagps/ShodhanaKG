"""Color-only revision: reproduce pairwise simulation and original-renderer checks."""
import itertools
import json
import zipfile
import numpy as np
from audit_node_colors import ROOT, MATRICES, simulate, lab, hex_from_linear, contrast, linear, rgb

out = ROOT / 'docs/accessibility/node-colors-v2'
out.mkdir(parents=True, exist_ok=True)
styles = json.loads((ROOT / 'src/data/nodeStyles.json').read_text())
report = {'method': 'Machado linear sRGB; OKLab distance <0.05 is a screening flag, not accessibility certification', 'palettes': {}}
panels = []
for mode in ['normal', 'accessible']:
    colors = {t: s[mode] for t, s in styles.items()}
    results = {}
    for vision, matrix in MATRICES.items():
        samples = {t: simulate(c, matrix) for t, c in colors.items()}
        pairs = sorted([{'a': a, 'b': b, 'distance': float(np.linalg.norm(lab(samples[a])-lab(samples[b])))} for a,b in itertools.combinations(colors, 2)], key=lambda p:p['distance'])
        results[vision] = {'minimum': pairs[0], 'flagged_pairs': [p for p in pairs if p['distance'] < .05], 'pairs': pairs}
    report['palettes'][mode] = {'colors': colors, 'visions': results, 'minimum_fill_contrast_on_graph': min(contrast(linear(rgb(c)), linear(rgb('#FAF8F5'))) for c in colors.values())}
    rows = []
    for t,c in colors.items():
        cells = ''.join(f'<td><span style="background:{hex_from_linear(simulate(c,matrix))}"></span></td>' for matrix in MATRICES.values())
        rows.append(f'<tr><th>{t}</th>{cells}</tr>')
    panels.append(f'<h2>{mode}</h2><table><tr><th>Category</th>'+''.join(f'<th>{v}</th>' for v in MATRICES)+'</tr>'+''.join(rows)+'</table>')
with zipfile.ZipFile(ROOT/'docs/accessibility/node-colors/baseline-source.zip') as archive:
    report['original_renderer_restored'] = {p: (ROOT/p).read_bytes() == archive.read(p) for p in ['src/components/explorer/GraphCanvas.tsx','src/lib/graphFocus.ts']}
assert all(report['original_renderer_restored'].values())
(out/'palette-audit.json').write_text(json.dumps(report, indent=2))
(out/'palette-comparison.html').write_text('<!doctype html><meta charset="utf-8"><title>Color-only palette audit</title><style>body{background:#FAF8F5;color:#30251D;font:14px sans-serif}td,th{padding:6px;text-align:left}span{display:block;width:34px;height:34px;border:2px solid #f5f0e8;border-radius:50%}</style>'+''.join(panels))
for mode,data in report['palettes'].items():
    print(mode, {v: round(r['minimum']['distance'],5) for v,r in data['visions'].items()})
print(report['original_renderer_restored'])
