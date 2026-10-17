<?php
require_once __DIR__ . '/../core/ConversationEntities.php';
require_once __DIR__ . '/../core/QueryUnderstanding.php';
$count = 0;
function checkResolution($condition, $message) { global $count; $count++; if (!$condition) throw new RuntimeException($message); }
$nodes = [
    ['@rid'=>'#1:1','@type'=>'Compound','name'=>'Copper'],
    ['@rid'=>'#1:2','@type'=>'Compound','name'=>'Lead'],
    ['@rid'=>'#1:3','@type'=>'Compound','name'=>'Coper'],
    ['@rid'=>'#2:1','@type'=>'Disease','name'=>'Shared Name'],
    ['@rid'=>'#3:1','@type'=>'Indication','name'=>'Shared Name'],
    ['@rid'=>'#4:1','@type'=>'Disease','name'=>'Vipadika'],
    ['@rid'=>'#5:1','@type'=>'Therapy','name'=>'Sarvanga Abhyanga'],
    ['@rid'=>'#6:1','@type'=>'Compound','name'=>'Alpha, Beta'],
];
$index = new EntityResolution(array_filter($nodes, static function($n){return $n['@rid'] !== '#1:3';}), []);
foreach (['coper','coppre','coppper','COPPER',' Copper ','#1:1'] as $text) {
    $r = $index->resolve($text); checkResolution($r['outcome']==='resolved' && $r['candidates'][0]['id']==='#1:1', 'Copper edit: '.$text);
}
foreach (['cop','co','zzzzzz','alpha','beta'] as $text) checkResolution($index->resolve($text)['outcome']==='unmatched', 'Unsafe short/substring: '.$text);
$exact = new EntityResolution($nodes, []);
checkResolution($exact->resolve('Coper')['candidates'][0]['id']==='#1:3', 'Exact beats nearby label');
checkResolution($index->resolve('Shared Name')['outcome']==='ambiguous', 'Cross-type ambiguity');
checkResolution($index->resolve('Shared Name',['Indication'])['candidates'][0]['type']==='Indication', 'Explicit type retained');
checkResolution(!ConversationEntities::select($index->mentions('Shared Name')['groups'][0], 'Compound Shared Name'), 'Explicit incompatible type cannot be overridden');
checkResolution(ConversationEntities::select($index->mentions('Shared Name')['groups'][0], 'Indication Shared Name')['inferred_type']==='Indication', 'Explicit matching type disambiguates');
checkResolution(count($index->search('Shared', ['Disease','Indication'], 10))===2, 'Search keeps both types');
$tied = new EntityResolution([['@rid'=>'#1:1','@type'=>'Compound','name'=>'Copper'],['@rid'=>'#1:2','@type'=>'Compound','name'=>'Copter']],[]);
checkResolution($tied->resolve('Coper')['outcome']==='ambiguous', 'Equal-distance tie must clarify');
$duplicate = new EntityResolution([['@rid'=>'#1:1','@type'=>'Compound','name'=>'Copper'],['@rid'=>'#1:2','@type'=>'Compound','name'=>'Copper']],[]);
checkResolution($duplicate->resolve('Copper')['outcome']==='ambiguous', 'Duplicate labels retain RIDs');
checkResolution(EntityResolution::distance('coppre','copper')===1, 'Transposition');
foreach (get_schema_vertex_types() as $type) checkResolution(EntityResolution::residual(str_replace('_',' ',$type))==='', 'Schema qualifier is not a misspelled entity: '.$type);
checkResolution(EntityResolution::normalize('Śodhana') !== EntityResolution::normalize('Sodhana'), 'Do not erase diacritic semantics');
$multi = $index->mentions('Which formulation is used in Vipdika with Sarvanga Abhynga?');
checkResolution(count($multi['groups'])===2, 'Two independently misspelled mentions');
$cat=['mappings'=>[['rid'=>'#4:1','type'=>'Disease','label'=>'Vipadika','aliases'=>['Reviewed synonym'],'broader_terms'=>['Broad concept'],'source'=>'test source','review_status'=>'source_checked']]];
$mapped = new EntityResolution($nodes,$cat);
checkResolution($mapped->resolve('Reviewed synonym')['candidates'][0]['id']==='#4:1', 'Verified alias');
checkResolution($mapped->resolve('Broad concept')['outcome']==='broader', 'Broad term never auto-resolves');
$cat['mappings'][0]['label']='Stale label';
checkResolution((new EntityResolution($nodes,$cat))->resolve('Reviewed synonym')['outcome']==='unmatched', 'Stale mapping disabled');
foreach (['What are the methods to purify coper?','List purification methods for Copper.','For Copper, which methods are recorded?'] as $q) checkResolution(QueryUnderstanding::target($q)==='Shodhana_Method','Requested methods: '.$q);
checkResolution(QueryUnderstanding::target('Which purified compounds result from Copper?')==='Purified_Compound','Products remain products');
checkResolution(QueryUnderstanding::target('What are the symptoms of cancer?')==='Symptom','Symptom result type');
$valid = static function($prompt,$timeout,$schema) {checkResolution($timeout===10,'Bounded timeout');return ['status'=>'success','text'=>json_encode(['mentions'=>['Copper'],'result_type'=>'Shodhana_Method'])];};
checkResolution(QueryUnderstanding::propose('What methods purify Copper?', $valid)['status']==='proposed','Valid model interpretation');
foreach ([['mentions'=>['Lead'],'result_type'=>'Shodhana_Method'],['mentions'=>['Copper'],'result_type'=>'Compound'],['mentions'=>['Copper'],'result_type'=>'SQL'],['mentions'=>['Copper'],'result_type'=>'Shodhana_Method','sql'=>'SELECT FROM Disease']] as $proposal) {
    $model = static function()use($proposal){return ['status'=>'success','text'=>json_encode($proposal)];};
    checkResolution(QueryUnderstanding::propose('What methods purify Copper?',$model)['status']==='rejected','Reject invented/conflicting model output');
}
checkResolution(QueryUnderstanding::propose('Copper',static function(){return ['status'=>'success','text'=>'not json'];})['status']==='rejected','Invalid JSON');
checkResolution(QueryUnderstanding::propose('Copper',static function(){throw new RuntimeException('timeout');})['status']==='unavailable','Model timeout');
echo "$count resolution assertions passed\n";
