"""Local, reproducible structural analysis; never writes to the source KG."""
from __future__ import annotations
import argparse, collections as C, csv, hashlib, io, json, math, os, textwrap, zipfile
from pathlib import Path
import xml.etree.ElementTree as ET
ROOT=Path(__file__).resolve().parent
for key in ('TEMP','TMP','TMPDIR','MPLCONFIGDIR','XDG_CACHE_HOME'):
    p=ROOT/'.audit-temp'/('structure-'+key.lower());p.mkdir(parents=True,exist_ok=True);os.environ[key]=str(p)
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from matplotlib.patches import PathPatch, Polygon, Rectangle, Wedge, Patch
from matplotlib.path import Path as VP
from matplotlib.colors import to_rgb, LogNorm, Normalize
from scipy.cluster.hierarchy import linkage, leaves_list
from scipy.spatial.distance import squareform
import networkx as nx
import numpy as np
from PIL import Image
from plot_integrated_kg import load_graph, COMPOUND, DISEASE, sid

NS='{http://www.w3.org/2000/svg}'
BLUE='#246394';AMBER='#A96809';GRAY='#879099'
TYPES=sorted(COMPOUND)+sorted(DISEASE)
# Categorical, deliberately not an ordered rainbow scale. Labels/IDs are essential.
HEX=['#332288','#88CCEE','#AA4499','#117733','#DDCC77','#882255','#44AA99','#999933','#AA7733','#661100','#004488','#DDAA33','#BB5566','#6699CC','#663366','#447755','#555555']
COLOR=dict(zip(TYPES,HEX))
SHORT={t:''.join(w[0] for w in t.split('_')) for t in TYPES}
# Prevent abbreviation collisions while keeping stable labels.
SHORT.update({'Compound':'Cpd','Confirmation_Study':'CS','Process':'Proc','Purified_Compound':'PC','Disease':'Dis','Dosha':'Dos','Formulation':'Form','Symptom':'Sym','Therapy':'Ther','Media':'Med','Indication':'Ind'})
def pretty(s):return s.replace('_',' ')
def wrap(s,n=24):return '\n'.join(textwrap.wrap(s,n))
def read(p):return json.loads(Path(p).read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(Path(p).read_bytes()).hexdigest()
def ari(a,b):
    """Adjusted Rand index using exact integer contingency pair counts."""
    if len(a)<2:return 1.
    choose=lambda n:n*(n-1)/2
    cells=C.Counter(zip(a,b));ca=C.Counter(a);cb=C.Counter(b)
    observed=sum(choose(n) for n in cells.values());sa=sum(choose(n) for n in ca.values());sb=sum(choose(n) for n in cb.values())
    expected=sa*sb/choose(len(a));den=(sa+sb)/2-expected
    return (observed-expected)/den if den else 1.

class Analysis:
    def __init__(self,a):
        self.a=a;self.out=a.output
        for d in ['core','supplementary','data','previews','submission_jpeg']: (self.out/d).mkdir(parents=True,exist_ok=True)
        self.g=load_graph(a.input);self.input_hash=sha(a.input);self.figures=[];self.tests=[];self.stats={}
        self.edges=list(self.g.edges(keys=True,data=True))
        self.trip=C.Counter((self.g.nodes[u]['type'],d['predicate'],self.g.nodes[v]['type']) for u,v,k,d in self.edges)
        self.cross=[e for e in self.edges if self.g.nodes[e[0]]['domain']!=self.g.nodes[e[1]]['domain']]
        plt.rcParams.update({'font.family':'Arial','font.size':8,'axes.titlesize':9,'axes.labelsize':8,'xtick.labelsize':8,'ytick.labelsize':8,'svg.fonttype':'none','pdf.fonttype':42,'axes.linewidth':.5,'lines.linewidth':.65,'patch.linewidth':.4,'axes.spines.top':False,'axes.spines.right':False,'savefig.facecolor':'white'})
        self.positions={r['node_id']:(float(r['x']),float(r['y'])) for r in csv.DictReader(a.positions.open(encoding='utf-8'))}
        assert set(self.positions)==set(self.g),'Saved positions do not match graph identities'
        counts=C.Counter((self.g.nodes[u]['domain'],self.g.nodes[v]['domain']) for u,v,k,d in self.edges)
        assert (len(self.g),len(self.edges),len(self.trip))==(2534,10123,25)
        assert counts=={('compound','compound'):4123,('disease','disease'):5925,('compound','disease'):75}
        self.stats.update(nodes=len(self.g),edges=len(self.edges),schema_triples=len(self.trip),distinct_predicates=len({x[1] for x in self.trip}),distinct_triples=len({(u,d['predicate'],v) for u,v,k,d in self.edges}),bridge_pairs=len({(u,v) for u,v,k,d in self.cross}))
        pairs=C.Counter((u,v) for u,v,k,d in self.edges)
        self.stats.update(category_counts=dict(C.Counter(d['type'] for _,d in self.g.nodes(data=True))),domain_nodes=dict(C.Counter(d['domain'] for _,d in self.g.nodes(data=True))),domain_edges={f'{s}->{t}':n for (s,t),n in counts.items()},cross_domain_edges=len(self.cross),self_loops=nx.number_of_selfloops(self.g),distinct_endpoint_pairs=len(pairs),parallel_endpoint_pairs=sum(n>1 for n in pairs.values()),maximum_parallel_records=max(pairs.values()),raw_references=len({str(d['record'].get('ref','')) for u,v,k,d in self.edges}))
        self.table('palette',['category','abbreviation','color'],[(t,SHORT[t],COLOR[t]) for t in TYPES])
        self.table('schema_counts',['source_category','predicate','target_category','records'],[(*k,n) for k,n in sorted(self.trip.items())])

    def table(self,name,cols,rows):
        with (self.out/'data'/f'{name}.csv').open('w',encoding='utf-8',newline='') as f:
            w=csv.writer(f);w.writerow(cols);w.writerows(rows)

    def save(self,fig,name,caption,meta=None):
        path=self.out/name
        fig.savefig(path.with_suffix('.svg'));fig.savefig(path.with_suffix('.pdf'))
        b=io.BytesIO();fig.savefig(b,format='png',dpi=300);plt.close(fig)
        bitmap=Image.open(io.BytesIO(b.getvalue())).convert('RGB')
        p=self.out/'previews'/(path.name+'.png');tmp=p.with_suffix('.png.new');bitmap.save(tmp,format='PNG',dpi=(300,300));tmp.replace(p)
        bitmap.save(self.out/'submission_jpeg'/(path.name+'.jpg'),quality=95,dpi=(300,300))
        ET.register_namespace('',NS[1:-1]);tree=ET.parse(path.with_suffix('.svg'));r=tree.getroot()
        ET.SubElement(r,NS+'title').text=path.stem;ET.SubElement(r,NS+'desc').text=caption
        lookup={sid('node-',n):d['record'] for n,d in self.g.nodes(data=True)}
        lookup.update({sid('edge-',k):d['record'] for u,v,k,d in self.edges})
        lookup.update({sid('ribbon-',s+p+t):{'source_category':s,'predicate':p,'target_category':t,'edge_records':n} for (s,p,t),n in self.trip.items()})
        for el in r.iter():
            if el.get('id') in lookup:ET.SubElement(el,NS+'title').text=json.dumps(lookup[el.get('id')],ensure_ascii=False)
        assert r.get('viewBox') and list(r.iter(NS+'text')) and list(r.iter(NS+'path'))
        assert not list(r.iter(NS+'image')),'Raster element in '+name
        ids=[e.get('id','') for e in r.iter()]
        if meta and 'nodes' in meta:
            assert sum(i.startswith('node-') for i in ids)==meta['nodes']
            assert sum(i.startswith('edge-') for i in ids)==meta['edges']
            for el in r.iter():
                if el.get('id','').startswith('edge-'):
                    raw=json.loads(el.find(NS+'title').text);assert self.g[raw['@out']][raw['@in']][raw['@rid']]['record']==raw
        for el in r.iter():
            for k,v in el.attrib.items():
                if k.endswith('href'):assert v.startswith('#')
        tree.write(path.with_suffix('.svg'),encoding='utf-8',xml_declaration=True)
        assert b'/Subtype /Image' not in path.with_suffix('.pdf').read_bytes()
        entry={'name':name,'caption':caption,'width_mm':round(fig.get_figwidth()*25.4,2),'height_mm':round(fig.get_figheight()*25.4,2),'meta':meta or {}}
        self.figures.append(entry);self.tests.append({'figure':name,'vector_svg_pdf':True,'editable_text':True,'no_external_assets':True,'identity_check':bool(meta and 'nodes' in meta)})
        print('Saved',name,flush=True)

    def bars(self):
        fig,ax=plt.subplots(figsize=(180/25.4,4.1));fig.subplots_adjust(left=.12,right=.97,bottom=.19,top=.93)
        x=np.arange(3);nodes=[len(self.g)]+[sum(d['domain']==domain for _,d in self.g.nodes(data=True)) for domain in ['disease','compound']]
        edges=[len(self.edges)]+[sum(self.g.nodes[u]['domain']==domain and self.g.nodes[v]['domain']==domain for u,v,k,d in self.edges) for domain in ['disease','compound']]
        internal=edges.copy();internal[0]-=len(self.cross)
        ax.bar(x-.18,nodes,.34,label='Nodes',color=BLUE)
        ax.bar(x+.18,internal,.34,label='Internal edges',color='#6C7C8C')
        ax.bar(x[0]+.18,len(self.cross),.34,bottom=internal[0],color=AMBER,label='Integration edges')
        for i in range(3):
            ax.text(x[i]-.18,nodes[i]+150,f'{nodes[i]:,}',ha='center');ax.text(x[i]+.18,edges[i]+150,f'{edges[i]:,}',ha='center')
        ax.set(xticks=x,xticklabels=['Complete ShodhanaKG','Disease domain','Compound domain'],ylabel='Stored records',ylim=(0,11700));ax.yaxis.set_major_formatter(matplotlib.ticker.StrMethodFormatter('{x:,.0f}'));ax.legend(frameon=False)
        self.table('network_counts',['network','nodes','edges'],zip(['Complete','Disease','Compound'],nodes,edges))
        self.save(fig,'core/C01_scale','Census counts of unique RIDs. The complete graph includes 75 cross-domain edges; Disease and Compound bars count internal edges only. The three bars are nested views, not disjoint domains. No sampling uncertainty or error bars.')

    def chord(self,ax,trip,types):
        # Each directed predicate triple is its own ribbon, including treatment phases.
        incident=C.Counter()
        for (s,p,t),n in trip.items():incident[s]+=n;incident[t]+=n
        gap=.045;total=sum(incident.values());usable=2*np.pi-gap*len(types);cursor=np.pi/2;sectors={};slots={}
        for t in types:
            span=usable*incident[t]/total;sectors[t]=(cursor,cursor+span);slots[t]=cursor
            ax.add_patch(Wedge((0,0),1,np.degrees(cursor),np.degrees(cursor+span),width=.07,fc=COLOR[t],ec='white',lw=.4))
            angle=cursor+span/2;x,y=1.08*np.cos(angle),1.08*np.sin(angle)
            ax.text(x,y,SHORT[t],ha='left' if x>=0 else 'right',va='center',fontsize=8)
            cursor+=span+gap
        def point(angle,r=.92):return np.array([r*np.cos(angle),r*np.sin(angle)])
        def arc(start,end):
            # Small cubic arc spans retain the exact sector endpoints and width.
            # These are vector Beziers, not a sampled polyline.
            angles=np.linspace(start,end,max(1,math.ceil(abs(end-start)/np.deg2rad(5)))+1)
            vertices=[]
            for first,last in zip(angles[:-1],angles[1:]):
                handle=4/3*np.tan((last-first)/4)*.92
                tangent=lambda a:np.array([-np.sin(a),np.cos(a)])
                vertices.extend([point(first)+handle*tangent(first),point(last)-handle*tangent(last),point(last)])
            return vertices
        for (s,p,t),n in sorted(trip.items()):
            span=usable*n/total;a0=slots[s];a1=a0+span;slots[s]=a1;b0=slots[t];b1=b0+span;slots[t]=b1
            p0,p1,q0,q1=map(point,[a0,a1,b0,b1])
            verts=[p0,p0*.2,q1*.2,q1,*arc(b1,b0),q0*.2,p1*.2,p1,*arc(a1,a0)]
            codes=[VP.MOVETO]+[VP.CURVE4]*(len(verts)-1)
            verts.append(p0);codes.append(VP.CLOSEPOLY)
            ribbon=VP(verts,codes)
            ribbon.should_simplify=False
            cross=s in COMPOUND and t in DISEASE
            path=PathPatch(ribbon,fc=AMBER if cross else COLOR[s],ec='white',alpha=.8,lw=.3,
                           antialiased=True,joinstyle='round',capstyle='round',rasterized=False)
            path.set_gid(sid('ribbon-',s+p+t));ax.add_patch(path)
            # Outward-pointing arrow tip identifies the receiving category.
            angle=(b0+b1)/2;tip=point(angle,.98);base=point(angle,.85);tan=np.array([-np.sin(angle),np.cos(angle)])*min(.025,span*.2)
            ax.add_patch(Polygon([base-tan,tip,base+tan],fc=AMBER if cross else COLOR[s],ec='none'))
        ax.set(xlim=(-1.45,1.45),ylim=(-1.35,1.35),aspect='equal');ax.axis('off')

    def chords(self):
        fig=plt.figure(figsize=(180/25.4,7.5));ax=fig.add_axes([.12,.34,.76,.61]);self.chord(ax,self.trip,TYPES)
        legend=[Patch(fc=COLOR[t],label=f'{SHORT[t]}: {pretty(t)}') for t in TYPES]
        fig.legend(handles=legend,loc='lower center',bbox_to_anchor=(.5,.005),ncol=2,frameon=False,fontsize=8)
        phases=' · '.join(f'{p} {self.trip[("Disease",p,"Therapy")]:,}' for p in ['PRE_TREATED_BY','TREATED_BY','POST_TREATED_BY'])
        fig.text(.5,.325,'Disease → Therapy: '+phases,ha='center',fontsize=8)
        self.save(fig,'core/C02_category_chord','Directed category chord; sectors ordered compound then disease. Ribbon widths count edge records, not clinical strength. Arrow tips identify target sectors. All 25 typed predicate combinations are separate ribbons; the three therapy phases are explicitly listed. Sector arc totals count incoming plus outgoing incidence (twice the edge total overall). Full counts in schema_counts.csv.')
        fig,axs=plt.subplots(1,2,figsize=(11,6.5));fig.subplots_adjust(bottom=.35,top=.92,wspace=.15)
        for ax,domain,types,panel in [(axs[0],'Compound',sorted(COMPOUND),'a'),(axs[1],'Disease',sorted(DISEASE),'b')]:
            trip={k:v for k,v in self.trip.items() if k[0] in types and k[2] in types};self.chord(ax,trip,types);ax.set_title(panel+'  '+domain+' domain',loc='left',fontweight='bold')
        fig.legend(handles=legend,loc='lower center',ncol=3,frameon=False,fontsize=8)
        self.save(fig,'supplementary/S02_domain_chords','Internal edges only: Compound 4,123; Disease 5,925. Same category palette and directed predicate ribbon definition as C02; integration edges are excluded from these internal panels.')

    def network(self,g,pos,name,caption,community=None,small=False):
        fig,ax=plt.subplots(figsize=(180/25.4,6.4) if small else (12,8));fig.subplots_adjust(left=.035,right=.98,top=.94,bottom=.25)
        groups=C.defaultdict(list)
        for u,v,k,d in g.edges(keys=True,data=True):groups[(u,v)].append((k,d))
        for (u,v),es in groups.items():
            p=np.array(pos[u]);q=np.array(pos[v]);delta=q-p;normal=np.array([-delta[1],delta[0]])
            for i,(k,d) in enumerate(es):
                bend=(i-(len(es)-1)/2)*min(.06,.6/max(1,len(es)-1))
                if (v,u) in groups:bend+=.1
                ctrl=(p+q)/2+normal*bend
                if u==v:ctrl=p+np.array([.025,.05]);q=p+np.array([.001,0])
                cross=g.nodes[u]['domain']!=g.nodes[v]['domain'];color=AMBER if cross else '#B7BFC6'
                artist=PathPatch(VP([p,ctrl,q],[VP.MOVETO,VP.CURVE3,VP.CURVE3]),fill=False,ec=color,lw=.25,alpha=.85 if cross else .38)
                artist.set_gid(sid('edge-',k));ax.add_patch(artist)
                direction=q-ctrl;direction=direction/(np.linalg.norm(direction)+1e-12);orth=np.array([-direction[1],direction[0]]);tip=q-direction*.004
                ax.add_patch(Polygon([tip,tip-direction*.006+orth*.002,tip-direction*.006-orth*.002],fc=color,ec='none',alpha=.8))
        for n,d in g.nodes(data=True):
            color=COLOR[d['type']] if community is None else HEX[community[n]%len(HEX)]
            art=ax.scatter(*pos[n],s=4 if small else 8,c=[color],edgecolors='#333333',linewidths=.25,zorder=3);art.set_gid(sid('node-',n))
            if self.a.label_mode=='all' or (self.a.label_mode=='bridge' and any(n in (u,v) for u,v,k,d in self.cross)):
                ax.annotate(wrap(d['label'],20),pos[n],fontsize=8,xytext=(2,2),textcoords='offset points')
        if community is None:
            for x,t in [(0,'Compound Shodhana'),(1.7,'Disease Shodhana')]:
                ax.add_patch(Rectangle((x-.08,-.08),1.16,1.16,fill=False,ec='#777777',lw=.5));ax.text(x+.5,1.13,t,ha='center',fontsize=9)
            ax.set(xlim=(-.12,2.82),ylim=(-.12,1.2))
            ax.text(1.35,.95,'Treatment links\n'+f'{len(self.cross):,} edge records',ha='center',va='top',color=AMBER,fontsize=8)
            fig.legend(handles=[Patch(fc=COLOR[t],label=pretty(t)) for t in TYPES],loc='lower center',ncol=3,frameon=False,fontsize=8)
        else:
            fig.subplots_adjust(bottom=.15)
            count=len(set(community.values()))
            for i in sorted(set(community.values())):
                angle=2*np.pi*i/count
                ax.text(1.05*np.cos(angle),1.05*np.sin(angle),f'C{i+1}',ha='center',va='center',fontsize=8)
            ax.set(xlim=(-1.2,1.2),ylim=(-1.2,1.2),aspect='equal');fig.text(.5,.055,'Colours and C labels identify exploratory communities, not entity categories\nDomain assignments and node identities are retained in metadata / CSV',ha='center',fontsize=8)
        ax.axis('off');self.save(fig,name,caption,{'nodes':len(g),'edges':g.number_of_edges()})

    def heat(self,arr,rows,cols,name,caption,width=180/25.4,height=6,log=False,cell=False):
        fig,ax=plt.subplots(figsize=(width,height));fig.subplots_adjust(left=.32,bottom=.3,right=.9,top=.95)
        values=np.ma.masked_equal(arr,0) if log else arr
        cmap=plt.get_cmap('Blues').copy();cmap.set_bad('white')
        mesh=ax.pcolormesh(values,cmap=cmap,norm=LogNorm(vmin=1,vmax=max(2,arr.max())) if log else Normalize(0,max(1,arr.max())),rasterized=False)
        ax.invert_yaxis();ax.set_xticks(np.arange(len(cols))+.5,cols,rotation=65,ha='right');ax.set_yticks(np.arange(len(rows))+.5,rows)
        cb=fig.colorbar(mesh,ax=ax,fraction=.04,pad=.02);cb.solids.set_rasterized(False)
        if cell:
            for i,j in np.ndindex(arr.shape):
                if arr[i,j]:ax.text(j+.5,i+.5,f'{arr[i,j]:,g}',ha='center',va='center',fontsize=8,color='white' if arr[i,j]>.6*arr.max() else 'black')
        self.save(fig,name,caption)

    def matrices(self):
        sources=sorted({u for u,v,k,d in self.cross},key=lambda n:self.g.nodes[n]['label']);targets=sorted({v for u,v,k,d in self.cross},key=lambda n:self.g.nodes[n]['label'])
        mat=np.zeros((len(sources),len(targets)),int)
        for u,v,k,d in self.cross:mat[sources.index(u),targets.index(v)]+=1
        assert mat.sum()==75 and np.count_nonzero(mat)==56
        self.table('integration_matrix',['source_id','source_label','target_id','target_label','edges'],[(u,self.g.nodes[u]['label'],v,self.g.nodes[v]['label'],mat[i,j]) for i,u in enumerate(sources) for j,v in enumerate(targets)])
        # Full disease labels wrap rather than shrink below 8 pt.
        self.heat(mat,[self.g.nodes[n]['label'] for n in sources],[wrap(self.g.nodes[n]['label'],18) for n in targets],'core/C04_integration_heatmap','29 Purified Compounds × 17 Diseases; 75 stored treatment records occupy 56 distinct pairs. Zero cells are retained; colour is record count, not efficacy. The displayed participants are a subset of all 71 Purified Compounds and 134 Diseases.',height=8.4,cell=True)
        arr=np.zeros((17,17),int)
        for (s,p,t),n in self.trip.items():arr[TYPES.index(s),TYPES.index(t)]+=n
        assert arr.sum()==10123
        self.table('category_matrix',['source','target','edges'],[(s,t,int(arr[i,j])) for i,s in enumerate(TYPES) for j,t in enumerate(TYPES)])
        self.heat(arr,[pretty(t) for t in TYPES],[SHORT[t] for t in TYPES],'supplementary/S03a_category_counts','Directed category matrix; rows source, columns target; logarithmic colour scale with zero white. Diagonal Process cell represents same-type edges, not instance self-loops.',width=9,log=True,cell=True)
        norm=np.divide(arr,arr.sum(axis=1,keepdims=True),out=np.zeros_like(arr,dtype=float),where=arr.sum(axis=1,keepdims=True)!=0)*100
        self.heat(norm,[pretty(t) for t in TYPES],[SHORT[t] for t in TYPES],'supplementary/S03b_category_percent','Percentage of outgoing edges per source category. Rows with no outgoing records remain zero, not missing; values are compositional, not probabilities of clinical outcomes.',width=9)

    def sankey(self,ax,columns,links,colors):
        """Conserved stacked ribbons, exact count-height per column."""
        totals=[C.Counter() for _ in columns]
        for col,s,t,n in links:
            if col==0:totals[col][s]+=n
            totals[col+1][t]+=n
        grand=sum(totals[0].values());height=1.;gap=.012;scale=(height-gap*max(map(len,columns)))/grand;bounds=[]
        for c,names in enumerate(columns):
            y=0;bs={}
            for label in names:
                h=totals[c][label]*scale;bs[label]=(y,y+h);ax.add_patch(Rectangle((c-.025,y),.05,h,fc=colors.get(label,BLUE),ec='none'))
                y+=h+gap
            centers=[sum(bs[label])/2 for label in names];label_y=centers.copy()
            separation=(12/72)/ax.figure.get_figheight()/ax.get_position().height*1.055
            for i in range(1,len(label_y)):label_y[i]=max(label_y[i],label_y[i-1]+separation)
            label_y[-1]=min(label_y[-1],1.)
            for i in range(len(label_y)-2,-1,-1):label_y[i]=min(label_y[i],label_y[i+1]-separation)
            for label,original,placed in zip(names,centers,label_y):
                x=c+(-.045 if c==0 else .045)
                if abs(original-placed)>.002:ax.plot([c,x],[original,placed],color='#555555',lw=.25,zorder=4)
                ax.text(x,placed,pretty(label),ha='right' if c==0 else 'left',va='center',fontsize=8,zorder=5,bbox=dict(facecolor='white',edgecolor='none',pad=.6,alpha=.95))
            bounds.append(bs)
        outgoing=[{k:v[0] for k,v in b.items()} for b in bounds];incoming=[{k:v[0] for k,v in b.items()} for b in bounds]
        for c,s,t,n in links:
            a=outgoing[c][s];b=incoming[c+1][t];h=n*scale;outgoing[c][s]+=h;incoming[c+1][t]+=h
            x=c+.025;z=c+1-.025
            verts=[(x,a),(x+.4,a),(z-.4,b),(z,b),(z,b+h),(z-.4,b+h),(x+.4,a+h),(x,a+h),(x,a)]
            ax.add_patch(PathPatch(VP(verts,[VP.MOVETO]+[VP.CURVE4]*3+[VP.LINETO]+[VP.CURVE4]*3+[VP.CLOSEPOLY]),fc=colors.get(s,BLUE),ec='none',alpha=.6))
        ax.set(xlim=(-.8,len(columns)-.15),ylim=(1.04,-.015));ax.axis('off')

    def sankey_figure(self):
        preds=sorted({p for s,p,t in self.trip});a=C.Counter();b=C.Counter()
        for (s,p,t),n in self.trip.items():a[(s,p)]+=n;b[(p,t)]+=n
        assert sum(a.values())==sum(b.values())==10123
        assert {p:sum(n for (s,q),n in a.items() if q==p) for p in preds}=={p:sum(n for (q,t),n in b.items() if q==p) for p in preds}
        fig,ax=plt.subplots(figsize=(14,10));fig.subplots_adjust(left=.12,right=.88,top=.94,bottom=.05)
        self.sankey(ax,[TYPES,preds,TYPES],[(0,s,p,n) for (s,p),n in sorted(a.items())]+[(1,p,t,n) for (p,t),n in sorted(b.items())],COLOR)
        ax.set_title('Source category → predicate → target category',loc='left')
        self.save(fig,'supplementary/S01_semantic_sankey','Each KG edge contributes one unit through source category, predicate and target category. Both adjacent-column totals equal 10,123; do not add them. This is edge composition, not a causal sequence, patient flow or literal acyclic KG. Predicates keep exact stored spelling.')
        self.table('sankey_links',['stage','source','target','records'],[(0,s,p,n) for (s,p),n in sorted(a.items())]+[(1,p,t,n) for (p,t),n in sorted(b.items())])

    def properties_references(self):
        counts=C.defaultdict(C.Counter);totals=C.Counter();refs=C.Counter()
        for u,v,k,d in self.edges:
            r=d['record'];p=d['predicate'];totals[p]+=1;refs[str(r.get('ref',''))]+=1
            for key,value in r.items():
                if not key.startswith('@') and key!='id' and value is not None and str(value).strip():counts[p][key]+=1
        ps=sorted(totals);keys=sorted({k for x in counts.values() for k in x});arr=np.array([[100*counts[p][k]/totals[p] for k in keys] for p in ps])
        self.heat(arr,ps,keys,'supplementary/S04_property_coverage','Percentage of records containing a nonempty raw property. Denominator is all edges of each predicate, not known clinical eligibility. Zero means unpopulated in this snapshot; applicability is unknown. Exact numerators and denominators accompany the figure.',width=10,height=8)
        self.table('property_coverage',['predicate','property','populated','all_edges'],[(p,k,counts[p][k],totals[p]) for p in ps for k in keys])
        ranked=sorted(refs.items(),key=lambda x:(-x[1],x[0]));values=np.array([n for _,n in ranked]);assert values.sum()==10123 and len(values)==410
        fig,axs=plt.subplots(1,2,figsize=(9,4));fig.subplots_adjust(left=.09,right=.96,bottom=.2,top=.9,wspace=.4)
        axs[0].plot(np.arange(1,len(values)+1),values,color=BLUE);axs[0].set(xlabel='Reference rank',ylabel='Edge records',yscale='log');axs[0].set_title('a  Records per reference',loc='left',fontweight='bold')
        axs[1].plot(np.arange(1,len(values)+1)/len(values)*100,np.cumsum(values)/values.sum()*100,color=BLUE);axs[1].set(xlabel='References, highest count first (%)',ylabel='Cumulative edge records (%)',xlim=(0,100),ylim=(0,100));axs[1].set_title('b  Coverage concentration',loc='left',fontweight='bold')
        self.table('reference_counts',['raw_reference','edge_records'],ranked)
        self.stats['top_10_reference_edge_fraction']=float(values[:10].sum()/values.sum())
        self.save(fig,'supplementary/S05_reference_concentration','410 distinct raw identifiers. Edges sharing a reference are repeated records, not independent publications or independent experiments. Concentration measures dataset composition, not publication quality.')

    def connectivity(self):
        sizes=sorted(map(len,nx.weakly_connected_components(self.g)),reverse=True);isolates=list(nx.isolates(self.g));assert sizes==[2532,1,1]
        degree=np.array([self.g.degree(n) for n in self.g]);neighbours=np.array([len(set(self.g.predecessors(n))|set(self.g.successors(n))) for n in self.g])
        fig,axs=plt.subplots(1,3,figsize=(180/25.4,3.8));fig.subplots_adjust(left=.085,right=.99,bottom=.3,top=.88,wspace=.65)
        axs[0].bar(['Largest','Isolate 1','Isolate 2'],sizes,color=BLUE);axs[0].set_yscale('log');axs[0].tick_params(axis='x',rotation=60);axs[0].set_ylabel('Nodes (log scale)');axs[0].set_title('a  Weak components',loc='left',fontweight='bold')
        for i,n in enumerate(sizes):axs[0].text(i,n*1.15,f'{n:,}',ha='center')
        axs[1].scatter(neighbours,degree,s=3,color=BLUE);axs[1].set(xlabel='Distinct neighbours',ylabel='Total multidegree');axs[1].set_title('b  Record multiplicity',loc='left',fontweight='bold')
        axs[2].bar(['Purified\ncompound','Disease'],[29/71*100,17/134*100],color=[AMBER,BLUE]);axs[2].set(ylabel='Bridge participation (%)',ylim=(0,100));axs[2].set_title('c  Integration',loc='left',fontweight='bold')
        for i,(n,t) in enumerate([(29,71),(17,134)]):axs[2].text(i,100*n/t+3,f'{n}/{t}',ha='center')
        self.save(fig,'core/C05_connectivity','Weak connectivity ignores edge direction only for component membership. Total multidegree counts incoming plus outgoing records; distinct neighbours ignore record multiplicity. Bridge coverage is direct USED_FOR_TREATMENT participation, not efficacy or absence of real-world associations.')
        self.stats['weak_component_sizes']=sizes;self.stats['isolates']=[self.g.nodes[n]['record'] for n in isolates]
        self.table('node_connectivity',['node_id','category','in_degree','out_degree','distinct_neighbours'],[(n,self.g.nodes[n]['type'],self.g.in_degree(n),self.g.out_degree(n),len(set(self.g.predecessors(n))|set(self.g.successors(n)))) for n in self.g])

    def community_analysis(self):
        self.communities={};stability=[]
        for domain in ['complete','compound','disease']:
            nodes=sorted(n for n,d in self.g.nodes(data=True) if domain=='complete' or d['domain']==domain);sub=self.g.subgraph(nodes);projection=nx.Graph();projection.add_nodes_from(nodes)
            for u,v,k,d in sub.edges(keys=True,data=True):
                if u!=v:projection.add_edge(u,v,weight=projection.get_edge_data(u,v,{}).get('weight',0)+1)
            variants={}
            for weighted in [False,True]:
                runs=[];parts=[]
                for seed in range(20):
                    p=nx.community.louvain_communities(projection,weight='weight' if weighted else None,resolution=1,seed=seed)
                    p=sorted(p,key=lambda c:(-len(c),min(c)));lab={n:i for i,c in enumerate(p) for n in c};parts.append(p);runs.append([lab[n] for n in nodes])
                agree=np.array([[ari(a,b) for b in runs] for a in runs]);means=(agree.sum(axis=1)-1)/19;best=int(np.argmax(means));lab={n:i for i,c in enumerate(parts[best]) for n in c}
                assert set(lab)==set(nodes)
                variants[weighted]=(lab,parts[best],best)
                self.table(f'{domain}_{"weighted" if weighted else "unweighted"}_stability',['seed','mean_adjusted_rand','community_count'],[(i,float(means[i]),len(parts[i])) for i in range(20)])
                stability.append([domain,'record-weighted' if weighted else 'unique-pair',best,len(parts[best]),float(means[best]),float(agree[np.triu_indices(20,1)].min())])
                print('Community analysis',domain,weighted,'selected seed',best,flush=True)
            base,parts,best=variants[False];other=variants[True][0];comparison=ari([base[n] for n in nodes],[other[n] for n in nodes]);self.communities[domain]=(base,parts)
            self.stats[domain+'_community_analysis']={'selected_seed':best,'communities':len(parts),'weighted_vs_unweighted_ari':comparison}
            self.table(domain+'_community_assignments',['node_id','domain','category','community_unweighted','community_record_weighted'],[(n,self.g.nodes[n]['domain'],self.g.nodes[n]['type'],base[n]+1,other[n]+1) for n in nodes])
            # Community-centred positions: separate local layouts, then radial packing.
            positions={};largest=max(map(len,parts))
            for i,c in enumerate(parts):
                angle=2*np.pi*i/len(parts);center=np.array([.7*np.cos(angle),.7*np.sin(angle)]);radius=min(.28,.1+.18*np.sqrt(len(c)/largest));local=nx.spring_layout(projection.subgraph(sorted(c)),seed=self.a.seed,iterations=35,weight=None)
                for n,xy in local.items():positions[n]=center+radius*xy
            self.network(sub,positions,f'supplementary/S06_{domain}_communities',f'Exploratory {domain} communities from the undirected unique-endpoint projection; Louvain resolution 1, medoid partition across seeds 0–19. Original directed multigraph is rendered, not collapsed. Weighted sensitivity and assignments supplied. Singleton isolates remain separate. Colours are community labels, not validated biological classes.',community=base)
        self.table('community_stability_summary',['network','projection','selected_seed','communities','mean_ARI','minimum_pairwise_ARI'],stability)
        fig,ax=plt.subplots(figsize=(10,4));fig.subplots_adjust(left=.3,bottom=.2,right=.94,top=.94)
        labels=[r[0]+' / '+r[1] for r in stability];ax.scatter([r[4] for r in stability],range(6),label='Selected partition mean ARI',color=BLUE);ax.scatter([r[5] for r in stability],range(6),marker='D',label='Minimum pairwise ARI',color=AMBER);ax.set_yticks(range(6),labels);ax.invert_yaxis();ax.set(xlim=(-.05,1.05),xlabel='Adjusted Rand index');ax.legend(frameon=False,loc='lower left')
        self.save(fig,'supplementary/S07a_community_stability','20 seeds per network per projection. Selected partition maximizes mean agreement; ties use lowest seed. Comparison with multiplicity-weighted projection is exploratory sensitivity, not evidence that communities are clinically valid. ARI is adjusted Rand index.')
        base,parts=self.communities['complete'];arr=np.array([[sum(self.g.nodes[n]['type']==t for n in c) for t in TYPES] for c in parts]);assert arr.sum()==2534
        self.heat(arr,[f'C{i+1} (n={len(c)})' for i,c in enumerate(parts)],[SHORT[t] for t in TYPES],'supplementary/S07b_community_composition','Node counts by exploratory complete-graph community and semantic category. Communities ordered by size, tie by stable RID; colours encode counts, not category identity.',width=10,height=max(5,len(parts)*.24),log=True,cell=True)
        links=[(0,t,f'C{i+1}',int(arr[i,j])) for i in range(len(parts)) for j,t in enumerate(TYPES) if arr[i,j]]
        fig,ax=plt.subplots(figsize=(10,max(7,len(parts)*.3)));fig.subplots_adjust(left=.17,right=.88,bottom=.04,top=.97);self.sankey(ax,[TYPES,[f'C{i+1}' for i in range(len(parts))]],links,COLOR)
        self.save(fig,'supplementary/S07c_category_community_alluvial','Every node contributes once from its semantic entity category to an algorithm-derived community (2,534 total). This is a membership cross-tabulation, not a temporal or biological transition.')
        self.table('community_composition',['community','category','nodes'],[(i+1,t,int(arr[i,j])) for i in range(len(parts)) for j,t in enumerate(TYPES)])

    def similarity_paths(self):
        therapy=C.defaultdict(set)
        for u,v,k,d in self.edges:
            if d['predicate'] in ['PRE_TREATED_BY','TREATED_BY','POST_TREATED_BY']:therapy[u].add(v)
        nodes=sorted(therapy,key=lambda n:(self.g.nodes[n]['label'],n));n=len(nodes);mat=np.eye(n)
        for i,u in enumerate(nodes):
            for j,v in enumerate(nodes[:i]):mat[i,j]=mat[j,i]=len(therapy[u]&therapy[v])/len(therapy[u]|therapy[v])
        assert np.allclose(mat,mat.T) and np.allclose(np.diag(mat),1)
        order=leaves_list(linkage(squareform(1-mat,checks=True),method='average'));ordered=mat[np.ix_(order,order)]
        fig,ax=plt.subplots(figsize=(9,8));fig.subplots_adjust(left=.12,right=.9,bottom=.15,top=.94);m=ax.pcolormesh(ordered,vmin=0,vmax=1,cmap='Blues',rasterized=False);ax.invert_yaxis();ticks=np.arange(0,n,10);ax.set_xticks(ticks+.5,[str(i+1) for i in ticks]);ax.set_yticks(ticks+.5,[str(i+1) for i in ticks]);ax.set(xlabel='Disease index in clustered order',ylabel='Disease index in clustered order');cb=fig.colorbar(m,ax=ax);cb.solids.set_rasterized(False)
        excluded=[node for node,d in self.g.nodes(data=True) if d['type']=='Disease' and node not in therapy];self.stats['similarity_included']=n;self.stats['similarity_excluded']=excluded
        self.save(fig,'supplementary/S08_disease_therapy_similarity',f'Jaccard similarity of distinct Therapy neighbours for {n} Diseases; {len(excluded)} empty-neighbour diseases excluded and listed. PRE_TREATED_BY, TREATED_BY and POST_TREATED_BY are pooled only for this declared similarity projection. Average linkage orders rows; indices map to full labels/RIDs in CSV. Neighbourhood similarity is not clinical similarity.')
        self.table('similarity_order',['plot_index','node_id','label'],[(i+1,nodes[j],self.g.nodes[nodes[j]]['label']) for i,j in enumerate(order)])
        self.table('similarity_matrix',['source_id']+nodes,[[u]+list(mat[i]) for i,u in enumerate(nodes)])
        links=C.defaultdict(lambda:C.defaultdict(set))
        for u,v,k,d in self.edges:
            if d['predicate'] in ['UNDERGOES','PRODUCES','USED_FOR_TREATMENT']:links[(u,d['predicate'])][v].add(str(d['record'].get('ref','')))
        paths=[]
        for c,d in self.g.nodes(data=True):
            if d['type']!='Compound':continue
            for method,r1 in links[(c,'UNDERGOES')].items():
                if self.g.nodes[method]['type']!='Shodhana_Method':continue
                for purified,r2 in links[(method,'PRODUCES')].items():
                    if self.g.nodes[purified]['type']!='Purified_Compound':continue
                    for disease,r3 in links[(purified,'USED_FOR_TREATMENT')].items():
                        shared=(r1&r2&r3)-{''};paths.append((c,method,purified,disease,sorted(shared)))
        consistent=[p for p in paths if p[4]];labels=['Compound nodes','Disease nodes','Endpoint pairs','Node-sequence paths']
        def counts(ps):return [len({p[0] for p in ps}),len({p[3] for p in ps}),len({(p[0],p[3]) for p in ps}),len(ps)]
        a,b=counts(paths),counts(consistent);self.stats['supported_path_counts']={'structural':dict(zip(labels,a)),'shared_reference':dict(zip(labels,b))}
        fig,ax=plt.subplots(figsize=(9,4));fig.subplots_adjust(bottom=.25,left=.1,right=.98,top=.93);x=np.arange(4);ax.bar(x-.18,a,.34,color=BLUE,label='Structural path');ax.bar(x+.18,b,.34,color=AMBER,label='Common exact reference')
        for i in range(4):
            for dx,value in [(-.18,a[i]),(.18,b[i])]:ax.text(i+dx,value+max(a)*.025,f'{value:,}',ha='center')
        ax.yaxis.set_major_formatter(matplotlib.ticker.StrMethodFormatter('{x:,.0f}'))
        ax.set(xticks=x,xticklabels=labels,ylabel='Count',ylim=(0,max(a)*1.2));ax.legend(frameon=False)
        self.save(fig,'supplementary/S09_supported_paths','Exact path UNDERGOES → PRODUCES → USED_FOR_TREATMENT with Compound → Shodhana Method → Purified Compound → Disease node types. Structural paths may join different sources. A nonempty exact-reference intersection is reported separately; it still does not prove a single experimental context or clinical efficacy. No unsupported alternate path is inferred.')
        self.table('supported_paths',['compound','method','purified_compound','disease','common_raw_references'],[(*p[:4],';'.join(p[4])) for p in paths])

    def palette_check(self):
        # Machado severe-condition linear RGB matrices; tritan is an approximation.
        matrices=[np.eye(3),np.array([[.152286,1.052583,-.204868],[.114503,.786281,.099216],[-.003882,-.048116,1.051998]]),np.array([[.367322,.860646,-.227968],[.280085,.672501,.047413],[-.011820,.042940,.968881]]),np.array([[1.255528,-.076749,-.178779],[-.078411,.930809,.147602],[.004733,.691367,.303900]])]
        rgb=np.array([to_rgb(COLOR[t]) for t in TYPES]);linear=np.where(rgb<=.04045,rgb/12.92,((rgb+.055)/1.055)**2.4)
        fig,ax=plt.subplots(figsize=(10,6));fig.subplots_adjust(left=.28,bottom=.1,top=.9,right=.97)
        names=['Normal','Protan simulation','Deutan simulation','Tritan approximation'];rows=[]
        for j,matrix in enumerate(matrices):
            z=np.clip(linear@matrix.T,0,1);sim=np.where(z<=.0031308,12.92*z,1.055*z**(1/2.4)-.055)
            for i,t in enumerate(TYPES):ax.add_patch(Rectangle((j,i),.8,.8,fc=sim[i],ec='none'));rows.append([names[j],t,*map(float,sim[i])])
        ax.set(xlim=(-.1,4),ylim=(17,0),xticks=np.arange(4)+.4,xticklabels=names,yticks=np.arange(17)+.4,yticklabels=[pretty(t) for t in TYPES]);ax.xaxis.tick_top();ax.spines[['top','right','bottom','left']].set_visible(False);ax.tick_params(length=0)
        self.save(fig,'supplementary/S10_palette_assessment','Severe-condition colour-vision stress test using Machado matrices in linear sRGB, clipping and re-encoding for display. Tritan is an approximation, not a validated simulation of individual tritanopia. Some category colours converge: labels, abbreviations and domain placement are mandatory. This is not a claim of universal 17-colour accessibility. Source: https://colour.readthedocs.io/en/develop/_modules/colour/blindness/machado2009.html')
        self.table('palette_simulations',['condition','category','r','g','b'],rows)

    def finish(self):
        assert sha(self.a.input)==self.input_hash
        positions=np.array(list(self.positions.values()));assert np.isfinite(positions).all()
        assert max(self.positions[n][0] for n,d in self.g.nodes(data=True) if d['domain']=='compound')<min(self.positions[n][0] for n,d in self.g.nodes(data=True) if d['domain']=='disease')
        self.stats['input_unchanged']=True;self.stats['module_positions_separated']=True
        previous=self.out/'manifest.json'
        if self.a.only!='all' and previous.exists():
            old=read(previous)
            assert old['input_sha256']==self.input_hash and old['positions_sha256']==sha(self.a.positions), 'Selective rebuild requires matching inputs'
            assert old['arguments']['seed']==str(self.a.seed) and old['arguments']['label_mode']==self.a.label_mode, 'Selective rebuild requires matching layout options'
            self.figures=list({e['name']:e for e in old['figures']+self.figures}.values())
            self.tests=list({e['figure']:e for e in read(self.out/'validation.json')+self.tests}.values())
            self.stats=read(self.out/'analysis_results.json')|self.stats
        self.figures.sort(key=lambda e:e['name']);self.tests.sort(key=lambda e:e['figure'])
        (self.out/'analysis_results.json').write_text(json.dumps(self.stats,indent=2),encoding='utf-8')
        (self.out/'validation.json').write_text(json.dumps(self.tests,indent=2),encoding='utf-8')
        (self.out/'manifest.json').write_text(json.dumps({'input':str(self.a.input),'input_sha256':self.input_hash,'positions_sha256':sha(self.a.positions),'script_sha256':sha(__file__),'loader_sha256':sha(ROOT/'plot_integrated_kg.py'),'guidelines_sha256':sha(ROOT/'figures_guidelines.md'),'versions':{'python':__import__('platform').python_version(),'matplotlib':matplotlib.__version__,'networkx':nx.__version__,'numpy':np.__version__,'scipy':__import__('scipy').__version__},'arguments':{k:str(v) for k,v in vars(self.a).items()},'figures':self.figures},indent=2),encoding='utf-8')
        (self.out/'CAPTIONS.md').write_text('# Figure captions\n\n'+'\n\n'.join('## '+e['name']+'\n\n'+e['caption'] for e in self.figures),encoding='utf-8')
        (self.out/'INDEX.md').write_text('# Structural-analysis figures\n\n[Methods and reproduction](README.md) · [Results and metrics](RESULTS.md) · [Figure captions](CAPTIONS.md)\n\nUse PDF for journal submission and SVG for editing. Core and supplementary files are separate.\n\n'+'\n'.join(f"- **{e['name']}**: [SVG]({e['name']}.svg) · [PDF]({e['name']}.pdf) · [Preview](previews/{Path(e['name']).name}.png)" for e in self.figures),encoding='utf-8')
        print('VALIDATED',len(self.figures),'figures; results:',json.dumps(self.stats),flush=True)

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--input',type=Path,default=ROOT/'.audit-temp/production-20260928/production-records.json');p.add_argument('--output',type=Path,default=ROOT/'figures/structure_analysis');p.add_argument('--positions',type=Path,default=ROOT/'figures/data/S01_complete_instance_graph_positions.csv');p.add_argument('--seed',type=int,default=42);p.add_argument('--label-mode',choices=['auto','all','bridge'],default='auto');p.add_argument('--only',choices=['all','static','communities','networks'],default='all')
    a=p.parse_args();s=Analysis(a)
    if a.only in ['all','static']:
        s.bars();s.chords();s.matrices();s.sankey_figure();s.properties_references();s.connectivity();s.similarity_paths();s.palette_check()
    if a.only in ['all','communities']:s.community_analysis()
    if a.only in ['all','networks']:
        s.network(s.g,s.positions,'core/C03_category_coloured_graph','Complete instance graph: category-coloured nodes, quiet internal edges and amber integration edges. All RIDs, directions and properties preserved. Original saved positions reused; node size is constant, not a centrality measure. Dense overview uses metadata instead of full visible labels.',small=True)
        s.network(s.g,s.positions,'supplementary/S00_complete_graph_large','Larger zoomable companion of C03 with identical positions, categories and complete graph membership. Isolates and parallel edges retained; zooming is required to inspect dense regions.')
    s.finish()

if __name__=='__main__':main()
