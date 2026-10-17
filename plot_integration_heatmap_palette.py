"""Render a separate integration heatmap with the requested blue palette."""
from pathlib import Path
import collections
import csv
import hashlib
import json
import os
import textwrap
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parent
os.environ['MPLCONFIGDIR'] = str(ROOT / '.audit-temp' / 'structure-mplconfigdir')
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from matplotlib.colors import LinearSegmentedColormap, Normalize
import numpy as np
from PIL import Image

PALETTE = ['#E3E8F7', '#C5CFEF', '#A3B2E5', '#7F93D6', '#5B6FB5', '#3A4A8C']


def main():
    source = ROOT / 'figures/structure_analysis/data/integration_matrix.csv'
    snapshot = ROOT / '.audit-temp/production-20260928/production-records.json'
    out = ROOT / 'figures/integration_heatmap_palette'
    out.mkdir(parents=True, exist_ok=True)
    with source.open(encoding='utf-8', newline='') as handle:
        rows = list(csv.DictReader(handle))
    sources = dict((r['source_id'], r['source_label']) for r in rows)
    targets = dict((r['target_id'], r['target_label']) for r in rows)
    counts = {(r['source_id'], r['target_id']): int(r['edges']) for r in rows}
    records = {}
    for table in json.loads(snapshot.read_text(encoding='utf-8'))['tables'].values():
        for record in table:
            rid = record['@rid']
            assert rid not in records or records[rid] == record
            records[rid] = record
    actual = collections.Counter((r['@out'], r['@in']) for r in records.values()
                                 if r['@cat'] == 'e' and r['@type'] == 'USED_FOR_TREATMENT')
    assert {k: v for k, v in counts.items() if v} == dict(actual)
    matrix = np.array([[counts[s, t] for t in targets] for s in sources])
    assert matrix.shape == (29, 17) and matrix.sum() == 75
    assert np.count_nonzero(matrix) == 56
    plt.rcParams.update({'font.family': 'Arial', 'font.size': 8,
                         'svg.fonttype': 'none', 'pdf.fonttype': 42,
                         'axes.linewidth': .5, 'savefig.facecolor': 'white'})
    fig, ax = plt.subplots(figsize=(180 / 25.4, 8.4))
    fig.subplots_adjust(left=.32, bottom=.30, right=.88, top=.94)
    cmap = LinearSegmentedColormap.from_list('requested_blues', PALETTE, N=256)
    mesh = ax.pcolormesh(matrix, cmap=cmap, norm=Normalize(0, matrix.max()), rasterized=False)
    ax.invert_yaxis()
    ax.set_xticks(np.arange(len(targets)) + .5,
                  ['\n'.join(textwrap.wrap(label, 18)) for label in targets.values()],
                  rotation=65, ha='right')
    ax.set_yticks(np.arange(len(sources)) + .5, list(sources.values()))
    ax.set_title('Integration: purified compounds and diseases', fontsize=9, pad=12)
    cb = fig.colorbar(mesh, ax=ax, fraction=.04, pad=.025, ticks=range(7))
    cb.solids.set_rasterized(False)
    cb.set_label('Stored edge records')
    for i, j in np.ndindex(matrix.shape):
        value = matrix[i, j]
        if value:
            ax.text(j + .5, i + .5, str(value), ha='center', va='center',
                    color='white' if value >= 4 else '#17203B', fontsize=8)
    caption = ('29 purified compounds × 17 diseases; 75 stored treatment records across 56 pairs. '
               'Colour represents edge-record count, not efficacy. Zero cells use the lightest colour.')
    fig.text(.32, .025, '\n'.join(textwrap.wrap(caption, 78)), fontsize=8, va='bottom')
    base = out / 'C04_integration_heatmap_blue_palette'
    for extension in ('svg', 'pdf', 'png'):
        fig.savefig(base.with_suffix('.' + extension), dpi=300)
    plt.close(fig)
    with Image.open(base.with_suffix('.png')) as image:
        rgb = image.convert('RGB')
        rgb.save(base.with_suffix('.png'), dpi=(300, 300))
        rgb.save(base.with_suffix('.jpg'), quality=95, dpi=(300, 300))
    ns = '{http://www.w3.org/2000/svg}'
    ET.register_namespace('', ns[1:-1])
    tree = ET.parse(base.with_suffix('.svg'))
    root = tree.getroot()
    ET.SubElement(root, ns + 'title').text = 'Integration heatmap — blue palette'
    ET.SubElement(root, ns + 'desc').text = caption
    assert root.get('viewBox') and list(root.iter(ns + 'text'))
    assert not list(root.iter(ns + 'image'))
    tree.write(base.with_suffix('.svg'), encoding='utf-8', xml_declaration=True)
    assert b'/Subtype /Image' not in base.with_suffix('.pdf').read_bytes()
    report = {'status': 'PASS', 'palette': PALETTE, 'normalization': 'linear, 0 to 6',
              'rows': 29, 'columns': 17, 'edge_records': 75, 'nonzero_pairs': 56,
              'source': str(source.relative_to(ROOT)),
              'source_sha256': hashlib.sha256(source.read_bytes()).hexdigest(),
              'snapshot_sha256': hashlib.sha256(snapshot.read_bytes()).hexdigest(),
              'verified_against_snapshot': True, 'vector_svg_pdf': True,
              'caption': caption}
    (out / 'validation.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
    print(json.dumps(report, indent=2))


if __name__ == '__main__':
    main()
