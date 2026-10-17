"""Export the conceptual ShodhanaKG evaluation loop as editable vector art."""
from pathlib import Path
import os
import io
import json
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parent
os.environ['MPLCONFIGDIR'] = str(ROOT / '.audit-temp/figure-mpl')
os.environ['TEMP'] = str(ROOT / '.audit-temp')
os.environ['TMP'] = str(ROOT / '.audit-temp')
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch, Circle, Ellipse, Arc, PathPatch
from matplotlib.path import Path as MPath

OUT = ROOT / 'figures/core/08_evaluation_loop'
NAVY, AMBER, GRAY = '#18344F', '#B57915', '#526575'
plt.rcParams.update({'font.family':'Arial', 'font.size':10, 'svg.fonttype':'none', 'pdf.fonttype':42})
fig, ax = plt.subplots(figsize=(14,9))
fig.subplots_adjust(left=.02,right=.98,bottom=.02,top=.98)
ax.set(xlim=(0,16),ylim=(0,10)); ax.axis('off')

def arrow(points, color=NAVY, dashed=False, width=1.1):
    """Explicit polylines keep paths clear of text; arrowheads follow direction."""
    path=MPath(points,[MPath.MOVETO]+[MPath.LINETO]*(len(points)-1))
    ax.add_patch(FancyArrowPatch(path=path,arrowstyle='-|>',color=color,lw=width,linestyle='--' if dashed else '-',mutation_scale=12,zorder=1))

def box(x,y,title,body,width=2.85,height=1.55,accent=False):
    patch=FancyBboxPatch((x-width/2,y-height/2),width,height,boxstyle='round,pad=.04,rounding_size=.12',facecolor='white',edgecolor=AMBER if accent else NAVY,lw=1.2,zorder=3)
    ax.add_patch(patch)
    ax.text(x,y+.43,title,ha='center',va='center',color=NAVY,fontweight='bold',fontsize=11,zorder=4)
    ax.text(x,y-.15,body,ha='center',va='center',color=GRAY,fontsize=9.5,linespacing=1.5,zorder=4)

def cylinder(x,y,title,body,width=2.6,height=1.45):
    rx=width/2; top=y+height/2; bottom=y-height/2
    ax.add_patch(PathPatch(MPath([(x-rx,top),(x-rx,bottom),(x+rx,bottom),(x+rx,top)], [MPath.MOVETO,MPath.LINETO,MPath.LINETO,MPath.LINETO]),facecolor='white',edgecolor=NAVY,lw=1.1,zorder=3))
    ax.add_patch(Ellipse((x,bottom),width,.34,facecolor='white',edgecolor=NAVY,lw=1.1,zorder=3))
    ax.add_patch(Ellipse((x,top),width,.34,facecolor='white',edgecolor=NAVY,lw=1.1,zorder=4))
    ax.text(x,y+.2,title,ha='center',va='center',fontweight='bold',color=NAVY,fontsize=10,zorder=5)
    ax.text(x,y-.3,body,ha='center',va='center',color=GRAY,fontsize=9,zorder=5)

# Lifecycle: clockwise, with a separate repeat-case route above the first phase.
box(8,8,'1  Configure evaluation','Endpoint and JSON/SSE transport\nFixtures and run configuration')
box(11,5,'2  Load test case','Question, expected contract\nIndependent reference facts\nRelevant conversation context')
box(8,2,'3  Execute query / conversation','Submit requests; capture responses\nResults, citations and latency')
box(5,5,'4  Validate & record','Routing, retrieval, completeness\nAttribution, presentation\nConversation behaviour',accent=True)

ax.add_patch(Circle((8,5),1.06,facecolor=NAVY,edgecolor=NAVY,lw=1.2,zorder=3))
ax.text(8,5.17,'Evaluation\nRunner',ha='center',va='center',color='white',fontsize=14,fontweight='bold',zorder=4)
ax.text(8,4.62,'Experiment lifecycle',ha='center',va='center',color='white',fontsize=8.5,zorder=4)

arrow([(9.46,8),(11,8),(11,5.84)],AMBER)
arrow([(11,4.16),(11,2),(9.48,2)],AMBER)
arrow([(6.52,2),(5,2),(5,4.16)],AMBER)
arrow([(5,5.84),(5,9.2),(11.65,9.2),(11.65,5.84)],AMBER)
ax.text(8.25,9.35,'Next test case',ha='center',color=AMBER,fontsize=10)
for a,b in [((8,7.18),(8,6.1)),((9.1,5),(9.51,5)),((8,3.9),(8,2.84)),((6.9,5),(6.49,5))]:
    arrow([a,b],GRAY,dashed=True,width=.8)

# Benchmark inputs and separately persisted outputs.
cylinder(1.7,5,'Frozen benchmark fixtures','Independent reference data')
arrow([(1.7,4.08),(1.7,3.25),(6.65,3.25),(7.3,4.12)])
ax.text(3.35,3.4,'Test inputs',color=GRAY,fontsize=9)
cylinder(1.7,1.65,'Raw results & metrics','Responses, checks and timing')
arrow([(3.52,5),(3.2,5),(3.2,1.65),(3.03,1.65)])
ax.text(3.05,2.65,'Save',color=GRAY,fontsize=9,rotation=90,ha='right')

# System under test is distinct from the evaluator and its reference data.
box(14.25,5,'ShodhanaKG chatbot','System under test\nDeterministic KG-grounded results',width=2.85)
cylinder(14.25,8,'Knowledge graph','Nodes, directed edges, evidence')
box(14.25,2,'Optional LLM assistance','Language assistance only\nDoes not enumerate factual results',width=2.85)
arrow([(9.49,1.65),(12.62,1.65),(12.62,4.65),(12.77,4.65)])
ax.text(11.6,1.42,'Requests',ha='center',color=GRAY,fontsize=9)
arrow([(12.77,5.45),(12.72,5.45),(12.72,3.1),(9.48,3.1),(9.48,2.45)])
ax.text(10.15,3.26,'Responses + evidence',ha='center',color=GRAY,fontsize=9)
arrow([(14,5.84),(14,7.08)])
arrow([(14.5,7.08),(14.5,5.84)])
ax.text(15.05,6.48,'Fresh KG\nretrieval',ha='center',color=GRAY,fontsize=9)
arrow([(14.25,4.16),(14.25,2.84)],GRAY,dashed=True)

ax.text(8,9.85,'Conceptual evaluation workflow',ha='center',va='center',fontsize=16,fontweight='bold',color=NAVY)
ax.text(8,.43,'Amber: evaluation sequence     Navy: data flow     Dashed: coordination or optional assistance',ha='center',color=GRAY,fontsize=9)
ax.text(8,.12,'The loop repeats test cases; it does not train the model or automatically modify the knowledge graph',ha='center',color=NAVY,fontsize=9)

OUT.parent.mkdir(parents=True,exist_ok=True)
fig.savefig(OUT.with_suffix('.svg'),facecolor='white')
fig.savefig(OUT.with_suffix('.pdf'),facecolor='white')
preview=io.BytesIO();fig.savefig(preview,format='png',dpi=160,facecolor='white')
(ROOT/'figures/previews/08_evaluation_loop.png').write_bytes(preview.getvalue())
plt.close(fig)
ns='{http://www.w3.org/2000/svg}'
ET.register_namespace('',ns[1:-1])
tree=ET.parse(OUT.with_suffix('.svg'));root=tree.getroot()
ET.SubElement(root,ns+'title').text='Conceptual ShodhanaKG evaluation workflow'
ET.SubElement(root,ns+'desc').text='Evaluation runner with four phases, independent fixtures, KG-grounded chatbot and metrics store. Iteration is test execution, not training or automatic KG modification.'
assert root.get('viewBox') and list(root.iter(ns+'text')) and list(root.iter(ns+'path'))
assert not list(root.iter(ns+'image'))
tree.write(OUT.with_suffix('.svg'),encoding='utf-8',xml_declaration=True)
OUT.with_suffix('.json').write_text(json.dumps({'kind':'conceptual architecture; not quantitative results','editable_text':True,'embedded_rasters':0,'svg_xml_valid':True,'reproduce':'.audit-temp/figure-env/Scripts/python.exe -B plot_evaluation_workflow.py'},indent=2),encoding='utf-8')
print('Exported and validated:',OUT.with_suffix('.svg'))
