"""Draw coordinated, native-vector architecture and chatbot workflow figures."""
from pathlib import Path
import os, io, json, hashlib, zipfile
import xml.etree.ElementTree as ET

ROOT=Path(__file__).resolve().parent
for key in ('TEMP','TMP'):os.environ[key]=str(ROOT/'.audit-temp')
os.environ['MPLCONFIGDIR']=str(ROOT/'.audit-temp/figure-mpl')
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch, Rectangle, Circle, Ellipse, Arc
from matplotlib.path import Path as MP

NAVY='#18344F'; TEAL='#176E78'; AMBER='#AE7412'; GRAY='#556777'
plt.rcParams.update({'font.family':'Arial','font.size':10,'svg.fonttype':'none','pdf.fonttype':42})
OUT=ROOT/'figures/core'; PREVIEW=ROOT/'figures/previews'

def canvas(title, height=11):
    fig,ax=plt.subplots(figsize=(14,height*.85))
    fig.subplots_adjust(left=.015,right=.985,bottom=.02,top=.98)
    ax.set(xlim=(0,17),ylim=(0,height));ax.axis('off')
    ax.text(.55,height-.35,title,ha='left',va='center',fontsize=17,fontweight='bold',color=NAVY)
    return fig,ax

def arrow(ax, pts, color=NAVY, dashed=False):
    ax.add_patch(FancyArrowPatch(path=MP(pts,[MP.MOVETO]+[MP.LINETO]*(len(pts)-1)),arrowstyle='-|>',mutation_scale=12,lw=1.1,color=color,linestyle='--' if dashed else '-',zorder=1))

def text(ax,x,y,s,size=9.5,color=GRAY,ha='center',**kw):
    s=s.translate(str.maketrans({'①':'[1]','②':'[2]','③':'[3]'}))
    return ax.text(x,y,s,ha=ha,va='center',fontsize=size,color=color,linespacing=1.45,zorder=5,**kw)

def box(ax,x,y,w,h,title,body='',color=NAVY,icon=None):
    ax.add_patch(FancyBboxPatch((x-w/2,y-h/2),w,h,boxstyle='round,pad=.03,rounding_size=.1',facecolor='white',edgecolor=color,lw=1,zorder=2))
    text(ax,x,y+(.16 if body else 0),title,10.5,color,fontweight='bold')
    if body:text(ax,x,y-.3,body,9.5)
    if icon: symbol(ax,icon,x-w/2+.32,y+h/2-.2,color)

def symbol(ax,kind,x,y,color=NAVY):
    # All symbols are simple local vector primitives, not fonts or external images.
    if kind=='browser':
        ax.add_patch(Rectangle((x-.22,y-.15),.44,.3,fill=False,ec=color,lw=.8,zorder=4));ax.plot([x-.22,x+.22],[y+.06,y+.06],color=color,lw=.7,zorder=4)
    elif kind=='server':
        for dy in [-.1,.1]:
            ax.add_patch(Rectangle((x-.2,y+dy-.065),.4,.13,fill=False,ec=color,lw=.8,zorder=4));ax.add_patch(Circle((x-.13,y+dy),.015,fc=color,zorder=4))
    elif kind=='file':
        ax.add_patch(Rectangle((x-.14,y-.2),.28,.4,fill=False,ec=color,lw=.8,zorder=4))
        for dy in [-.1,0,.1]:ax.plot([x-.08,x+.08],[y+dy,y+dy],color=color,lw=.6,zorder=4)
    elif kind=='chat':
        ax.add_patch(FancyBboxPatch((x-.21,y-.13),.42,.26,boxstyle='round,pad=.01',fill=False,ec=color,lw=.8,zorder=4));ax.plot([x-.1,x-.16,x+.01],[y-.13,y-.23,y-.13],color=color,lw=.8,zorder=4)
    elif kind=='graph':
        pts=[(x-.19,y-.1),(x,y+.15),(x+.2,y-.1)]
        for u,v in [(0,1),(1,2),(0,2)]:ax.plot([pts[u][0],pts[v][0]],[pts[u][1],pts[v][1]],color=color,lw=.7,zorder=4)
        for p in pts:ax.add_patch(Circle(p,.045,fc='white',ec=color,lw=.8,zorder=4))
    elif kind=='user':
        ax.add_patch(Circle((x,y+.08),.065,fill=False,ec=color,lw=.8,zorder=4));ax.add_patch(Arc((x,y-.1),.3,.25,theta1=0,theta2=180,ec=color,lw=.8,zorder=4))

def band(ax,y,h,num,title,subtitle,color):
    ax.add_patch(FancyBboxPatch((.55,y-h/2),12.1,h,boxstyle='round,pad=.03,rounding_size=.1',facecolor='white',edgecolor=color,lw=.9,zorder=0))
    ax.add_patch(Circle((.97,y+h/2-.35),.19,fc=color,ec=color,zorder=3));text(ax,.97,y+h/2-.35,str(num),10,'white',fontweight='bold')
    text(ax,1.35,y+h/2-.35,title,12,color,ha='left',fontweight='bold');text(ax,12.3,y+h/2-.35,subtitle,9,GRAY,ha='right')

def export(fig,name,caption):
    OUT.mkdir(parents=True,exist_ok=True);PREVIEW.mkdir(parents=True,exist_ok=True)
    path=OUT/name
    fig.savefig(path.with_suffix('.svg'),facecolor='white');fig.savefig(path.with_suffix('.pdf'),facecolor='white')
    buffer=io.BytesIO();fig.savefig(buffer,format='png',dpi=160,facecolor='white')
    staging=PREVIEW/(name+'.png.new');staging.write_bytes(buffer.getvalue());staging.replace(PREVIEW/(name+'.png'));plt.close(fig)
    ns='{http://www.w3.org/2000/svg}';ET.register_namespace('',ns[1:-1]);tree=ET.parse(path.with_suffix('.svg'));r=tree.getroot()
    ET.SubElement(r,ns+'title').text=name.replace('_',' ');ET.SubElement(r,ns+'desc').text=caption
    assert r.get('viewBox') and list(r.iter(ns+'text')) and list(r.iter(ns+'path'))
    assert not list(r.iter(ns+'image'))
    for e in r.iter():
        for k,v in e.attrib.items():
            if k.endswith('href'):assert v.startswith('#')
    tree.write(path.with_suffix('.svg'),encoding='utf-8',xml_declaration=True)
    pdf=path.with_suffix('.pdf').read_bytes();assert pdf.startswith(b'%PDF') and b'/Subtype /Image' not in pdf
    report={'caption':caption,'editable_text_elements':len(list(r.iter(ns+'text'))),'embedded_images':0,'external_assets':0,'svg_xml_valid':True,'pdf_vector_check':True}
    path.with_suffix('.json').write_text(json.dumps(report,indent=2),encoding='utf-8');print(name,report['editable_text_elements'],'editable text elements',flush=True)
    return report

def architecture():
    fig,ax=canvas('Three-tier ShodhanaKG system architecture',12)
    band(ax,9.55,2.75,1,'Presentation · web browser','React 18 · TypeScript · Vite · Cytoscape.js',NAVY)
    box(ax,2.75,9.2,3.45,1.5,'Search and exploration','Global entity search\nDisease · Compound · Integrated',icon='browser')
    box(ax,6.6,9.2,3.45,1.5,'Graph and evidence views','Graph / table · filters · properties\nReferences and provenance',icon='graph')
    box(ax,10.45,9.2,3.45,1.5,'Chatbot interface','Questions · follow-ups\nResults and citation cards',color=AMBER,icon='chat')
    box(ax,14.85,9.55,3.25,1.9,'Vite build artifacts','HTML · CSS · JavaScript\nBundled fonts and assets',icon='file')
    arrow(ax,[(13.18,10.1),(12.7,10.1)]);text(ax,14.85,8.24,'Served by Apache over HTTP(S)\nStatic file delivery',9)

    band(ax,5.95,2.75,2,'Application · PHP API on Apache','HTTP endpoints and shared services',NAVY)
    box(ax,2.75,5.6,3.45,1.5,'Graph retrieval API','Directed traversal · schema mapping\nNode and edge properties',icon='server')
    box(ax,6.6,5.6,3.45,1.5,'Evidence services','Reference classification\nExact edge-to-citation mapping',icon='file')
    box(ax,10.45,5.6,3.45,1.5,'Chatbot services','Entity resolution · query planning\nContext · validation · answers',color=AMBER,icon='chat')
    arrow(ax,[(3.1,8.14),(3.1,7.37)]);text(ax,3.35,7.76,'HTTP requests: search, filters, questions',9,ha='left')
    arrow(ax,[(10.65,7.37),(10.65,8.14)],AMBER);text(ax,10.42,7.76,'JSON / chatbot SSE',9,AMBER,ha='right')
    box(ax,14.85,5.95,3.25,1.9,'Ollama · optional','Language assistance\nNo factual result enumeration',color=AMBER,icon='chat')
    arrow(ax,[(12.2,5.75),(13.18,5.75)],AMBER,True);arrow(ax,[(13.18,6.15),(12.2,6.15)],AMBER,True)
    text(ax,14.85,4.63,'HTTP assistance request / response',9,AMBER)

    band(ax,2.3,2.75,3,'Data · ArcadeDB knowledge graph','Stable IDs · directed edges · stored properties',TEAL)
    # Database symbol and semantically distinct domain representations.
    ax.add_patch(Rectangle((1,1.6),2.1,1.2,fc='white',ec=TEAL,lw=1,zorder=2))
    for yy in [1.6,2.8]:ax.add_patch(Ellipse((2.05,yy),2.1,.32,fc='white',ec=TEAL,lw=1,zorder=3))
    text(ax,2.05,2.2,'ArcadeDB',11,TEAL,fontweight='bold')
    box(ax,5.5,2.15,3.2,1.35,'Compound Shodhana','Purified Compound',TEAL,icon='graph')
    box(ax,10.4,2.15,3.2,1.35,'Disease Shodhana','Disease',TEAL,icon='graph')
    arrow(ax,[(7.15,2.1),(8.74,2.1)],AMBER);text(ax,7.96,1.77,'USED_FOR_\nTREATMENT',8.5,AMBER)
    arrow(ax,[(3.1,4.53),(3.1,3.72)]);text(ax,3.36,4.13,'REST/SQL commands',9,ha='left')
    arrow(ax,[(10.65,3.72),(10.65,4.53)],TEAL);text(ax,10.4,4.13,'JSON graph records',9,TEAL,ha='right')
    text(ax,14.85,2.35,'① Browser interface\n② PHP services\n③ KG retrieval',11,NAVY)
    text(ax,14.85,1.25,'Matching tiers in the\nchatbot workflow figure',9)
    text(ax,8.5,.44,'Solid arrows: file or data transfer    Dashed arrows: optional assistance    Amber: chatbot / integration emphasis',9)
    return export(fig,'09_three_tier_architecture','Conceptual production architecture. Apache serves the Vite static build and PHP API. JSON/SSE application exchange and ArcadeDB REST/SQL retrieval are distinct from static asset delivery. Ollama is optional; it does not enumerate factual results. Domain boxes summarize the schema connection, not a complete instance graph. Numbered tiers match Figure 10. No benchmark or clinical-validity claim.')

def pipeline():
    fig,ax=canvas('ShodhanaKG chatbot: from question to evidence-linked answer',10)
    # Two left-to-right rows preserve a readable reading order without eight narrow boxes.
    stages=[(2.25,7.55,'1  User question','Question or follow-up\n① Browser',NAVY,'user'),(6.3,7.55,'2  Validate request / context','Validate input and resolve references\n② PHP services',NAVY,'server'),(10.35,7.55,'3  Resolve entity and intent','KG types · aliases · ambiguity\n② PHP services',NAVY,'graph'),(14.4,7.55,'4  Plan supported traversal','Result type · constraints · direction\n② PHP services',NAVY,'server'),(2.25,3.9,'5  Fresh KG retrieval','Read nodes, edges and properties\n③ ArcadeDB via PHP',TEAL,'graph'),(6.3,3.9,'6  Validate facts / provenance','Preserve IDs and edge-bound evidence\n② PHP services',NAVY,'file'),(10.35,3.9,'7  Construct answer','Deterministic results and citations\n② PHP services',AMBER,'chat'),(14.4,3.9,'8  Results and citations','JSON / SSE · complete-result access\n① Browser',NAVY,'browser')]
    for x,y,t,b,c,ic in stages:box(ax,x,y,3.45,1.45,t,b,c,ic)
    for y in [7.55,3.9]:
        for x in [2.25,6.3,10.35]:arrow(ax,[(x+1.78,y),(x+2.26,y)],AMBER)
    arrow(ax,[(14.4,6.78),(14.4,5.75),(2.25,5.75),(2.25,4.68)],AMBER)
    text(ax,8.3,5.96,'Execute the validated retrieval plan',9,AMBER)

    # Clarification returns to input, not to factual answer construction.
    arrow(ax,[(10.35,8.32),(10.35,8.92),(2.25,8.92),(2.25,8.32)])
    text(ax,6.3,9.13,'Ambiguous entity or intent → clarification → revised input',9.5)
    box(ax,14.4,1.7,3.45,1.25,'Unsupported / no result','Explicit response; no invented facts')
    arrow(ax,[(16.17,7.2),(16.55,7.2),(16.55,1.7),(16.17,1.7)])
    text(ax,16.35,5.45,'Unsupported\npath',8,ha='right')
    arrow(ax,[(2.25,3.14),(2.25,2.64),(14.4,2.64),(14.4,2.36)])
    text(ax,4.25,2.83,'No matching records',9)
    arrow(ax,[(15.88,2.36),(15.88,3.14)])

    # The optional intro is grounded in the validated packet; it cannot enumerate results.
    box(ax,8.4,1.62,4.5,1.2,'Optional LLM introduction','Validated fact packet only; no result enumeration',AMBER)
    arrow(ax,[(6.3,3.14),(6.3,2.4),(7.2,2.4),(7.2,2.26)],AMBER,True)
    arrow(ax,[(9.6,2.26),(9.6,2.4),(10.35,2.4),(10.35,3.14)],AMBER,True)

    # Separate context track: interpret next turn, never use old answer text as evidence.
    ax.add_patch(FancyBboxPatch((.55,.22),15.85,.62,boxstyle='round,pad=.02',fc='white',ec=NAVY,lw=.8,zorder=2))
    text(ax,8.5,.53,'Structured context: active entity/type · resolved entities · constraints · prior intent/result type · pending clarification',9)
    arrow(ax,[(.55,.53),(.25,.53),(.25,6.35),(6.3,6.35),(6.3,6.78)],GRAY,True)
    text(ax,4.1,6.52,'Next turn: revalidate context and retrieve again',8.5)
    arrow(ax,[(14.4,3.14),(16.35,3.14),(16.35,.87)],GRAY,True)
    text(ax,2.45,1.64,'Prior answer text is not\ntrusted scientific evidence',9,NAVY)
    return export(fig,'10_chatbot_architecture_pipeline','Conceptual chatbot pipeline linked to architecture tiers 1 (browser), 2 (PHP) and 3 (ArcadeDB). Two left-to-right rows show the main path. Clarification revises input; unsupported paths/no records yield explicit responses. Structured context is revalidated for fresh KG retrieval. Optional model assistance uses validated facts for language only; results/citations are constructed deterministically. Failure and optional branches are schematic, not exhaustive control flow or timing measurements.')

if __name__=='__main__':
    reports={'architecture':architecture(),'chatbot_pipeline':pipeline()}
    sources=['api/core/ChatbotOrchestrator.php','api/core/ConversationContext.php','api/core/QueryUnderstanding.php','api/core/ResultsPresentation.php','src/lib/chatApi.ts','api/graph_service.php']
    reports['sources']={p:hashlib.sha256((ROOT/p).read_bytes()).hexdigest() if (ROOT/p).is_file() else 'Source unavailable in figure-only package' for p in sources}
    (ROOT/'figures/architecture_validation.json').write_text(json.dumps(reports,indent=2),encoding='utf-8')
    with zipfile.ZipFile(ROOT/'architecture_figures.zip','w',zipfile.ZIP_DEFLATED) as z:
        for name in ['09_three_tier_architecture','10_chatbot_architecture_pipeline']:
            for ext in ['.svg','.pdf','.json']:z.write(OUT/(name+ext),'figures/core/'+name+ext)
            z.write(PREVIEW/(name+'.png'),'figures/previews/'+name+'.png')
        z.write(Path(__file__).resolve(),'plot_architecture_figures.py')
        z.write(ROOT/'requirements-figures.txt','requirements-figures.txt')
        z.write(ROOT/'figures/architecture_validation.json','figures/architecture_validation.json')
        if (ROOT/'figures/ARCHITECTURE_FIGURES.md').is_file():z.write(ROOT/'figures/ARCHITECTURE_FIGURES.md','figures/ARCHITECTURE_FIGURES.md')
