<?php
require_once __DIR__ . '/../core/EntityResolution.php';
require_once __DIR__ . '/../core/QuestionRouter.php';
$count=0;
function termCheck($ok,$why) { global $count; $count++; if (!$ok) throw new RuntimeException($why); }
$nodes=[['@rid'=>'#1:3','@type'=>'Compound','name'=>'Modifier'],['@rid'=>'#1:1','@type'=>'Compound','name'=>'First material','Common_Name'=>'Shared alias'],
 ['@rid'=>'#1:2','@type'=>'Media','name'=>'Shared alias'],
 ['@rid'=>'#2:1','@type'=>'Disease','name'=>'Narrow condition'],
 ['@rid'=>'#2:3','@type'=>'Disease','name'=>'Illness'], ['@rid'=>'#2:2','@type'=>'Disease','name'=>'Broad illnesx']];
$catalog=['mappings'=>[
 ['rid'=>'#1:1','type'=>'Compound','label'=>'First material','source'=>'KG #1:1','review_status'=>'source_checked','property_bindings'=>['Common_Name'=>'Shared alias'],'aliases'=>[['term'=>'Shared alias','policy'=>'confirm'],['term'=>'Listed term','policy'=>'confirm']]],
 ['rid'=>'#2:1','type'=>'Disease','label'=>'Narrow condition','source'=>'test','review_status'=>'source_checked','aliases'=>[['term'=>'Trusted synonym','policy'=>'resolve'],['term'=>'NC','policy'=>'confirm'],['term'=>'Invalid policy alias','policy'=>'typo']],'broader_terms'=>[['term'=>'Broad illness','path'=>['C1','C2']]]]
]];
$i=new EntityResolution($nodes,$catalog);
termCheck(!$i->mentions('What symptoms occur with Modifie Broad illness?')['groups'],'Do not fuzzy-resolve a discarded broader-term qualifier as a separate node');
termCheck(!$i->mentions('What symptoms occur with Zorblax Broad illness?')['suggestions'],'Unknown qualifier is not discarded');
termCheck(!$i->mentions('What symptoms occur with Broad illness?')['groups'] && count($i->mentions('What symptoms occur with Broad illness?')['suggestions'])===1,'Complete broader phrase beats contained canonical name');
termCheck($i->resolve('Shared alias')['candidates'][0]['id']==='#1:2','Canonical label wins');
termCheck($i->mentions('Describe compound Shared alias')['resolutions'][0]['candidates'][0]['id']==='#1:1','Explicit type can choose a source alias over another types canonical label');
termCheck($i->resolve('Shared alias',['Compound'])['outcome']==='ambiguous','Typed KG-listed alias requires confirmation');
termCheck($i->resolve('Listed term')['outcome']==='ambiguous','KG name requires confirmation');
termCheck($i->resolve('Listad term')['outcome']==='ambiguous','Spelling cannot bypass confirmation');
termCheck($i->resolve('Invalid policy alias',[],false)['outcome']==='unmatched','Unknown alias policy fails closed');
termCheck($i->resolve('NC')['outcome']==='ambiguous','Short source abbreviation requires confirmation');
termCheck($i->resolve('Trusted synonym')['outcome']==='resolved','Source-checked synonym resolves');
termCheck($i->resolve('Broad illness')['outcome']==='broader','Known broader term precedes near-spelling match');
termCheck($i->resolve('Broad illness',['Compound'])['suggestions']===[],'Broader suggestions respect actual types');
termCheck($i->resolve('Broad illness')['suggestions'][0]['terminology_path']===['C1','C2'],'Source hierarchy retained');
$nodes[1]['Common_Name']='Changed';$stale=new EntityResolution($nodes,$catalog);
termCheck($stale->resolve('Listed term',[],false)['outcome']==='unmatched','Changed source field disables aliases');
$nodes[3]['@type']='Indication';$stale=new EntityResolution($nodes,$catalog);
termCheck($stale->resolve('Trusted synonym',[],false)['outcome']==='unmatched','Cannot convert node type');

$snapshot=json_decode(file_get_contents(__DIR__.'/fixtures/base_snapshot.json'),true);
$raw=[];foreach($snapshot['tables'] as $table) foreach($table as $n) if(($n['@cat']??'')==='v')$raw[]=$n;
$live=new EntityResolution($raw);
$expected=[];foreach($raw as $n)$expected[EntityResolution::normalize(vertex_label($n))][]=$n['@rid'];
foreach($raw as $n) {
 $r=$live->resolve(vertex_label($n),[],false);$ids=array_column($r['candidates'],'id');$want=$expected[EntityResolution::normalize(vertex_label($n))];sort($ids);sort($want);
 termCheck($ids===$want,'Canonical identities: '.$n['@rid']);
}
foreach(['Lou Gehrig Disease'=>'Amyotrophic Lateral Sclerosis','Chronic Renal Insufficiency'=>'Chronic Kidney Disease','irritable colon'=>'Irritable Bowel Syndrome'] as $term=>$label){
 $r=$live->resolve($term);termCheck($r['outcome']==='resolved' && $r['candidates'][0]['label']===$label,'Clinical synonym '.$term);
}
foreach(['Tamra','Gomutra','Vatsanabha','Abhraka','Ardraka Swarasa'] as $term)termCheck($live->resolve($term)['outcome']==='ambiguous','Confirm KG terminology '.$term);
foreach(['Cardiovascular Disorder','Autoimmune Disorder','Skin Disorder'] as $term)termCheck($live->resolve($term)['outcome']==='broader' && count($live->resolve($term)['suggestions'])>1,'Broad category '.$term);
foreach(['NMR','NDI','IBS'] as $term)termCheck($live->resolve($term)['outcome']==='ambiguous','Explicit abbreviation confirms '.$term);
$catalog=json_decode(file_get_contents(__DIR__.'/../core/terminology_catalog_v2.json'),true);
$exposed=0;$shadowed=0;$paths=0;
foreach($catalog['mappings'] as $mapping) {
 foreach($mapping['aliases'] as $alias) {
  $r=$live->resolve($alias['term'],[],false);$ids=array_column($r['candidates'],'id');
  $canonical=$expected[EntityResolution::normalize($alias['term'])]??[];
  $present=in_array($mapping['rid'],$ids,true);
  termCheck($present || ($canonical && !array_diff($ids,$canonical) && $ids),'Every catalog alias is exposed or deliberately superseded by a canonical label');
  if($present)$exposed++;else $shadowed++;
 }
 foreach($mapping['broader_terms'] as $term) {
  termCheck(in_array($mapping['rid'],array_column($live->broader($term['term']),'id'),true),'Every supported hierarchy target remains available');$paths++;
 }
}
echo "Alias entries exposed: $exposed; canonical precedence: $shadowed; hierarchy targets: $paths\n";
echo "PASS $count terminology and canonical-identity assertions\n";
