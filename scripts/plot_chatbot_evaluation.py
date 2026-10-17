"""Reproduce publication figures from saved, checksum-verified chatbot results.

No live queries, data edits, model calls, or benchmark execution are performed.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import json
import math
import os
from pathlib import Path
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
CACHE = ROOT / '.audit-temp' / 'chatbot-evaluation-figures'
CACHE.mkdir(parents=True, exist_ok=True)
os.environ.update(TEMP=str(CACHE), TMP=str(CACHE), MPLCONFIGDIR=str(CACHE / 'mpl'))
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from matplotlib import font_manager
from matplotlib.ticker import PercentFormatter, MaxNLocator, StrMethodFormatter
import numpy as np
from PIL import Image, ImageDraw

NAVY, BLUE, AMBER, GREY = '#18344F', '#267DA5', '#A56500', '#5C6269'
COLORS = [NAVY, BLUE, AMBER]
MARKERS = ['o', 's', '^']
NS = '{http://www.w3.org/2000/svg}'


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def load_verified(audit: Path) -> tuple[dict, dict]:
    """Require the original manifest for every raw input and primary summary."""
    manifest = {}
    for line in (audit / 'SHA256SUMS').read_text().splitlines():
        h, name = line.split('  ', 1)
        manifest[name.replace('\\', '/')] = h
    names = ['benchmark/benchmark.json', 'benchmark/factual.json',
             'benchmark/attribution.json', 'benchmark/presentation.json',
             'benchmark/conversation.json', 'benchmark/answers.json',
             'benchmark/routing.log', 'metrics.json']
    hashes, data = {}, {}
    for name in names:
        path = audit / name
        actual = digest(path)
        if manifest.get(name) != actual:
            raise ValueError(f'Historical checksum mismatch or missing manifest entry: {path}')
        hashes[name] = actual
        if path.suffix == '.json':
            data[path.stem] = json.loads(path.read_bytes())
    return data, hashes


def stats(values: list[float]) -> dict:
    v = sorted(values)
    if not v or any(not math.isfinite(x) or x < 0 for x in v):
        raise ValueError('Missing or invalid latency observations')
    return dict(n=len(v), missing=0, p50_ms=v[math.ceil(len(v)*.5)-1],
                p95_ms=v[math.ceil(len(v)*.95)-1], max_ms=max(v),
                mean_ms=round(sum(v)/len(v), 2))


def validate(data: dict) -> dict:
    b, f, c = (data[x] for x in ['benchmark', 'factual', 'conversation'])
    if b['contract_version'] != '8':
        raise ValueError('This figure specification requires benchmark contract 8')
    assert f['summary'] == b['factual'] == dict(total=168, passed=168, failed=0)
    assert len(f['cases']) == 168 and all(x['passed'] for x in f['cases'])
    counts = {}
    for row in f['cases']:
        counts[row['endpoint']] = counts.get(row['endpoint'], 0) + 1
    assert len(counts) == 52
    histogram = {n: sum(v == n for v in counts.values()) for n in sorted(set(counts.values()))}
    assert histogram == {2: 8, 3: 26, 4: 16, 5: 2}
    assert sum(r['expected_status'] == 'success' for r in f['cases']) == 133
    assert sum(r['expected_status'] == 'no_data' for r in f['cases']) == 35
    assert data['attribution']['summary'] == b['attribution']
    assert b['attribution'] == dict(endpoints=40, cases=121, passed=121, failed=0)
    assert data['presentation']['summary'] == b['presentation'] == dict(cases=24, passed=24, failed=0)
    assert b['routing_status'] == dict(total=82, passed=82, failed=0, skipped=0)
    assert c['scenarios'] == 34 and len(c['runs']) == 108 and all(x['passed'] for x in c['runs'])
    assert not c['parity_failures'] and not b['transport_parity_failures']
    pairs = {}
    categories = {}
    for row in c['runs']:
        key = (row['scenario'], row['turn'])
        group = pairs.setdefault(key, {})
        assert row['transport'] not in group
        group[row['transport']] = row
        if row['transport'] == 'json':
            categories[row['category']] = categories.get(row['category'], 0) + 1
    assert len(pairs) == 54 and len(categories) == 17
    for pair in pairs.values():
        assert set(pair) == {'json', 'sse'}
        assert pair['json']['category'] == pair['sse']['category']
        assert pair['json']['question'] == pair['sse']['question']
    assert all(c['summary']['categories'][k]['total'] == 2*v for k,v in categories.items())
    latencies = {
        'direct_endpoint_not_http': [x['latency_ms'] for x in f['cases']],
        'results_first_http_json': [x['latency_ms'] for x in data['answers']['rows']],
        'conversation_http_json': [x['latency_ms'] for x in c['runs'] if x['transport']=='json'],
        'conversation_http_sse': [x['latency_ms'] for x in c['runs'] if x['transport']=='sse'],
    }
    for name, values in latencies.items():
        assert stats(values) == b['latency'][name], f'Latency mismatch: {name}'
    for transport in ['json', 'sse']:
        r = b['refusals'][transport]
        assert (r['answerable_turns'], r['false_refusals'], r['expected_abstention_turns'],
                r['correct_abstentions'], r['false_acceptances']) == (40,0,12,12,0)
    return dict(endpoint_counts=counts, histogram=histogram, pairs=pairs,
                categories=categories, latencies=latencies)


def configure() -> str:
    font = font_manager.findfont('Arial', fallback_to_default=False)
    plt.rcParams.update({'font.family':'Arial', 'font.size':8, 'axes.titlesize':9,
                        'axes.labelsize':8, 'xtick.labelsize':7.5, 'ytick.labelsize':8,
                        'legend.fontsize':7.5, 'svg.fonttype':'none', 'pdf.fonttype':42,
                        'axes.spines.top':False, 'axes.spines.right':False,
                        'axes.linewidth':.6, 'lines.linewidth':1.15,
                        'text.color':NAVY, 'axes.labelcolor':NAVY,
                        'xtick.color':GREY, 'ytick.color':GREY, 'savefig.facecolor':'white'})
    return font


def panel(fig, position: list[float], letter: str, title: str):
    ax = fig.add_axes(position)
    fig.text(position[0]-.035, position[1]+position[3]+.027, letter,
             fontweight='bold', fontsize=10)
    ax.set_title(title, loc='left', pad=12, fontweight='bold')
    return ax


def export(fig, path: Path, caption: str) -> dict:
    path.parent.mkdir(parents=True, exist_ok=True)
    fig.canvas.draw()
    renderer = fig.canvas.get_renderer()
    outside = []
    for item in fig.findobj(matplotlib.text.Text):
        if item.get_visible() and item.get_text():
            box = item.get_window_extent(renderer)
            # Ignore unused tick labels outside the active axis range.
            if box.width and box.height and not fig.bbox.contains(*box.p0):
                outside.append(item.get_text())
            elif box.width and box.height and not fig.bbox.contains(*box.p1):
                outside.append(item.get_text())
    if outside:
        raise ValueError(f'Text outside figure {path.name}: {outside}')
    for ext in ['svg', 'pdf', 'png']:
        fig.savefig(path.with_suffix('.'+ext), dpi=300)
    with Image.open(path.with_suffix('.png')) as preview:
        rgb = preview.convert('RGB')
    rgb.save(path.with_suffix('.png'), dpi=(300,300))
    plt.close(fig)
    ET.register_namespace('', NS[1:-1])
    tree = ET.parse(path.with_suffix('.svg')); root = tree.getroot()
    ET.SubElement(root, NS+'title').text = path.name.replace('_',' ')
    ET.SubElement(root, NS+'desc').text = caption
    tree.write(path.with_suffix('.svg'), encoding='utf-8', xml_declaration=True)
    assert root.get('viewBox') and list(root.iter(NS+'text')) and list(root.iter(NS+'path'))
    assert not list(root.iter(NS+'image')), 'Raster substitute found in vector output'
    assert not list(root.iter(NS+'script'))
    assert all(path.with_suffix('.'+ext).stat().st_size > 1000 for ext in ['svg','pdf','png'])
    pdf = path.with_suffix('.pdf').read_bytes()
    assert b'/FontFile2' in pdf and b'/Subtype /Image' not in pdf
    return dict(figure=path.name, editable_text_elements=len(list(root.iter(NS+'text'))),
                vector_paths=len(list(root.iter(NS+'path'))), raster_images=0,
                text_within_canvas=True, pdf_embedded_truetype=True, png_mode='RGB', png_dpi=300)


def main_figure(data: dict, validated: dict, out: Path, caption: str) -> dict:
    fig = plt.figure(figsize=(183/25.4,135/25.4))
    ax = panel(fig,[.075,.59,.86,.31],'a','Agreement with tested KG records and contracts')
    ax.set_title('Agreement with tested KG records and contracts',loc='left',pad=23,fontweight='bold')
    fig.texts[0].set_y(.958)
    ax.axis('off')
    rows=[('Factual retrieval','168/168','Endpoint/entity cases'),
          ('Per-edge attribution','121/121','Cases across 40 endpoints'),
          ('Results-first presentation','24/24','HTTP questions'),
          ('Routing and status','82/82','Contract cases'),
          ('Conversation correctness','108/108','54 turns × 2 transports'),
          ('JSON/SSE parity','54/54','Paired turns')]
    for x,label in [(0,'Evaluation'),(.49,'Passed / evaluated'),(.75,'Unit')]:
        ax.text(x,1.03,label,transform=ax.transAxes,fontweight='bold',fontsize=8)
    for i,(label,count,unit) in enumerate(rows):
        y=.86-i*.15
        ax.text(0,y,label,transform=ax.transAxes)
        ax.text(.49,y,count,transform=ax.transAxes,fontweight='bold')
        ax.text(.75,y,unit,transform=ax.transAxes,fontsize=7.5)
        ax.plot([0,1],[y-.045,y-.045],color='#D7DADD',lw=.45,transform=ax.transAxes)
    ax.text(0,-.14,'All listed suites: 100% pass · Overlapping scopes; do not sum',
            transform=ax.transAxes,fontsize=7.5,color=GREY)
    ax = panel(fig,[.075,.14,.39,.29],'b','Answerability and abstention')
    ax.axis('off')
    ax.text(.65,1.0,'JSON',fontweight='bold',ha='center');ax.text(.93,1.0,'SSE',fontweight='bold',ha='center')
    for i,(label,value) in enumerate([('Answerable: success','40/40'),('Expected abstention','12/12'),('False refusals','0/40'),('False acceptances','0/12')]):
        y=.77-i*.21;ax.text(0,y,label,fontsize=7.5)
        for x in [.65,.93]:ax.text(x,y,value,ha='center',fontweight='bold')
    ax.text(0,-.18,'Same turns in both transports\n+2 no-data controls per transport',fontsize=7.3,color=GREY,linespacing=1.5)
    ax = panel(fig,[.70,.14,.255,.29],'c','HTTP completion time')
    labels=['Presentation JSON','Conversation JSON','Conversation SSE']
    names=list(validated['latencies'])[1:]
    for i,(name,label) in enumerate(zip(names,labels)):
        s=stats(validated['latencies'][name]); y=2-i
        ax.plot([s['p50_ms'],s['p95_ms']],[y,y],color=COLORS[i])
        ax.scatter(s['p50_ms'],y,marker='o',s=25,color=COLORS[i],zorder=3)
        ax.scatter(s['p95_ms'],y,marker='D',s=25,facecolors='white',edgecolors=COLORS[i],zorder=3)
        ax.text(s['p50_ms'],y+.20,str(s['p50_ms']),ha='center',fontsize=7)
        ax.text(s['p95_ms'],y+.20,str(s['p95_ms']),ha='center',fontsize=7)
    ax.set(yticks=[2,1,0],yticklabels=[f'{x}\n(n = {len(validated["latencies"][n])})' for x,n in zip(labels,names)],
           xlim=(350,950),ylim=(-.5,2.6),xlabel='Time (ms)')
    ax.set_xticks([400,600,800]);ax.tick_params(axis='y',length=0,labelsize=7.2)
    ax.spines['left'].set_visible(False)
    ax.text(-.08,-.36,'Circle: median · Diamond: p95\nRange is not a confidence interval',transform=ax.transAxes,fontsize=7.1,color=GREY)
    return export(fig,out/'core/F_chatbot_evaluation',caption)


def coverage_figure(v: dict, out: Path, caption: str) -> dict:
    fig=plt.figure(figsize=(183/25.4,170/25.4))
    ax=panel(fig,[.33,.11,.29,.80],'a','Conversation coverage')
    labels=[k.replace('_',' ').capitalize() for k in v['categories']]
    vals=list(v['categories'].values()); y=np.arange(len(vals))
    ax.barh(y,vals,color=BLUE,height=.62)
    for yy,n in zip(y,vals):ax.text(n+.15,yy,f'{n}/{n}',va='center',fontsize=7.5)
    ax.set(yticks=y,yticklabels=labels,xlim=(0,10),xlabel='Unique turns');ax.invert_yaxis()
    ax.xaxis.set_major_locator(MaxNLocator(integer=True,nbins=5));ax.tick_params(axis='y',length=0)
    ax.text(0,-.10,'54 turns · 34 scenarios\nLabels: passed/evaluated in each transport',transform=ax.transAxes,fontsize=7.5,color=GREY)
    ax=panel(fig,[.75,.48,.21,.43],'b','Endpoint sampling')
    x=list(v['histogram']);n=list(v['histogram'].values())
    ax.bar(x,n,color=NAVY,width=.65)
    for xx,nn in zip(x,n):ax.text(xx,nn+.7,str(nn),ha='center')
    ax.set(xticks=x,ylim=(0,31),ylabel='Endpoints',xlabel='Cases per endpoint')
    ax.yaxis.set_major_locator(MaxNLocator(integer=True,nbins=5))
    ax.text(0,-.22,'52 endpoints\n168 cases\n\n133 expected success\n35 expected no data',transform=ax.transAxes,fontsize=8,linespacing=1.6,va='top')
    return export(fig,out/'supplementary/S1_chatbot_coverage',caption)


def ecdf(ax, values: list[float], color: str, label: str, style: str='-'):
    x=np.sort(values); y=np.arange(1,len(x)+1)/len(x)
    ax.step(np.r_[0,x],np.r_[0,y],where='post',color=color,label=label,linestyle=style)
    ax.set(ylim=(0,1.035),xlabel='Completion time (ms)',ylabel='Cumulative proportion')
    ax.yaxis.set_major_formatter(PercentFormatter(1))


def timing_figure(v: dict,out: Path,caption: str) -> dict:
    fig=plt.figure(figsize=(183/25.4,155/25.4))
    ax=panel(fig,[.10,.62,.35,.28],'a','Direct endpoint retrieval')
    ecdf(ax,v['latencies']['direct_endpoint_not_http'],NAVY,'n = 168')
    ax.set_xlim(left=0);ax.legend(loc='lower right',frameon=False)
    ax.text(0,1.01,'No HTTP or question routing',transform=ax.transAxes,fontsize=7,color=GREY)
    ax=panel(fig,[.61,.62,.35,.28],'b','HTTP completion distributions')
    for name,label,color,style in zip(list(v['latencies'])[1:],['Presentation JSON (24)','Conversation JSON (54)','Conversation SSE (54)'],COLORS,['-', '--', ':']):
        ecdf(ax,v['latencies'][name],color,label,style)
    ax.set_xlim(0,2500);ax.set_xticks([0,1000,2000]);ax.legend(loc='lower right',frameon=False,fontsize=7)
    ax.xaxis.set_major_formatter(StrMethodFormatter('{x:,.0f}'))
    ax=panel(fig,[.33,.105,.37,.33],'c','Matched conversation turns')
    json_ms=[x['json']['latency_ms'] for x in v['pairs'].values()]
    sse_ms=[x['sse']['latency_ms'] for x in v['pairs'].values()]
    ax.scatter(json_ms,sse_ms,s=22,facecolors='none',edgecolors=BLUE,linewidths=.9)
    limit=math.ceil(max(json_ms+sse_ms)/500)*500
    ax.plot([0,limit],[0,limit],color=GREY,linestyle='--',lw=.7,label='Equal completion time')
    ax.set(xlim=(0,limit),ylim=(0,limit),xlabel='JSON completion time (ms)',ylabel='SSE completion time (ms)')
    ax.set_aspect('equal',adjustable='box');ax.legend(frameon=False,fontsize=7,loc='lower right')
    ax.xaxis.set_major_formatter(StrMethodFormatter('{x:,.0f}'))
    ax.yaxis.set_major_formatter(StrMethodFormatter('{x:,.0f}'))
    ax.text(.03,.95,'54 pairs',transform=ax.transAxes,va='top')
    return export(fig,out/'supplementary/S2_chatbot_latency',caption)


def csv_write(path: Path, header: list[str], rows):
    with path.open('w',newline='',encoding='utf-8') as f:
        w=csv.writer(f);w.writerow(header);w.writerows(rows)


def accessibility_previews(out: Path) -> None:
    """Approximate full-severity CVD previews in linear RGB, not clinical validation.

    Matrices follow the full-severity Machado-style RGB simulation convention.
    These diagnostic raster previews are never embedded in the vector masters.
    """
    matrices={
      'Protanopia':[[.152286,1.052583,-.204868],[.114503,.786281,.099216],[-.003882,-.048116,1.051998]],
      'Deuteranopia':[[.367322,.860646,-.227968],[.280085,.672501,.047413],[-.011820,.042940,.968881]],
      'Tritanopia':[[1.255528,-.076749,-.178779],[-.078411,.930809,.147602],[.004733,.691367,.3039]]}
    folder=out/'previews';folder.mkdir(exist_ok=True)
    for path in list((out/'core').glob('*.png'))+list((out/'supplementary').glob('*.png')):
        image=Image.open(path).convert('RGB');image.thumbnail((1050,1000))
        rgb=np.asarray(image)/255.;linear=np.where(rgb<=.04045,rgb/12.92,((rgb+.055)/1.055)**2.4)
        versions=[('Normal',image),('Grayscale',image.convert('L').convert('RGB'))]
        for name,matrix in matrices.items():
            altered=np.clip(linear@np.array(matrix).T,0,1)
            srgb=np.where(altered<=.0031308,12.92*altered,1.055*altered**(1/2.4)-.055)
            versions.append((name,Image.fromarray(np.uint8(np.clip(srgb*255,0,255)))))
        w,h=image.size; sheet=Image.new('RGB',(w*3,(h+25)*2),'white');draw=ImageDraw.Draw(sheet)
        for i,(name,img) in enumerate(versions):
            x=(i%3)*w;y=(i//3)*(h+25);draw.text((x+8,y+5),name,fill='black');sheet.paste(img,(x,y+25))
        target=folder/(path.stem+'_vision_checks.png')
        staging=target.with_suffix('.png.new')
        sheet.save(staging,format='PNG')
        staging.replace(target)


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--input',type=Path,default=ROOT/'docs/audits/browser-six-20261011')
    parser.add_argument('--output',type=Path,default=ROOT/'figures/chatbot_evaluation')
    args=parser.parse_args();out=args.output;out.mkdir(parents=True,exist_ok=True)
    data,hashes=load_verified(args.input);v=validate(data);font=configure()
    captions={
      'F_chatbot_evaluation':'Chatbot correctness and response time. (a) Case-level agreement with frozen KG-derived references and software contracts. All listed scopes passed, but suites overlap and must not be summed. (b) Answerability and abstention outcomes for the same turns through JSON and SSE. Two additional reference-based no-data controls per transport are not part of the 40 answerable/12 abstention denominators. (c) Local sequential HTTP completion times: filled circles denote medians and open diamonds denote nearest-rank p95. Connecting lines are descriptive ranges, not confidence intervals. Measurements extend through body receipt, not first-token delivery. Agreement with KG records does not establish scientific or clinical validity.',
      'S1_chatbot_coverage':'Evaluation coverage. (a) All 17 conversation categories, counted once per scenario/turn; 54 turns across 34 scenarios were each executed through JSON and SSE (108 executions). Labels indicate passed/evaluated turns in each transport. (b) Factual cases per endpoint: 8 endpoints had 2 cases, 26 had 3, 16 had 4 and 2 had 5, totaling 168 cases across 52 endpoints. Of these cases, 133 expected success and 35 expected no data. Endpoint coverage does not imply exhaustive entity, path or natural-language coverage.',
      'S2_chatbot_latency':'Local response-time distributions. (a) Empirical cumulative distribution of 168 direct endpoint measurements, bypassing HTTP and routing. (b) Complete observations from 24 presentation JSON, 54 conversation JSON and 54 conversation SSE requests. (c) JSON and SSE timings matched by scenario and turn for 54 pairs; the dashed identity line indicates equal completion time, and overlapping points are retained without jitter. All observations, including extremes, are retained. Timings are from one sequential local run with no warm-up exclusion; they do not estimate production capacity or demonstrate a significant transport effect.'}
    checks=[main_figure(data,v,out,captions['F_chatbot_evaluation']),coverage_figure(v,out,captions['S1_chatbot_coverage']),timing_figure(v,out,captions['S2_chatbot_latency'])]
    folder=out/'source_data';folder.mkdir(exist_ok=True)
    csv_write(folder/'latency_observations.csv',['series','observation_index','latency_ms'],((name,i+1,x) for name,vals in v['latencies'].items() for i,x in enumerate(vals)))
    csv_write(folder/'latency_summary.csv',['series','n','median_ms','p95_ms','mean_ms','maximum_ms'],((name,s['n'],s['p50_ms'],s['p95_ms'],s['mean_ms'],s['max_ms']) for name,vals in v['latencies'].items() for s in [stats(vals)]))
    csv_write(folder/'conversation_categories.csv',['category','unique_turns','passed_json','passed_sse'],((k,n,n,n) for k,n in v['categories'].items()))
    csv_write(folder/'endpoint_cases.csv',['endpoint','cases'],sorted(v['endpoint_counts'].items()))
    csv_write(folder/'paired_transports.csv',['scenario','turn','json_ms','sse_ms'],((key[0],key[1],pair['json']['latency_ms'],pair['sse']['latency_ms']) for key,pair in v['pairs'].items()))
    csv_write(folder/'primary_outcomes.csv',['metric','passed','evaluated','unit'],[
        ['Factual retrieval',168,168,'endpoint/entity case'],['Attribution',121,121,'case'],['Presentation',24,24,'HTTP question'],['Routing/status',82,82,'case'],['Conversation',108,108,'turn execution'],['JSON/SSE parity',54,54,'paired turn']])
    csv_write(folder/'refusals.csv',['transport','answerable','success','false_refusals','expected_abstentions','correct_abstentions','false_acceptances'],((t,r['answerable_turns'],r['answerable_turns']-r['answerable_non_success'],r['false_refusals'],r['expected_abstention_turns'],r['correct_abstentions'],r['false_acceptances']) for t,r in data['benchmark']['refusals'].items()))
    accessibility_previews(out)
    (out/'captions.md').write_text('# Figure captions\n\nAbbreviations: KG, knowledge graph; JSON, JavaScript Object Notation; SSE, server-sent events; HTTP, Hypertext Transfer Protocol; p95, 95th percentile.\n\n'+'\n\n'.join('## '+k+'\n\n'+v for k,v in captions.items())+'\n',encoding='utf-8')
    (out/'source_checksums.json').write_text(json.dumps({'audit':str(args.input),'inputs':hashes,'generator_sha256':digest(Path(__file__))},indent=2),encoding='utf-8')
    (out/'validation.json').write_text(json.dumps({'input_hashes_verified':len(hashes),'all_latency_summaries_recomputed':True,'paired_turns':54,'font_path':font,'matplotlib':matplotlib.__version__,'numpy':np.__version__,'figures':checks,'vision_previews':'Approximate full-severity simulations; manual inspection recorded separately'},indent=2),encoding='utf-8')
    (out/'README.md').write_text('''# Chatbot evaluation figures

One main composite and two supplements derived exclusively from the checksum-verified contract-8 local benchmark. See captions.md for manuscript legends and source_data/ for plotted values. Existing manuscript report: ../../docs/manuscript/chatbot-evaluation-20261011/manuscript_results.md.

Reproduce from the repository root (PowerShell):

```powershell
& .\\.audit-temp\\figure-env\\Scripts\\python.exe -B scripts/plot_chatbot_evaluation.py
```

Use --input and --output to override directories. Existing dependencies are documented in requirements-figures.txt; no dependencies were installed or changed. All caches are directed to D: under .audit-temp/chatbot-evaluation-figures. The generator validates original hashes and recomputes quantitative summaries before export; inconsistent sources stop execution.

PDFs are the submission files (embedded TrueType font); SVGs preserve editable text as local vector masters. PNG previews are RGB at 300 dpi. The supplied journal guidance does not accept SVG for submission. Figures are prepared at 183 mm width, Arial approximately 8 pt, white background, no gradients/hatching, lower-case panel labels and strokes >=0.25 pt. No remote assets or font services are used.

previews/ contains normal, grayscale and approximate full-severity protanopia/deuteranopia/tritanopia contact sheets. Matrix previews are diagnostic approximations, not an accessibility certification; labels, line styles and marker shapes preserve interpretation without color. No simulation image is embedded in any vector master.

All cases passed in the selected run; denominators and coverage therefore carry more information than repeated 100% accuracy bars. Suites overlap. Raw distributions are descriptive, not confidence intervals. No clinical truth, RAGAS score, production performance or model-attributable improvement is inferred. Application-wide browser counts and later production smoke tests are intentionally excluded. No new benchmark or live retrieval was run.
''',encoding='utf-8')
    files=sorted(x for x in out.rglob('*') if x.is_file() and x.name!='SHA256SUMS')
    (out/'SHA256SUMS').write_text('\n'.join(digest(x)+'  '+x.relative_to(out).as_posix() for x in files)+'\n',encoding='ascii')
    print(json.dumps({'output':str(out),'figures':checks,'files_checksummed':len(files)}))


if __name__=='__main__':
    main()
