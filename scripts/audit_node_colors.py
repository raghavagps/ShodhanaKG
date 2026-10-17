"""Reproducible palette audit; no production writes. Requires numpy.

Machado et al. (2009), doi:10.1109/TVCG.2009.113, supplementary matrices:
https://www.inf.ufrgs.br/~oliveira/pubs_files/CVD_Simulation/CVD_Simulation.html
Cross-checked against colorspacious/cvd.py (scientific numeric data).
OKLab Euclidean distance is a screening metric, NOT a WCAG threshold.
"""
import colorsys
import itertools
import json
import re
import zipfile
from pathlib import Path
import numpy as np

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'docs/accessibility/node-colors'
MATRICES = {
 'normal': np.eye(3),
 'protan-50': [[.458064,.679578,-.137642],[.092785,.846313,.060902],[-.007494,-.016807,1.024301]],
 'protan-100': [[.152286,1.052583,-.204868],[.114503,.786281,.099216],[-.003882,-.048116,1.051998]],
 'deutan-50': [[.547494,.607765,-.155259],[.181692,.781742,.036566],[-.010410,.027275,.983136]],
 'deutan-100': [[.367322,.860646,-.227968],[.280085,.672501,.047413],[-.011820,.042940,.968881]],
 'tritan-50': [[1.017277,.027029,-.044306],[-.006113,.958479,.047634],[.006379,.248708,.744913]],
 'tritan-100': [[1.255528,-.076749,-.178779],[-.078411,.930809,.147602],[.004733,.691367,.303900]],
 'grayscale': np.tile([.2126,.7152,.0722], (3,1)),
}
def rgb(value):
 return np.array([int(value[i:i+2],16)/255 for i in (1,3,5)])
def linear(v):
 return np.where(v <= .04045, v/12.92, ((v+.055)/1.055)**2.4)
def srgb(v):
 return np.where(v <= .0031308, 12.92*v, 1.055*np.maximum(v,0)**(1/2.4)-.055)
def simulate(hex_, matrix):
 return np.clip(np.asarray(matrix) @ linear(rgb(hex_)),0,1)
def hex_from_linear(v):
 return '#'+''.join(f'{int(round(c*255)):02X}' for c in srgb(v))
def lab(v):
 lms=np.array([[.4122214708,.5363325363,.0514459929],[.2119034982,.6806995451,.1073969566],[.0883024619,.2817188376,.6299787005]])@v
 return np.array([[.2104542553,.793617785,-.0040720468],[1.9779984951,-2.428592205,.4505937099],[.0259040371,.7827717662,-.808675766]])@np.cbrt(lms)
def contrast(a,b):
 x,y=sorted([float(np.dot(a,[.2126,.7152,.0722])),float(np.dot(b,[.2126,.7152,.0722]))])
 return (y+.05)/(x+.05)

def main():
 OUT.mkdir(parents=True,exist_ok=True)
 styles=json.loads((ROOT/'src/data/nodeStyles.json').read_text())
 shapes={}
 for a,b,path in re.findall(r"(?:'([^']+)'|(\w+)): '([^']+)'", (ROOT/'src/lib/nodeStyles.ts').read_text()): shapes[a or b]=path
 palettes={m:{t:s[m] for t,s in styles.items()} for m in ['normal','accessible']}
 with zipfile.ZipFile(OUT/'baseline-source.zip') as z:
  data=z.read('src/data/integratedGraph.ts').decode('utf-8-sig')
  block=data.split('export const I_NODE_COLORS:')[1].split('};')[0]
  palettes['baseline-normal']={}
  for t,h,s,l in re.findall(r'(\w+): "hsl\((\d+), (\d+)%, (\d+)%\)"',block):
   v=colorsys.hls_to_rgb(int(h)/360,int(l)/100,int(s)/100)
   palettes['baseline-normal'][t]='#'+''.join(f'{round(c*255):02X}' for c in v)
  block=z.read('src/hooks/useColorblind.tsx').decode().split('const colorblindMap:')[1].split('};')[0]
  palettes['baseline-accessible']=dict(re.findall(r'(\w+): "(#[0-9A-Fa-f]+)"',block))
 report={'method':'Machado linear-sRGB, clipped to gamut, OKLab Euclidean distance; <0.05 flags review, not certification', 'palettes':{}}
 panels=[]
 for mode,colors in palettes.items():
  results={}; rows=[]
  for vision,matrix in MATRICES.items():
   simulated={t:simulate(c,matrix) for t,c in colors.items()}
   pairs=[{'a':a,'b':b,'distance':round(float(np.linalg.norm(lab(simulated[a])-lab(simulated[b]))),5)} for a,b in itertools.combinations(colors,2)]
   pairs.sort(key=lambda p:p['distance'])
   results[vision]={'pair_count':len(pairs),'minimum':pairs[0],'flagged_pairs':[p for p in pairs if p['distance']<.05], 'pairs':pairs}
  outline=linear(rgb('#30251D'))
  css=(ROOT/'src/index.css').read_text().split(':root {')[1].split('  }')[0]
  surfaces=[]
  for token in ['background','card','secondary','muted']:
   h,s,l=map(float,re.search('--'+token+r':\s*([\d.]+) ([\d.]+)% ([\d.]+)%',css).groups())
   surfaces.append('#'+''.join(f'{round(c*255):02X}' for c in colorsys.hls_to_rgb(h/360,l/100,s/100)))
  text_ratios=[contrast(outline,linear(.1*rgb(c)+.9*rgb(bg))) for c in colors.values() for bg in surfaces]
  boundary_ratios=[contrast(outline,linear(rgb(bg))) for bg in surfaces]
  border_colors={t:('#000000' if np.dot(linear(rgb(c)),[.2126,.7152,.0722])>.179 else '#FFFFFF') for t,c in colors.items()}
  border_ratios=[contrast(linear(rgb(c)),linear(rgb(border_colors[t]))) for t,c in colors.items()]
  report['palettes'][mode]={'colors':colors,'unique_colors':len(set(colors.values())), 'contrast_surfaces':surfaces, 'min_tinted_text_contrast':min(text_ratios),'min_boundary_contrast':min(boundary_ratios),'min_pattern_fill_contrast':min(border_ratios),'visions':results}
  if mode.startswith('baseline'):
   for key in ['min_tinted_text_contrast','min_boundary_contrast','min_pattern_fill_contrast']: report['palettes'][mode].pop(key)
  if not mode.startswith('baseline'):
   assert min(text_ratios)>=4.5 and min(boundary_ratios)>=3 and min(border_ratios)>=4.5
  for t,c in colors.items():
   s=styles[t]
   cells=[]
   for vision,matrix in MATRICES.items():
    fill=hex_from_linear(simulate(c,matrix));stroke=hex_from_linear(simulate('#30251D',matrix))
    dash=' stroke-dasharray="3 2"' if s['border']=='dashed' and not mode.startswith('baseline') else ''
    path=shapes['ellipse' if mode.startswith('baseline') else s['shape']]
    border=border_colors[t]
    marker=f'<path d="{path}" fill="{fill}" stroke="{stroke}" stroke-width="3"/><path d="{path}" fill="{fill}" stroke="{border}" stroke-width="1.5"{dash}/>'
    if mode.startswith('baseline'): marker=f'<path d="{path}" fill="{fill}" stroke="#F7F4ED" stroke-width="1.4"/>'
    cells.append(f'<td><svg width="34" height="34" viewBox="-2 -2 24 24">{marker}</svg></td>')
   rows.append(f'<tr><th>{t}<small>{c}</small></th>'+''.join(cells)+'</tr>')
  panels.append(f'<section><h2>{mode}</h2><table><thead><tr><th>Category</th>'+''.join(f'<th>{v}</th>' for v in MATRICES)+'</tr></thead><tbody>'+''.join(rows)+'</tbody></table></section>')
 (OUT/'palette-audit.json').write_text(json.dumps(report,indent=2)+'\n')
 (OUT/'palette-comparison.html').write_text('<!doctype html><meta charset="utf-8"><title>Node palette simulations</title><style>body{background:#faf8f5;color:#30251d;font:14px Arial;margin:24px}table{border-collapse:collapse}td,th{padding:6px 12px;text-align:center;border-bottom:1px solid #ddd}th:first-child{text-align:left}small{display:block;font-weight:normal}section{break-after:page;margin-bottom:40px}</style><h1>Node palette audit</h1><p>34px markers; simulated colors, stable geometry. Numeric distance is a screening measure, not accessibility certification.</p>'+''.join(panels))
 for mode,result in report['palettes'].items():
  print(mode,'unique',result['unique_colors'],'text contrast',round(result['min_tinted_text_contrast'],2) if 'min_tinted_text_contrast' in result else 'not measured','boundary',round(result['min_boundary_contrast'],2) if 'min_boundary_contrast' in result else 'not measured','full-CVD close pairs',{v:len(r['flagged_pairs']) for v,r in result['visions'].items() if v.endswith('100')})

if __name__=='__main__':
 import runpy
 runpy.run_path(str(ROOT/'scripts/audit_node_colors_v2.py'), run_name='__main__')
