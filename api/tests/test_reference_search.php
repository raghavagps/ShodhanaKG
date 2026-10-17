<?php
require_once __DIR__.'/../graph_service.php';
require_once __DIR__.'/../core/ReferenceSearch.php';
$n=0;
function searchCheck($ok,$message){global $n;$n++;if(!$ok)throw new RuntimeException($message);}
$nodes=array('#999:1'=>array('@rid'=>'#999:1','@type'=>'Disease','name'=>'Test disease'), '#999:2'=>array('@rid'=>'#999:2','@type'=>'Therapy','name'=>'Test therapy'));
$base=array('@type'=>'TREATED_BY','@out'=>'#999:1','@in'=>'#999:2','Dose'=>0,'Procedure'=>'Recorded procedure','@internal'=>'DO_NOT_MATCH_INTERNAL');
$edges=array('#999:3'=>$base+array('@rid'=>'#999:3','pubmed_id'=>'76448'), '#999:4'=>$base+array('@rid'=>'#999:4','ayush_id'=>'76448'), '#999:5'=>$base+array('@rid'=>'#999:5','ref'=>'not-classified'));
$snapshot=array('nodes'=>$nodes,'edges'=>$edges,'fetched_at'=>'test');
$all=ReferenceSearch::run($snapshot,array('identifier'=>'76448'));
searchCheck($all['totalGroups']===2&&$all['totalEdges']===2,'Namespaces must remain distinct');
$pmid=ReferenceSearch::run($snapshot,array('identifier'=>'PMID:76448'));
searchCheck($pmid['totalGroups']===1&&$pmid['groups'][0]['reference']['namespace']==='PMID','Explicit namespace prefix');
searchCheck($pmid['groups'][0]['records'][0]['edge']['id']==='#999:3','Exact supporting edge identity');
searchCheck($pmid['groups'][0]['records'][0]['edge']['source']==='#999:1','Stored edge direction');
searchCheck(ReferenceSearch::run($snapshot,array('q'=>'Recorded procedure'))['totalGroups']===2,'Stored property keyword');
searchCheck(ReferenceSearch::run($snapshot,array('q'=>'DO_NOT_MATCH_INTERNAL'))['totalGroups']===0,'Internal fields excluded');
searchCheck(ReferenceSearch::run($snapshot,array('type'=>'Compound'))['totalGroups']===0,'Entity type filter');
searchCheck(ReferenceSearch::run($snapshot,array('domain'=>'compound'))['totalGroups']===0,'Domain filter');
$first=ReferenceSearch::run($snapshot,array('limit'=>1));$second=ReferenceSearch::run($snapshot,array('limit'=>1,'cursor'=>$first['nextCursor']));
searchCheck($first['groups'][0]['id']!==$second['groups'][0]['id']&&$second['nextCursor']===null,'Stable pagination');
try{ReferenceSearch::run($snapshot,array('namespace'=>'AYUSH','identifier'=>'PMID:76448'));searchCheck(false,'Mismatch accepted');}catch(InvalidArgumentException $e){searchCheck(true,'Mismatch rejected');}
try{ReferenceSearch::run($snapshot,array('namespace'=>'PMID','cursor'=>$first['nextCursor']));searchCheck(false,'Stale cursor accepted');}catch(InvalidArgumentException $e){searchCheck(true,'Stale cursor rejected');}
echo "PASS $n reference-search assertions\n";
