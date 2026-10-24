<?php
require_once __DIR__ . '/../core/GraphPage.php';
require_once __DIR__ . '/../core/FAQMatcher.php';
require_once __DIR__ . '/../core/ChatRequest.php';
$checks = 0;
function expectUpdate($condition, $message) { global $checks; $checks++; if (!$condition) throw new RuntimeException($message); }
$nodes = array(array('id'=>'seed','type'=>'Compound','label'=>'Seed')); $edges=array();
for ($i=0;$i<650;$i++) { $nodes[]=array('id'=>'n'.$i,'type'=>$i<640?'Common':'Rare','label'=>'Node '.$i); $edges[]=array('id'=>'e'.$i,'source'=>'seed','target'=>'n'.$i,'type'=>'UNDERGOES','dose'=>0); }
$edges[]=array('id'=>'parallel','source'=>'seed','target'=>'n1','type'=>'PRODUCES');
$edges[]=array('id'=>'loop','source'=>'seed','target'=>'seed','type'=>'FOLLOWED_BY');
$p=GraphPage::render($nodes,$edges,array('seed'),array('budget'=>10));
expectUpdate(count($p['nodes'])===10,'budget enforced');
expectUpdate(count(array_filter($p['nodes'],function($n){return $n['type']==='Rare';}))>=4,'minority category represented fairly');
$allNodes=array();$allEdges=array();$options=array('budget'=>10000);$iterations=0;
do { $p=GraphPage::render($nodes,$edges,array('seed'),$options); foreach($p['nodes'] as $n) {expectUpdate(!isset($allNodes[$n['id']]),'node not repeated');$allNodes[$n['id']]=$n;} foreach($p['edges'] as $e) {expectUpdate(isset($allNodes[$e['source']],$allNodes[$e['target']]),'edge endpoints loaded');expectUpdate(!isset($allEdges[$e['id']]),'edge not repeated');$allEdges[$e['id']]=$e;} $options['cursor']=$p['meta']['cursor']; if(++$iterations>20)throw new RuntimeException('No progress'); } while($p['meta']['hasMore']);
expectUpdate(count($allNodes)===count($nodes)&&count($allEdges)===count($edges),'complete RID union');
expectUpdate(isset($allEdges['loop'],$allEdges['parallel']),'parallel and self-loop preserved');
expectUpdate(count(GraphPage::render($nodes,$edges,array('seed','seed'),array('budget'=>1))['nodes'])===1,'duplicate seed does not duplicate node');
try{GraphPage::render($nodes,$edges,array('seed'),array('budget'=>'invalid'));expectUpdate(false,'invalid budget accepted');}catch(InvalidArgumentException $e){expectUpdate(true,'invalid budget rejected');}
$p=GraphPage::render($nodes,$edges,array('seed'),array('budget'=>1));
try { GraphPage::render($nodes,$edges,array('seed'),array('cursor'=>$p['meta']['cursor'],'queryKey'=>'changed'));expectUpdate(false,'stale cursor accepted'); } catch(InvalidArgumentException $e){expectUpdate(true,'stale rejected');}
$catalog=json_decode(file_get_contents(__DIR__.'/../core/faq_catalog.json'),true);
foreach($catalog['entries'] as $faq) {expectUpdate(FAQMatcher::find($faq['question'])['id']===$faq['id'],'exact FAQ');expectUpdate(FAQMatcher::find('', $faq['id'])['id']===$faq['id'],'FAQ ID');}
foreach(array('What is Copper?','What Shodhana method purifies Copper?','What is Panchakarma for Psoriasis?','What is Shodhana? Give me a dose.') as $question) expectUpdate(FAQMatcher::find($question)===null,'specific requests bypass FAQ');
try{ChatRequest::decode('{"question":"hello","faq_id":"invented"}');expectUpdate(false,'unknown FAQ accepted');}catch(InvalidArgumentException $e){expectUpdate(true,'unknown FAQ rejected');}
echo "PASS $checks planned-update assertions\n";
