"""Layout-only complete KG figures; existing data and analysis files are read-only."""
from __future__ import annotations
import argparse
import collections as C
import csv
import json
import math
from pathlib import Path

from plot_kg_structure_analysis import (ROOT, Analysis, COLOR, SHORT, TYPES, AMBER,
    NS, sha, load_graph, sid, pretty, plt, np, PathPatch, Polygon, Rectangle, Patch, VP)
from scipy.optimize import linear_sum_assignment
from scipy.spatial.distance import pdist

# Drawing anchors only: they do not define communities or graph relationships.
ANCHORS = {
    'Compound':(.20,1.12), 'Toxic_Substance':(.49,1.16),
    'Shodhana_Type':(.73,1.18), 'Ayurvedic_Formulation':(1.08,1.15),
    'Shodhana_Method':(.61,.85), 'Media':(.20,.68),
    'Process':(.64,.58), 'Purified_Compound':(1.10,.68),
    'Confirmation_Study':(.43,.24), 'Indication':(1.03,.23),
    'Disease':(2.34,.88), 'Assessment_Parameter':(2.27,1.30),
    'Symptom':(3.07,1.23), 'Ayurvedic_Diagnosis':(2.13,.50),
    'Dosha':(2.02,.94), 'Therapy':(2.54,.32), 'Formulation':(3.12,.37),
}

def hex_slots(n: int, spacing: float = .020) -> np.ndarray:
    """Distinct lattice sites, selected by distance from the type anchor."""
    extent=math.ceil(math.sqrt(n))+2
    xy=np.array([(spacing*(i+.5*(j%2)),spacing*math.sqrt(3)/2*j)
                 for j in range(-extent,extent+1) for i in range(-extent,extent+1)])
    order=sorted(range(len(xy)),key=lambda i:(float(xy[i]@xy[i]),float(xy[i,1]),float(xy[i,0])))
    return xy[order[:n]]

def type_layout(g) -> tuple[dict,dict]:
    """Keep types apart and minimize total squared incident length within groups."""
    groups={t:sorted(n for n,d in g.nodes(data=True) if d['type']==t) for t in TYPES}
    slots={t:hex_slots(len(ns))+np.array(ANCHORS[t]) for t,ns in groups.items()}
    positions={n:slots[t][i] for t,ns in groups.items() for i,n in enumerate(ns)}
    neighbours={n:sorted((set(g.predecessors(n))|set(g.successors(n)))-{n}) for n in g}
    # Coordinate descent: only drawing positions move; graph membership is untouched.
    for _ in range(8):
        for t,ns in groups.items():
            desired=np.array([np.mean([positions[v] for v in neighbours[n]],axis=0)
                              if neighbours[n] else positions[n] for n in ns])
            degree=np.array([max(1,len(neighbours[n])) for n in ns])
            cost=((desired[:,None,:]-slots[t][None,:,:])**2).sum(axis=2)*degree[:,None]
            rows,cols=linear_sum_assignment(cost)
            for row,col in zip(rows,cols):positions[ns[row]]=slots[t][col]
    return positions,groups

def crossing_proxy(g, positions: dict) -> int:
    """Straight-segment intersections of unique undirected endpoint pairs.

    A layout diagnostic, not a graph-analysis result or an exact curved-edge count.
    Shared endpoints and collinear contacts are excluded.
    """
    pairs=sorted({tuple(sorted((u,v))) for u,v in g.edges() if u!=v})
    a=np.array([positions[u] for u,v in pairs]);b=np.array([positions[v] for u,v in pairs])
    cross=lambda x,y:x[...,0]*y[...,1]-x[...,1]*y[...,0]
    total=0
    for i in range(len(pairs)-1):
        c=a[i+1:];d=b[i+1:];v=b[i]-a[i];w=d-c
        hit=(cross(v,c-a[i])*cross(v,d-a[i]) < -1e-14)&(cross(w,a[i]-c)*cross(w,b[i]-c) < -1e-14)
        total+=int(hit.sum())
    return total

def reduce_crossings(g, positions: dict, groups: dict, iterations: int = 2400) -> dict:
    """Accept only within-type swaps that reduce straight-segment crossings."""
    nodes=sorted(g);index={n:i for i,n in enumerate(nodes)}
    xy=np.array([positions[n] for n in nodes]);pairs=sorted({tuple(sorted((index[u],index[v]))) for u,v in g.edges() if u!=v})
    endpoints=np.array(pairs);a=xy[endpoints[:,0]];b=xy[endpoints[:,1]]
    incident={i:np.flatnonzero((endpoints==i).any(axis=1)) for i in range(len(nodes))}
    cross=lambda x,y:x[...,0]*y[...,1]-x[...,1]*y[...,0]
    def count(moving):
        total=0;other=np.ones(len(pairs),bool);other[moving]=False
        for start in range(0,len(moving),24):
            ids=moving[start:start+24];p=a[ids,None,:];q=b[ids,None,:];c=a[None,:,:];d=b[None,:,:]
            hit=(cross(q-p,c-p)*cross(q-p,d-p)<-1e-14)&(cross(d-c,p-c)*cross(d-c,q-c)<-1e-14)
            hit &= other[None,:] | (np.arange(len(pairs))[None,:]>ids[:,None])
            total+=int(hit.sum())
        return total
    rng=np.random.default_rng(42);types=[t for t in TYPES if len(groups[t])>1]
    weights=np.array([sum(len(incident[index[n]]) for n in groups[t]) for t in types],float);weights/=weights.sum()
    accepted=0;reduction=0
    for step in range(iterations):
        t=rng.choice(types,p=weights);u,v=[index[n] for n in rng.choice(groups[t],2,replace=False)]
        moving=np.union1d(incident[u],incident[v]);before=count(moving)
        xy[[u,v]]=xy[[v,u]];a[moving]=xy[endpoints[moving,0]];b[moving]=xy[endpoints[moving,1]]
        after=count(moving)
        if after<before:accepted+=1;reduction+=before-after
        else:
            xy[[u,v]]=xy[[v,u]];a[moving]=xy[endpoints[moving,0]];b[moving]=xy[endpoints[moving,1]]
        if (step+1)%400==0:print(f'Layout swaps {step+1}: accepted {accepted}; proxy reduction {reduction:,}',flush=True)
    return {n:xy[index[n]] for n in nodes}

class Renderer:
    save=Analysis.save

    def __init__(self,args):
        self.out=args.output;self.g=load_graph(args.input);self.edges=list(self.g.edges(keys=True,data=True))
        self.trip=C.Counter((self.g.nodes[u]['type'],d['predicate'],self.g.nodes[v]['type']) for u,v,k,d in self.edges)
        self.figures=[];self.tests=[]
        for folder in ['core','supplementary','previews','submission_jpeg']:(self.out/folder).mkdir(parents=True,exist_ok=True)
        plt.rcParams.update({'font.family':'Arial','font.size':8,'svg.fonttype':'none','pdf.fonttype':42,'savefig.facecolor':'white'})

    def render(self,pos,groups,large=False):
        width=12 if large else 180/25.4
        fig,ax=plt.subplots(figsize=(width,width*.70))
        fig.subplots_adjust(left=.025,right=.975,bottom=.26,top=.94)
        ax.set(xlim=(-.08,3.49),ylim=(-.08,1.70),aspect='equal');ax.axis('off')
        pairs=C.defaultdict(list)
        for u,v,k,d in self.edges:pairs[u,v].append((k,d))
        # Grey connections first, orange integration links above them, all below nodes.
        for (u,v),es in sorted(pairs.items(),key=lambda e:self.g.nodes[e[0][0]]['domain']!=self.g.nodes[e[0][1]]['domain']):
            p=np.array(pos[u]);q=np.array(pos[v]);delta=q-p;normal=np.array([-delta[1],delta[0]])
            cross=self.g.nodes[u]['domain']!=self.g.nodes[v]['domain'];color=AMBER if cross else '#9DA6AF'
            for i,(k,d) in enumerate(sorted(es)):
                bend=(i-(len(es)-1)/2)*min(.014,.10/max(1,len(es)-1))
                if (v,u) in pairs:bend+=.065
                ctrl=(p+q)/2+normal*bend
                if u==v:ctrl=p+np.array([.03,.04])
                patch=PathPatch(VP([p,ctrl,q],[VP.MOVETO,VP.CURVE3,VP.CURVE3]),fill=False,ec=color,lw=.25,alpha=.72 if cross else .12,zorder=1.5 if cross else 1)
                patch.set_gid(sid('edge-',k));ax.add_patch(patch)
                direction=q-ctrl;direction/=np.linalg.norm(direction)+1e-12
                orth=np.array([-direction[1],direction[0]]);tip=q-direction*.006
                ax.add_patch(Polygon([tip,tip-direction*.009+orth*.003,tip-direction*.009-orth*.003],fc=color,ec='none',alpha=.72 if cross else .18,zorder=1.5 if cross else 1))
        for n,d in self.g.nodes(data=True):
            artist=ax.scatter(*pos[n],s=5 if large else 2,facecolor=COLOR[d['type']],edgecolors='#333333',linewidths=.25,zorder=3)
            artist.set_gid(sid('node-',n))
        for t,ns in groups.items():
            points=np.array([pos[n] for n in ns]);center=np.array(ANCHORS[t])
            y=float(points[:,1].max())+.035
            ax.text(center[0],y,f'{SHORT[t]} · {len(ns):,}',ha='center',va='bottom',fontsize=8,zorder=4,bbox=dict(fc='white',ec='none',pad=1.1))
        for left,right,title in [(-.04,1.38,'Compound Shodhana'),(1.94,3.46,'Disease Shodhana')]:
            ax.add_patch(Rectangle((left,-.04),right-left,1.65,fill=False,ec='#A4A4A4',lw=.5,zorder=0))
            ax.text((left+right)/2,1.64,title,ha='center',va='bottom',fontsize=9)
        ax.text(1.66,1.20,'Integration\n75 edge records',ha='center',va='center',color=AMBER,fontsize=8)
        fig.legend(handles=[Patch(fc=COLOR[t],label=f'{SHORT[t]}: {pretty(t)}') for t in TYPES],loc='lower center',ncol=3,frameon=False,fontsize=8)
        name='supplementary/S00_complete_graph_large' if large else 'core/C03_category_coloured_graph'
        self.save(fig,name,'Complete snapshot grouped by node type within the two fixed domains. Every node and directed edge is retained, including isolates and parallel records. Node colour identifies type; size is constant. Normal edges are grey; cross-domain edges orange. Type placement and within-type assignment are drawing choices, not communities or scientific similarity. Type labels show node counts. Dense links require zooming.',{'nodes':len(self.g),'edges':len(self.edges)})

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--input',type=Path,default=ROOT/'.audit-temp/production-20260928/production-records.json')
    parser.add_argument('--output',type=Path,default=ROOT/'figures/type_clustered')
    args=parser.parse_args()
    protected=[args.input,ROOT/'figures/data/S01_complete_instance_graph_positions.csv']+list((ROOT/'figures/structure_analysis').rglob('*'))
    protected={p:sha(p) for p in protected if p.is_file()}
    renderer=Renderer(args);g=renderer.g;pos,groups=type_layout(g)
    pos=reduce_crossings(g,pos,groups)
    assert len(pos)==2534 and g.number_of_edges()==10123
    assert len({tuple(p) for p in pos.values()})==len(g)
    assert max(pos[n][0] for n,d in g.nodes(data=True) if d['domain']=='compound') < min(pos[n][0] for n,d in g.nodes(data=True) if d['domain']=='disease')
    distance=float(pdist(np.array(list(pos.values()))).min())
    assert distance>=.0199,'Overlapping lattice points'
    old={r['node_id']:(float(r['x']),float(r['y'])) for r in csv.DictReader((ROOT/'figures/data/S01_complete_instance_graph_positions.csv').open())}
    quality={'minimum_node_spacing':distance,'straight_segment_crossing_proxy_before':crossing_proxy(g,old),'straight_segment_crossing_proxy_after':crossing_proxy(g,pos)}
    print(json.dumps(quality),flush=True)
    with (args.output/'layout_positions.csv').open('w',newline='',encoding='utf-8') as f:
        w=csv.writer(f);w.writerow(['node_id','domain','category','x','y'])
        w.writerows((n,g.nodes[n]['domain'],g.nodes[n]['type'],*pos[n]) for n in sorted(pos))
    renderer.render(pos,groups);renderer.render(pos,groups,large=True)
    assert all(sha(p)==value for p,value in protected.items()),'Protected source or analysis artifact changed'
    report={'source_sha256':sha(args.input),'script_sha256':sha(__file__),'nodes':len(g),'edges':len(renderer.edges),'integration_edges':sum(g.nodes[u]['domain']!=g.nodes[v]['domain'] for u,v,k,d in renderer.edges),'protected_files_unchanged':len(protected),'layout_quality':quality,'checks':renderer.tests,'figures':renderer.figures}
    (args.output/'validation.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
    print('PASS: full identities preserved; source and existing analysis unchanged',flush=True)

if __name__=='__main__':main()
