<?php
require_once __DIR__.'/../core/QuestionRouter.php';
require_once __DIR__.'/../core/ReferenceSearch.php';
require_once __DIR__.'/../core/GraphPage.php';
$n=0;
function verifyRefinement($ok,$message){global $n;$n++;if(!$ok)throw new RuntimeException($message);}
foreach(get_schema_vertex_types() as $type){
 $raw=array('@rid'=>'#1:1','@type'=>$type,'name'=>'Example','Description'=>'Stored text','Dose'=>0,'Flag'=>false,'Empty'=>'','out_EDGE'=>'#9:9');
 $node=$raw+array('inferred_type'=>$type);
 foreach(array('Describe Example','Explain Example','What is Example?','Tell me about Example') as $q){
  verifyRefinement(NodePropertyAnswer::requested($q,$node)==='*','Broad properties '.$type);
  $a=NodePropertyAnswer::build($raw,'*');
  verifyRefinement($a['node_evidence']['properties']['Description']==='Stored text' && $a['node_evidence']['properties']['Dose']===0 && $a['node_evidence']['properties']['Flag']===false,'Stored values');
  verifyRefinement(!isset($a['node_evidence']['properties']['out_EDGE'])&&!isset($a['node_evidence']['properties']['Empty']),'Internal/empty exclusion');
 }
 verifyRefinement(NodePropertyAnswer::requested('What is the description of Example?',$node)==='description','Named field');
 unset($raw['Description']);verifyRefinement(NodePropertyAnswer::build($raw,'description')['status']==='no_data','Missing description never synthesized');
}
$nodes=array('#1:1'=>array('@rid'=>'#1:1','@type'=>'Disease','name'=>'D'),'#1:2'=>array('@rid'=>'#1:2','@type'=>'Therapy','name'=>'T'),'#2:1'=>array('@rid'=>'#2:1','@type'=>'Compound','name'=>'C'),'#2:2'=>array('@rid'=>'#2:2','@type'=>'Shodhana_Method','name'=>'M'));
$edges=array();for($i=0;$i<110;$i++)$edges['#3:'.$i]=array('@rid'=>'#3:'.$i,'@type'=>'TREATED_BY','@out'=>'#1:1','@in'=>'#1:2','pubmed_id'=>(string)(90000000+$i),'Dose'=>$i);
$edges['#4:1']=array('@rid'=>'#4:1','@type'=>'UNDERGOES','@out'=>'#2:1','@in'=>'#2:2','ayush_id'=>'2201');
$snapshot=array('nodes'=>$nodes,'edges'=>$edges,'fetched_at'=>'test');
foreach(array('disease','compound','integrated') as $domain){
 $facets=ReferenceSearch::run($snapshot,array('domain'=>$domain,'facets'=>'1'));
 verifyRefinement(!array_diff($facets['types'],vertex_types_for_domain($domain)),'Domain entity facets');
 verifyRefinement(!array_diff($facets['relationships'],edge_types_for_domain($domain)),'Domain relation facets');
}
foreach(array(25,50,100) as $limit){
 $cursor='';$ids=array();do{$page=ReferenceSearch::run($snapshot,array('domain'=>'disease','limit'=>$limit,'cursor'=>$cursor));foreach($page['groups'] as $group)$ids[]=$group['id'];$cursor=$page['nextCursor'];}while($cursor);
 verifyRefinement(count($ids)===110 && count(array_unique($ids))===110,'Complete pagination '.$limit);
}
foreach(array(array('domain'=>'disease','type'=>'Compound'),array('domain'=>'disease','relationship'=>'UNDERGOES'),array('limit'=>'bad'),array('limit'=>101)) as $bad){try{ReferenceSearch::run($snapshot,$bad);verifyRefinement(false,'Invalid filter accepted');}catch(InvalidArgumentException $e){verifyRefinement(true,'Invalid filter rejected');}}
$nodes=array(array('id'=>'seed','type'=>'Disease','label'=>'seed'));$edges=array();
for($i=1;$i<=420;$i++){$nodes[]=array('id'=>'n'.$i,'type'=>$i%2?'Therapy':'Symptom','label'=>'node');$edges[]=array('id'=>'e'.$i,'source'=>'seed','target'=>'n'.$i,'type'=>'EDGE','dose'=>$i);}
$edges[]=array('id'=>'loop','source'=>'seed','target'=>'seed','type'=>'LOOP');$edges[]=array('id'=>'parallel','source'=>'seed','target'=>'n1','type'=>'OTHER');
foreach(array(100,150,200,300,100000) as $limit){$cursor='';$seen=array();$links=array();do{$page=GraphPage::render($nodes,$edges,array('seed'),array('budget'=>$limit,'cursor'=>$cursor));foreach($page['nodes'] as $node)$seen[$node['id']]=$node;foreach($page['edges'] as $edge)$links[$edge['id']]=$edge;$cursor=$page['meta']['cursor'];}while($page['meta']['withinBudget']);
 verifyRefinement(count($seen)===min($limit,421),'Selected budget');verifyRefinement(isset($links['loop'],$links['parallel']),'Loops and parallel edges');
 foreach($edges as $edge)if(isset($seen[$edge['source']],$seen[$edge['target']]))verifyRefinement(isset($links[$edge['id']])&&$links[$edge['id']]===$edge,'Complete exact induced edges');
}
echo "PASS $n UI refinement assertions\n";
