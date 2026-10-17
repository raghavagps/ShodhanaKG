"""Reproducible manuscript figures from stored KG and verified audit artifacts.

No database writes or live model calls. See figures/README.md for scope and sources.
"""
from __future__ import annotations

import argparse
import collections as C
import csv
import hashlib
import io
import json
import os
from pathlib import Path
import shutil
import subprocess
import textwrap
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parent
os.environ.setdefault('MPLCONFIGDIR', str(ROOT / '.audit-temp/figure-mpl'))
os.environ.setdefault('TEMP', str(ROOT / '.audit-temp'))
os.environ.setdefault('TMP', str(ROOT / '.audit-temp'))
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from matplotlib.patches import FancyArrowPatch, Rectangle
from matplotlib.ticker import StrMethodFormatter
import networkx as nx
import numpy as np

DISEASE = {'Disease', 'Dosha', 'Ayurvedic_Diagnosis', 'Symptom', 'Therapy', 'Formulation', 'Assessment_Parameter'}
COMPOUND = {'Compound', 'Shodhana_Method', 'Toxic_Substance', 'Confirmation_Study', 'Media', 'Purified_Compound', 'Shodhana_Type', 'Process', 'Indication', 'Ayurvedic_Formulation'}
GREEN, BLUE, AMBER, GRAY = '#176B50', '#2166A0', '#A65E00', '#7C858D'
NS = '{http://www.w3.org/2000/svg}'

def read_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding='utf-8'))

def pretty(value: str) -> str:
    return value.replace('_', ' ')

def wrap(value: str, width: int = 26) -> str:
    return '\n'.join(textwrap.wrap(value, width=width))

def sid(prefix: str, value: str) -> str:
    return prefix + hashlib.sha256(value.encode()).hexdigest()[:20]

def label(r: dict) -> str:
    fields = ['name', 'label', 'Ayurvedic_Name', 'Common_Name', 'Disease', 'Symptoms', 'Type']
    return next((str(r[k]) for k in fields if r.get(k)), r['@rid'])

def load_graph(path: Path) -> nx.MultiDiGraph:
    data = read_json(path)
    if 'tables' not in data:
        raise ValueError('Expected ArcadeDB snapshot with tables mapping; no schema fallback permitted')
    records = {}
    for rows in data['tables'].values():
        for record in rows:
            rid = record['@rid']
            if rid in records and records[rid] != record:
                raise ValueError(f'Conflicting records for {rid}')
            records[rid] = record
    graph = nx.MultiDiGraph()
    for rid, r in records.items():
        if r.get('@cat') == 'v':
            t = r['@type']
            if t not in DISEASE | COMPOUND:
                raise ValueError(f'Unknown domain membership: {rid}, {t}')
            graph.add_node(rid, record=r, label=label(r), type=t, domain='disease' if t in DISEASE else 'compound')
    for rid, r in records.items():
        if r.get('@cat') == 'e':
            u, v = r['@out'], r['@in']
            if u not in graph or v not in graph:
                raise ValueError(f'Missing endpoint on {rid}: {u} -> {v}')
            graph.add_edge(u, v, key=rid, record=r, predicate=r['@type'])
    return graph

class Figures:
    def __init__(self, args: argparse.Namespace):
        self.args = args
        self.out = args.output
        for folder in ('core', 'supplementary', 'data', 'previews'):
            (self.out / folder).mkdir(parents=True, exist_ok=True)
        self.g = load_graph(args.input)
        self.input_hash = hashlib.sha256(args.input.read_bytes()).hexdigest()
        self.entries = []
        self.sources = [args.input, args.benchmark / 'metrics.csv', args.benchmark / 'conversation_categories.csv', args.benchmark / 'latency.json', args.benchmark / 'benchmark/conversation.json', args.benchmark / 'browser.json', args.catalog, ROOT / 'figures_guidelines.md']
        plt.rcParams.update({'font.family':'Arial', 'font.size':args.font_size, 'svg.fonttype':'none', 'pdf.fonttype':42, 'ps.fonttype':42, 'axes.spines.top':False, 'axes.spines.right':False, 'axes.linewidth':.6, 'lines.linewidth':.8, 'savefig.facecolor':'white'})

    def save(self, fig, name: str, caption: str, metadata: dict | None = None):
        path = self.out / name
        fig.savefig(path.with_suffix('.svg'))
        fig.savefig(path.with_suffix('.pdf'))
        preview=io.BytesIO()
        fig.savefig(preview, format='png', dpi=160)
        preview_path=self.out / 'previews' / (path.name + '.png')
        staging=preview_path.with_suffix('.png.new')
        staging.write_bytes(preview.getvalue())
        staging.replace(preview_path)
        plt.close(fig)
        self.annotate(path.with_suffix('.svg'), metadata or {})
        self.entries.append({'figure':name, 'caption':caption, 'metadata':metadata or {}})
        print('Saved', name, flush=True)

    def annotate(self, path: Path, metadata: dict):
        ET.register_namespace('', NS[1:-1])
        tree = ET.parse(path); root = tree.getroot()
        for old in root.findall(NS+'title')+root.findall(NS+'desc'):root.remove(old)
        ET.SubElement(root, NS+'title').text = path.stem.replace('_', ' ')
        ET.SubElement(root, NS+'desc').text = 'ShodhanaKG; native editable vectors. ' + json.dumps(metadata, ensure_ascii=False)
        lookup = {sid('node-', u):d['record'] for u,d in self.g.nodes(data=True)}
        lookup.update({sid('edge-', k):d['record'] for u,v,k,d in self.g.edges(keys=True,data=True)})
        for element in root.iter():
            if element.get('id') in lookup:
                titles=element.findall(NS+'title')
                for old in titles:element.remove(old)
                ET.SubElement(element, NS+'title').text = json.dumps(lookup[element.get('id')], ensure_ascii=False)
        tree.write(path, encoding='utf-8', xml_declaration=True)

    def bars(self, ax, labels, values, title, color=BLUE):
        ax.barh(range(len(labels)), values, color=color, height=.65)
        ax.set_yticks(range(len(labels)), [pretty(x) for x in labels]); ax.invert_yaxis()
        ax.set_title(title, loc='left', fontweight='bold', pad=10)
        maximum = max(values, default=1) or 1
        ax.set_xlim(0, maximum*1.22)
        for i,v in enumerate(values): ax.text(v+maximum*.025, i, f'{v:,}', va='center')
        ax.xaxis.set_major_formatter(StrMethodFormatter('{x:,.0f}'))

    def schema(self):
        import graphviz
        dot = self.args.dot or shutil.which('dot')
        if not dot: raise RuntimeError('Graphviz dot executable required for schema')
        os.environ['PATH'] = str(Path(dot).parent) + os.pathsep + os.environ['PATH']
        counts = C.Counter((self.g.nodes[u]['type'], d['predicate'], self.g.nodes[v]['type']) for u,v,d in self.g.edges(data=True))
        graph = graphviz.Digraph('schema', engine='dot', strict=False)
        graph.attr(rankdir='LR', compound='true', concentrate='false', splines='spline', nodesep='.6', ranksep='1.4', bgcolor='white', pad='.3', fontname='Arial')
        graph.attr('node', shape='box', style='rounded', fontname='Arial', fontsize='12', margin='.15,.1', penwidth='.8')
        graph.attr('edge', fontname='Arial', fontsize='9', color=GRAY, arrowsize='.7', penwidth='.65')
        for domain, types, color in [('compound',COMPOUND,GREEN),('disease',DISEASE,BLUE)]:
            with graph.subgraph(name='cluster_'+domain) as cluster:
                cluster.attr(label=domain.capitalize()+' Shodhana', color=color, fontcolor=color, fontsize='16', margin='25', style='rounded')
                for t in sorted(types): cluster.node(t, pretty(t), color=color, id='class-'+t)
        for (u,p,v),n in sorted(counts.items()):
            cross = u in COMPOUND and v in DISEASE
            graph.edge(u,v,label=pretty(p),id=sid('schema-edge-',u+p+v),color=AMBER if cross else GRAY,penwidth='1.8' if cross else '.65',constraint='false' if p=='DERIEVED_FROM' else 'true',weight='100' if cross else '1')
        # Layout-only ordering: no semantic edge is created, reversed or counted.
        graph.edge('Indication','Assessment_Parameter',style='invis',minlen='2',weight='10',id='layout-only-domain-order')
        graph.edge('Ayurvedic_Formulation','Assessment_Parameter',style='invis',minlen='2',weight='10',id='layout-only-domain-order-2')
        path = self.out/'core/01_schema'
        path.with_suffix('.dot').write_text(graph.source, encoding='utf-8')
        for fmt in ('svg','pdf','png'):
            target = self.out/'previews/01_schema.png' if fmt=='png' else path.with_suffix('.'+fmt)
            subprocess.run([str(dot), '-T'+fmt, str(path.with_suffix('.dot')), '-o', str(target)],check=True)
        self.entries.append({'figure':'core/01_schema','caption':'Actual stored schema: 17 classes, 25 directed type-level connections using 21 predicates. A class-level Process to Process edge does not assert an instance self-loop. Stored DERIEVED_FROM spelling retained. White domain clusters; no instance counts implied.','metadata':{'nodes':17,'edges':len(counts),'kind':'schema'}})
        self.table('schema_connections', ['source_type','predicate','target_type','instance_edges'], [(u,p,v,n) for (u,p,v),n in sorted(counts.items())])

    def table(self, name, columns, rows):
        with (self.out/'data'/f'{name}.csv').open('w',newline='',encoding='utf-8') as f:
            writer=csv.writer(f); writer.writerow(columns); writer.writerows(rows)

    def composition(self):
        fig,axs=plt.subplots(1,2,figsize=(12,8)); fig.subplots_adjust(left=.2,right=.98,bottom=.1,top=.93,wspace=.95)
        nodes=C.Counter(d['type'] for _,d in self.g.nodes(data=True)); edges=C.Counter(d['predicate'] for *_,d in self.g.edges(data=True))
        for ax,c,title in [(axs[0],nodes,'a  Nodes by entity type'),(axs[1],edges,'b  Edges by predicate')]:
            items=sorted(c.items(),key=lambda x:-x[1]); self.bars(ax,[x for x,n in items],[n for x,n in items],title); ax.set_xlabel('Stored records')
        self.save(fig,'core/02_composition','Counts of unique RIDs; inherited Node rows deduplicated by exact RID, never by display label. All 2,534 vertices and 10,123 edges included.')

    def positions(self, graph: nx.MultiDiGraph) -> dict:
        positions={}
        bridges={n for u,v in graph.edges() if graph.nodes[u]['domain']!=graph.nodes[v]['domain'] for n in (u,v)}
        for domain, offset in [('compound',0),('disease',self.args.spacing)]:
            nodes=sorted(n for n,d in graph.nodes(data=True) if d['domain']==domain)
            if not nodes: continue
            sub=nx.Graph(graph.subgraph(nodes))
            p=nx.spring_layout(sub,seed=self.args.seed,iterations=80,weight=None)
            arr=np.array([p[n] for n in nodes]); arr=(arr-arr.min(axis=0))/(np.ptp(arr,axis=0)+1e-8)
            for n,xy in zip(nodes,arr):
                x,y=xy
                if n in bridges: x=.82+.16*x if domain=='compound' else .02+.16*x
                positions[n]=(offset+x, y)
        return positions

    def graph_figure(self, graph, name, caption, complete=False):
        pos=self.positions(graph); count=graph.number_of_nodes()
        bridge_only=all(graph.nodes[u]['domain']!=graph.nodes[v]['domain'] for u,v in graph.edges())
        if bridge_only:
            for domain,x in [('compound',.93),('disease',self.args.spacing+.07)]:
                nodes=sorted((n for n,d in graph.nodes(data=True) if d['domain']==domain),key=lambda n:(graph.nodes[n]['label'],n))
                for n,y in zip(nodes,np.linspace(.98,.02,len(nodes))):pos[n]=(x,y)
        fig,ax=plt.subplots(figsize=(18,12) if complete else ((12,12) if bridge_only else (12,8))); fig.subplots_adjust(left=.03,right=.97,top=.9,bottom=.12)
        cross=[(u,v,k,d) for u,v,k,d in graph.edges(keys=True,data=True) if graph.nodes[u]['domain']!=graph.nodes[v]['domain']]
        bridges={n for u,v,*_ in cross for n in (u,v)}
        for domain, offset,color in [('compound',0,GREEN),('disease',self.args.spacing,BLUE)]:
            ax.add_patch(Rectangle((offset-.08,-.08),1.16,1.16,fill=False,edgecolor=color,lw=.6))
            ax.text(offset+.5,1.13,domain.capitalize()+' Shodhana',ha='center',color=color,fontsize=12)
        groups=C.defaultdict(list)
        for u,v,k,d in graph.edges(keys=True,data=True): groups[(u,v)].append((k,d))
        for (u,v), edges in groups.items():
            for i,(k,d) in enumerate(sorted(edges)):
                is_cross=graph.nodes[u]['domain']!=graph.nodes[v]['domain']
                rad=(i-(len(edges)-1)/2)*min(.045 if complete else .13, .7/max(1,len(edges)-1))
                if (v,u) in groups: rad+=.16
                if u==v:
                    start=(pos[u][0]-.012,pos[u][1]); end=(pos[u][0]+.012,pos[u][1]); rad=2+i*.5
                else: start,end=pos[u],pos[v]
                arrow=FancyArrowPatch(start,end,connectionstyle=f'arc3,rad={rad}',arrowstyle='-|>',mutation_scale=3 if complete else 6,shrinkA=1.4 if complete else 4,shrinkB=1.4 if complete else 4,linewidth=.3 if complete else .55,color=AMBER if is_cross else GRAY,alpha=.65 if is_cross else (.2 if complete else .65),zorder=2 if is_cross else 1)
                arrow.set_gid(sid('edge-',k)); ax.add_patch(arrow)
        for u,d in graph.nodes(data=True):
            color=GREEN if d['domain']=='compound' else BLUE
            node=ax.scatter(*pos[u],s=(9 if u in bridges else 4) if complete else 38,facecolor='white',edgecolor=color,lw=.5,zorder=4)
            node.set_gid(sid('node-',u))
            visible=bridge_only or self.args.label_mode=='all' or (not complete and count<=35) or (self.args.label_mode=='bridge' and u in bridges)
            if visible:
                offset=(-8,0) if bridge_only and d['domain']=='compound' else ((8,0) if bridge_only else (0,6))
                ax.annotate(wrap(d['label'],40 if bridge_only else 23),pos[u],xytext=offset,textcoords='offset points',ha=('right' if d['domain']=='compound' else 'left') if bridge_only else 'center',va='center' if bridge_only else 'bottom',fontsize=8,zorder=5,bbox=dict(facecolor='white',edgecolor='none',pad=.4))
        ax.set(xlim=(-.16,self.args.spacing+1.16),ylim=(-.14,1.2)); ax.axis('off')
        fig.text(.5,.05,f'{count:,} nodes | {graph.number_of_edges():,} directed edges | {len(cross):,} cross-domain edges\nGreen: compound domain   Blue: disease domain   Amber: USED_FOR_TREATMENT',ha='center',fontsize=9)
        self.table(Path(name).name+'_positions',['node_id','domain','x','y'],[(n,graph.nodes[n]['domain'],*pos[n]) for n in sorted(graph)])
        metadata={'kind':'instances','nodes':count,'edges':graph.number_of_edges(),'cross_edges':len(cross),'node_ids':sorted(graph.nodes),'edge_ids':sorted(k for u,v,k in graph.edges(keys=True)),'label_mode':self.args.label_mode}
        self.save(fig,name,caption,metadata)

    def integration(self):
        g=nx.MultiDiGraph()
        for u,v,k,d in self.g.edges(keys=True,data=True):
            if self.g.nodes[u]['domain']!=self.g.nodes[v]['domain']:
                for n in [u,v]:g.add_node(n,**self.g.nodes[n])
                g.add_edge(u,v,key=k,**d)
        self.graph_figure(g,'core/03_integration','All 75 actual cross-domain edges and 46 incident nodes. Every arrow is a stored USED_FOR_TREATMENT edge, not a symbolic module connector. All endpoint labels shown in alphabetical order; identities and full edge properties remain in SVG tooltips and data tables. This bridge-only detail complements the complete internally connected instance graph.')
        self.table('integration_edges',['edge_id','source_id','source_label','predicate','target_id','target_label'],[(k,u,self.g.nodes[u]['label'],d['predicate'],v,self.g.nodes[v]['label']) for u,v,k,d in g.edges(keys=True,data=True)])

    def workflow(self):
        fig,ax=plt.subplots(figsize=(10,7)); ax.axis('off'); ax.set(xlim=(0,10),ylim=(0,7))
        steps=[('User question','Free-text input'),('Entity and intent resolution','KG types, aliases, ambiguity and constraints'),('Validated retrieval plan','Supported directed paths; no invented relationships'),('Fresh KG retrieval','Exact nodes, edges and properties'),('Deterministic answer construction','Complete results and edge-bound citations'),('JSON / SSE response','Matching terminal metadata and evidence')]
        for i,(title,sub) in enumerate(steps):
            y=6-i
            ax.text(5,y,title+'\n'+sub,ha='center',va='center',bbox=dict(boxstyle='round,pad=.6',facecolor='white',edgecolor=BLUE),fontsize=10)
            if i<5: ax.annotate('',(5,y-.73),(5,y-.28),arrowprops=dict(arrowstyle='->',color=GRAY))
        ax.text(.05,.02,'Structured context guides interpretation; it is revalidated against the KG.\nOptional LLM assistance does not enumerate factual results.',transform=ax.transAxes,fontsize=9)
        self.save(fig,'core/04_retrieval_workflow','Conceptual implementation diagram, not measured data. Based on ChatbotOrchestrator, QueryUnderstanding, ConversationContext, ResultsPresentation and ChatResponseTransport. Prior answer text is not scientific ground truth.')

    def benchmark(self):
        with (self.args.benchmark/'metrics.csv').open(encoding='utf-8-sig') as f: metrics=list(csv.DictReader(f))
        selected=[r for r in metrics if r['metric'] in ['Factual retrieval','Attribution','Presentation','Routing/status','Conversation executions','JSON/SSE semantic parity','Entity resolution','Structured context']]
        fig,axs=plt.subplots(1,2,figsize=(12,6)); fig.subplots_adjust(left=.19,right=.98,wspace=.7,bottom=.24,top=.9)
        ax=axs[0]; y=range(len(selected)); ax.scatter([float(r['percent']) for r in selected],y,color=BLUE)
        ax.set_yticks(y,[r['metric'] for r in selected]); ax.invert_yaxis(); ax.set(xlim=(0,122),xticks=[0,25,50,75,100],xlabel='Case pass rate (%)'); ax.set_title('a  Software evaluation',loc='left',fontweight='bold')
        for i,r in enumerate(selected):ax.text(102,i,f"{r['passed_or_count']}/{r['total']}",va='center')
        latency=read_json(self.args.benchmark/'latency.json'); ax=axs[1]
        for i,r in enumerate(latency):
            ax.plot([r['p50_ms'],r['p95_ms']],[i,i],color=GRAY); ax.scatter(r['p50_ms'],i,color=BLUE,label='Median' if i==0 else None); ax.scatter(r['p95_ms'],i,color=AMBER,marker='D',label='95th percentile' if i==0 else None)
        ax.set_yticks(range(4),['Direct endpoint\n(non-HTTP), n=168','Results-first JSON\nn=24','Conversation JSON\nn=54','Conversation SSE\nn=54']);ax.invert_yaxis();ax.set_xlabel('Response time (ms)');ax.legend(loc='lower right');ax.set_title('b  Local latency summaries',loc='left',fontweight='bold')
        fig.text(.5,.06,'Verified local benchmark: 28 September 2026 report; not a fresh production benchmark\nOverlapping suites are not pooled; timings are different execution paths',ha='center',fontsize=9)
        self.save(fig,'core/05_software_evaluation','Exact denominators from the latest complete verified audit. Latency lines connect recorded median and p95, not confidence intervals or reconstructed distributions. These results predate subsequent production compatibility changes. No clinical accuracy claim.')

    def case_studies(self):
        # Deterministic, declared one-reference subgraphs prevent cross-publication path splicing.
        disease=sorted((n for n,d in self.g.nodes(data=True) if d['type']=='Disease' and d['label'].lower()=='vipadika'))
        if not disease: raise ValueError('No stored Vipadika node for declared treatment case')
        seed=disease[0]
        incident=[(u,v,k,d) for u,v,k,d in self.g.edges(keys=True,data=True) if u==seed and d['predicate'] in {'PRE_TREATED_BY','TREATED_BY','POST_TREATED_BY'}]
        refs=sorted({str(d['record'].get('ref','')) for *_,d in incident})
        ref=max(refs,key=lambda r:len({d['predicate'] for *_,d in incident if str(d['record'].get('ref',''))==r}))
        chosen=[x for x in incident if str(x[3]['record'].get('ref',''))==ref]
        therapies={v for _,v,*_ in chosen}
        chosen += [(u,v,k,d) for u,v,k,d in self.g.edges(keys=True,data=True) if u in therapies and d['predicate']=='USES_FORMULATION' and str(d['record'].get('ref',''))==ref]
        self.detail(chosen,'supplementary/S02_treatment_phases',f'Actual Vipadika case, source node {seed}, reference {ref}. Selected reference maximizes represented treatment-phase types among this node’s records; ties lexical. All selected therapy edges and same-reference formulation edges retained. Missing phases are not invented.')
        # One complete reference-induced compound module; select the smallest covering broad pathway concepts.
        byref=C.defaultdict(list)
        for u,v,k,d in self.g.edges(keys=True,data=True):
            if self.g.nodes[u]['domain']==self.g.nodes[v]['domain']=='compound':byref[str(d['record'].get('ref',''))].append((u,v,k,d))
        required={'UNDERGOES','FOLLOWED_BY','USES_MEDIA','PRODUCES','VALIDATED_BY'}
        eligible=[(len(es),r,es) for r,es in byref.items() if required <= {d['predicate'] for *_,d in es}]
        if not eligible: raise ValueError('No complete reference subgraph covers required purification predicates')
        _,ref,chosen=min(eligible,key=lambda x:(x[0],x[1]))
        self.detail(chosen,'core/06_purification_case',f'All compound-domain edges sharing reference {ref}; smallest edge-count reference subgraph containing UNDERGOES, FOLLOWED_BY, USES_MEDIA, PRODUCES and VALIDATED_BY (lexical tie-break). Illustrative association records, not a validated procedure or efficacy claim.')

    def detail(self, edges, name, caption):
        import graphviz
        graph=graphviz.Digraph(strict=False)
        graph.attr(rankdir='LR',concentrate='false',splines='spline',nodesep='.55',ranksep='1.1',pad='.25',bgcolor='white')
        graph.attr('node',shape='box',fontname='Arial',fontsize='11',style='rounded',color=GREEN)
        graph.attr('edge',fontname='Arial',fontsize='9',arrowsize='.65',penwidth='.65',color=GRAY)
        nodes={n for u,v,*_ in edges for n in [u,v]}
        for n in sorted(nodes):
            d=self.g.nodes[n];graph.node(sid('n',n),wrap(d['label'],24)+'\n'+pretty(d['type'])+' · '+n,id=sid('node-',n),color=GREEN if d['domain']=='compound' else BLUE)
        for u,v,k,d in edges:graph.edge(sid('n',u),sid('n',v),label=pretty(d['predicate'])+'\n'+k,id=sid('edge-',k))
        p=self.out/name;p.with_suffix('.dot').write_text(graph.source,encoding='utf-8')
        for fmt in ['svg','pdf','png']:
            output=self.out/'previews'/(p.name+'.png') if fmt=='png' else p.with_suffix('.'+fmt)
            subprocess.run([str(self.args.dot),'-T'+fmt,str(p.with_suffix('.dot')),'-o',str(output)],check=True)
        self.annotate(p.with_suffix('.svg'),{'kind':'instances','nodes':len(nodes),'edges':len(edges)})
        self.entries.append({'figure':name,'caption':caption,'metadata':{'kind':'instances','nodes':len(nodes),'edges':len(edges)}})
        (self.out/'data'/(p.name+'.json')).write_text(json.dumps({'nodes':[self.g.nodes[n]['record'] for n in sorted(nodes)],'edges':[d['record'] for *_,d in edges]},indent=2,ensure_ascii=False),encoding='utf-8')

    def evidence(self):
        catalog=read_json(self.args.catalog)['entries']; namespaces={str(e['raw']):e['namespace'] for e in catalog}
        types=sorted({d['predicate'] for *_,d in self.g.edges(data=True)})
        values=C.defaultdict(C.Counter)
        for *_,d in self.g.edges(data=True):
            ref=str(d['record'].get('ref','')).strip(); values[d['predicate']][namespaces.get(ref,'Unclassified') if ref else 'Missing']+=1
        fig,axs=plt.subplots(1,2,figsize=(12,8));fig.subplots_adjust(left=.19,right=.98,wspace=.4,bottom=.15,top=.91)
        left=np.zeros(len(types)); colors=[BLUE,GREEN,AMBER,GRAY,'#C4C4C4']
        for cat,color in zip(['PMID','AYUSH','DOI','Unclassified','Missing'],colors):
            a=np.array([values[t][cat] for t in types]);total=np.array([sum(values[t].values()) for t in types]); pct=100*a/total
            axs[0].barh(range(len(types)),pct,left=left,label=cat,color=color);left+=pct
        axs[0].set_yticks(range(len(types)),[pretty(t) for t in types]);axs[0].invert_yaxis();axs[0].set_xlabel('Edge occurrences (%)');axs[0].set_title('a  Reference namespace by predicate',loc='left',fontweight='bold');axs[0].legend(loc='upper center',bbox_to_anchor=(.5,-.08),ncol=3,fontsize=8)
        counts=C.Counter(e['namespace'] for e in catalog); self.bars(axs[1],list(counts),list(counts.values()),'b  Distinct reference identifiers')
        axs[1].set_xlabel('Identifiers (not proven unique publications)')
        self.save(fig,'core/07_evidence_coverage','Namespaces come from the audited catalog, not numeric-length inference. Counts by predicate are repeated edge occurrences; distinct identifier counts use catalog entries. Namespace classification is not evidence that a publication supports the edge.')
        self.table('evidence_coverage',['predicate','namespace','edges'],[(t,n,values[t][n]) for t in types for n in ['PMID','AYUSH','DOI','Unclassified','Missing']])

    def properties(self):
        types=sorted({d['predicate'] for *_,d in self.g.edges(data=True)})
        present=C.defaultdict(C.Counter); totals=C.Counter()
        for *_,d in self.g.edges(data=True):
            r=d['record'];t=d['predicate'];totals[t]+=1
            for k,v in r.items():
                if not k.startswith('@') and k not in ['id'] and v is not None and str(v).strip():present[t][k]+=1
        keys=sorted({k for counts in present.values() for k in counts}); arr=np.array([[100*present[t][k]/totals[t] for k in keys] for t in types])
        fig,ax=plt.subplots(figsize=(max(10,len(keys)*.7),9));fig.subplots_adjust(left=.22,bottom=.26,right=.93,top=.92)
        im=ax.pcolormesh(np.arange(len(keys)+1)-.5,np.arange(len(types)+1)-.5,arr,cmap='Blues',vmin=0,vmax=100,rasterized=False)
        ax.invert_yaxis()
        ax.set_xticks(range(len(keys)),keys,rotation=50,ha='right');ax.set_yticks(range(len(types)),[pretty(t) for t in types]);ax.set_title('Stored edge-property coverage',loc='left',fontweight='bold')
        for i,t in enumerate(types):
            for j,k in enumerate(keys):
                n=present[t][k]
                ax.text(j,i,f'{n}/{totals[t]}' if n else '—',ha='center',va='center',fontsize=7,color='white' if arr[i,j]>55 else 'black')
        colorbar=fig.colorbar(im,ax=ax,label='Populated edges (%)',fraction=.025,pad=.02)
        colorbar.solids.set_rasterized(False)
        self.save(fig,'supplementary/S03_property_coverage','Raw property names retained without semantic merging. Denominator is all edges of each predicate, not clinically eligible edges. Dash = no stored nonempty values; applicability is not established and must not be interpreted as missing required clinical data.')
        self.table('property_coverage',['predicate','property','populated','all_edges'],[(t,k,present[t][k],totals[t]) for t in types for k in keys])

    def connectivity(self):
        g=self.g;degree=[d for _,d in g.degree()]; comp=sorted([len(c) for c in nx.weakly_connected_components(g)],reverse=True)
        fig,axs=plt.subplots(1,3,figsize=(12,4.5));fig.subplots_adjust(left=.07,right=.97,bottom=.22,top=.85,wspace=.48)
        freq=C.Counter(degree);axs[0].scatter(list(freq),list(freq.values()),s=13,color=BLUE);axs[0].set(xscale='symlog',yscale='log',xlabel='Total directed multidegree',ylabel='Nodes',title='a  Degree distribution')
        axs[1].plot(range(1,len(comp)+1),comp,'.',color=BLUE);axs[1].set(yscale='log',xlabel='Component rank',ylabel='Nodes',title='b  Weak components')
        bridges=[(u,v) for u,v,d in g.edges(data=True) if d['predicate']=='USED_FOR_TREATMENT']; sources={u for u,v in bridges};targets={v for u,v in bridges}
        self.bars(axs[2],['Purified compounds','Diseases'],[len(sources),len(targets)],'c  Direct bridge participants');axs[2].set_xlabel('Unique nodes')
        self.save(fig,'supplementary/S04_connectivity','Multidegree counts every parallel edge; weak components ignore direction only for component calculation. Bridge participants are direct endpoints of USED_FOR_TREATMENT, not arbitrary path reachability or efficacy scores.')
        self.table('node_degrees',['node_id','in_degree','out_degree'],[(n,g.in_degree(n),g.out_degree(n)) for n in sorted(g)])
        self.table('weak_components',['rank','nodes'],list(enumerate(comp,1)))

    def robustness_context_failures(self):
        with (self.args.benchmark/'conversation_categories.csv').open(encoding='utf-8-sig') as f: rows=list(csv.DictReader(f))
        fig,ax=plt.subplots(figsize=(9,7));fig.subplots_adjust(left=.3,right=.95,bottom=.15,top=.92)
        for i,r in enumerate(rows):
            ax.scatter(100*int(r['passed'])/int(r['total']),i,color=BLUE);ax.text(102,i,r['passed']+'/'+r['total'],va='center')
        ax.set_yticks(range(len(rows)),[pretty(r['category']) for r in rows]);ax.invert_yaxis();ax.set(xlim=(0,120),xticks=[0,25,50,75,100],xlabel='Execution pass rate (%)',title='Natural-language and conversation robustness')
        self.save(fig,'supplementary/S05_language_robustness','108 executions across 34 scenarios; JSON/SSE executions are paired, not 108 independent questions. Categories and denominators reproduced from the verified audit; no unsupported generalization to all English questions.')
        data=read_json(self.args.benchmark/'benchmark/conversation.json'); chosen=[r for r in data['runs'] if r['transport']=='json' and r['scenario'] in ['pronoun_chain','explicit_topic_override','ambiguous_method']]
        fig,ax=plt.subplots(figsize=(13,max(8,len(chosen)*1.3)));ax.axis('off')
        for i,r in enumerate(chosen):
            res=r['response'];routing=(res.get('context') or {}).get('routing') or {};ctx=res.get('conversation_context') or {};subject=ctx.get('subject') or {};y=1-(i+.5)/len(chosen)
            text=f"{r['scenario']} · turn {r['turn']}\n"+wrap(r['question'],48)
            detail=f"Active: {subject.get('name','No resolved subject')}\nType: {subject.get('type','Unresolved')} | ID: {subject.get('id','—')}\nIntent: {ctx.get('intent') or routing.get('intent') or 'Clarification pending'}\nStatus: {res.get('status')}\nResults: {len(res.get('answer_results',[]))} | Citations: {len(res.get('citations',[]))}"
            for x,txt in [(.01,text),(.55,detail)]:ax.text(x,y,txt,va='center',fontsize=9,bbox=dict(facecolor='white',edgecolor=BLUE,boxstyle='round,pad=.5'),transform=ax.transAxes)
            ax.annotate('',(.53,y),(.49,y),xycoords='axes fraction',arrowprops=dict(arrowstyle='->',color=GRAY))
        self.save(fig,'supplementary/S06_conversation_context','Actual recorded JSON turns: pronoun follow-ups, explicit topic change and ambiguity clarification. Counts are lengths of returned answer_results/citations arrays. Full sent and returned structured contexts are supplied in the accompanying JSON; prior answer wording is not scientific evidence.')
        (self.out/'data/conversation_examples.json').write_text(json.dumps(chosen,indent=2,ensure_ascii=False),encoding='utf-8')
        fig,axs=plt.subplots(1,2,figsize=(11,5));fig.subplots_adjust(left=.23,right=.97,wspace=.65,top=.88,bottom=.18)
        labels=['Conversation failures','Browser unexpected','Browser flaky','False refusals (JSON)','False acceptances (JSON)'];den=[108,328,328,39,13]
        # Read verified counts rather than infer success from absence of files.
        with (self.args.benchmark/'metrics.csv').open(encoding='utf-8-sig') as f: m={r['metric']:r for r in csv.DictReader(f)}
        values=[sum(not r['passed'] for r in data['runs']),int(m['Browser tests']['total'])-int(m['Browser tests']['passed_or_count']),read_json(self.args.benchmark/'browser.json')['stats']['flaky'],int(m['JSON false refusals']['passed_or_count']),int(m['JSON false acceptances']['passed_or_count'])]
        axs[0].scatter(values,range(5),color=BLUE);axs[0].set_yticks(range(5),labels);axs[0].invert_yaxis();axs[0].set(xlim=(-.1,1),xlabel='Observed events',title='a  Verified failure profile')
        for i,(v,n) in enumerate(zip(values,den)):axs[0].text(.12,i,f'{v}/{n}',va='center')
        axs[1].axis('off');axs[1].text(0,.95,'b  Scope limitations',fontweight='bold',va='top');axs[1].text(0,.8,wrap('Chromium and WebKit only; Firefox excluded. English terminology and supported paths are bounded. Graph display caps remain. No clinical validity, institutional-server capacity or unrestricted production security established.',35),va='top',linespacing=1.8)
        self.save(fig,'supplementary/S07_failures_limitations','Latest complete verified audit has no observed failures in these categories. Zero events are shown as points, not fabricated failure distributions. Unsupported requests are not automatically software failures. Denominators overlap and are not pooled.')

    def publications(self):
        with (ROOT/'docs/chatbot/audits/identifier_rules_2026_09_24_v1/reference_classification.csv').open(encoding='utf-8-sig') as f: audit=list(csv.DictReader(f))
        counts=C.Counter(r['Independently verified?'] for r in audit)
        catalog=read_json(self.args.catalog)['entries'];status=C.Counter(e['resolution_status'] for e in catalog)
        fig,axs=plt.subplots(1,2,figsize=(12,6));fig.subplots_adjust(left=.31,right=.97,wspace=.7,bottom=.2,top=.9)
        labels=[wrap(k,34) for k in counts];self.bars(axs[0],labels,list(counts.values()),'a  Identifier audit evidence');axs[0].set_xlabel('Distinct raw references')
        self.bars(axs[1],list(status),list(status.values()),'b  Catalog resolution status');axs[1].set_xlabel('Catalog entries')
        fig.text(.5,.06,'Namespace / registry audit only · Scientific association review: not established\nStages are not interchangeable; no scientific-validation funnel is inferred',ha='center',fontsize=9)
        self.save(fig,'supplementary/S08_publication_status','Identifier audit dated 24 September 2026; catalog status counts reproduced as stored. User-confirmed namespace, registry existence and publication claim support are distinct. No numeric expert-review completion rate is available; it is not plotted as zero.')
        self.sources.append(ROOT/'docs/chatbot/audits/identifier_rules_2026_09_24_v1/reference_classification.csv')
        self.table('identifier_audit_status',['evidence_status','identifiers'],counts.items())

    def validate(self):
        assert self.input_hash==hashlib.sha256(self.args.input.read_bytes()).hexdigest(),'Input changed'
        checks=[]
        for item in self.entries:
            p=self.out/(item['figure']+'.svg');r=ET.parse(p).getroot()
            assert p.stat().st_size>100 and r.get('viewBox'),p
            assert not list(r.iter(NS+'image')),f'Raster in {p}'
            assert list(r.iter(NS+'text')),f'No editable text in {p}'
            assert list(r.iter(NS+'path')) or list(r.iter(NS+'polygon')),p
            ids=[e.get('id','') for e in r.iter()];meta=item['metadata']
            if meta.get('kind')=='instances':
                assert sum(x.startswith('node-') for x in ids)==meta['nodes'],p
                assert sum(x.startswith('edge-') for x in ids)==meta['edges'],p
                for element in r.iter():
                    if element.get('id','').startswith('edge-'):
                        raw=json.loads(element.find(NS+'title').text)
                        assert self.g[raw['@out']][raw['@in']][raw['@rid']]['record']==raw
            if meta.get('kind')=='schema':
                assert sum(x.startswith('class-') for x in ids)==17
                assert sum(x.startswith('schema-edge-') for x in ids)==25
            checks.append({'figure':item['figure'],'svg_xml':True,'editable_text':True,'embedded_rasters':0,'pdf_exists':p.with_suffix('.pdf').exists(),'semantic_counts':meta.get('kind')})
        pairs=C.Counter((u,v) for u,v in self.g.edges());cross=C.Counter((self.g.nodes[u]['type'],d['predicate'],self.g.nodes[v]['type']) for u,v,d in self.g.edges(data=True) if self.g.nodes[u]['domain']!=self.g.nodes[v]['domain'])
        report={'nodes':len(self.g),'edges':self.g.number_of_edges(),'parallel_endpoint_groups':sum(n>1 for n in pairs.values()),'self_loops':nx.number_of_selfloops(self.g),'cross_domain':[(list(k),v) for k,v in cross.items()],'checks':checks}
        (self.out/'validation.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
        (self.out/'manifest.json').write_text(json.dumps({'arguments':{k:str(v) for k,v in vars(self.args).items()},'sources':{str(p.relative_to(ROOT)):hashlib.sha256(p.read_bytes()).hexdigest() for p in self.sources},'figures':self.entries},indent=2,ensure_ascii=False),encoding='utf-8')
        (self.out/'CAPTIONS.md').write_text('# Figure captions\n\n'+'\n\n'.join('## '+e['figure']+'\n\n'+e['caption'] for e in self.entries),encoding='utf-8')

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--input',type=Path,default=ROOT/'.audit-temp/production-20260928/production-records.json')
    parser.add_argument('--benchmark',type=Path,default=ROOT/'docs/audits/final-release-20260928')
    parser.add_argument('--catalog',type=Path,default=ROOT/'api/core/reference_catalog_v1.json')
    parser.add_argument('--output',type=Path,default=ROOT/'figures')
    parser.add_argument('--dot',type=Path,default=ROOT/'.audit-temp/graphviz-runtime/Library/bin/dot.exe')
    parser.add_argument('--seed',type=int,default=42)
    parser.add_argument('--spacing',type=float,default=1.7)
    parser.add_argument('--font-size',type=float,default=9)
    parser.add_argument('--label-mode',choices=['auto','all','bridge'],default='auto')
    parser.add_argument('--validate-existing',action='store_true',help='Recheck already rendered figures and refresh SVG record metadata without changing geometry')
    args=parser.parse_args()
    if args.spacing<1.3:parser.error('Module spacing must be >=1.3 to avoid group overlap')
    f=Figures(args)
    if args.validate_existing:
        manifest=read_json(args.output/'manifest.json')
        f.entries=manifest['figures']
        for item in f.entries:
            if item['figure']=='core/03_integration':
                item['caption']='All 75 actual cross-domain edges and 46 incident nodes; all endpoint labels shown. Every arrow is a stored USED_FOR_TREATMENT edge. SVG metadata and CSV preserve full identities. This bridge-only detail complements the full instance graph.'
            f.annotate(args.output/(item['figure']+'.svg'),item['metadata'])
        f.sources=[ROOT/p for p in manifest['sources']]
        f.validate()
        print('Validated existing',len(f.entries),'figures',flush=True)
        return
    f.schema();f.composition();f.integration();f.workflow();f.benchmark();f.case_studies();f.evidence();f.properties();f.connectivity();f.robustness_context_failures();f.publications()
    f.graph_figure(f.g,'supplementary/S01_complete_instance_graph','Complete actual graph, no sampling or edge concentration. Force layouts computed separately in documented domains with seed 42; bridge nodes constrained to inner sides. All nodes/edges retain IDs and raw metadata. Auto labels suppressed for dense graph; zoomable supplement, not intended for reading every edge at print scale.',complete=True)
    f.validate()
    print('Validated',len(f.entries),'figures',flush=True)

if __name__=='__main__':main()
