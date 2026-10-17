<?php
require_once __DIR__ . '/../core/QuestionRouter.php';
require_once __DIR__ . '/../core/ResponseGenerator.php';
require_once __DIR__ . '/../core/FAQMatcher.php';
$n=0;
function checkNode($ok,$message) { global $n; $n++; if (!$ok) throw new RuntimeException($message); }
foreach (get_schema_vertex_types() as $type) {
    $raw=['@rid'=>'#1:1','@type'=>$type,'name'=>'Example Entity','Description.4'=>'Stored description.','Dose'=>0,'Available'=>false,'Empty'=>'  ','Null'=>null,'out_HAS_SYMPTOM'=>'#9:9'];
    $node=$raw+['inferred_type'=>$type];
    foreach (['What is Example Entity?','Describe Example Entity','Tell me about Example Entity','What is the description of Example Entity?'] as $q) {
        checkNode(NodePropertyAnswer::requested($q,$node)===(in_array($q,['What is Example Entity?','Tell me about Example Entity','Describe Example Entity'],true)?'*':'description'),$type . ': '.$q);
    }
    $a=NodePropertyAnswer::build($raw,'description');
    checkNode($a['node_evidence']['properties']===['Description.4'=>'Stored description.'],$type.' exact stored description');
    foreach (['Empty','Null','missing'] as $key) checkNode(NodePropertyAnswer::build($raw,$key)['status']==='no_data',$type.' missing '.$key);
    checkNode(NodePropertyAnswer::build($raw,'dose')['node_evidence']['properties']===['Dose'=>0],'Zero retained');
    checkNode(NodePropertyAnswer::build($raw,'available')['node_evidence']['properties']===['Available'=>false],'False retained');
    $all=NodePropertyAnswer::build($raw,'*');
    checkNode(!isset($all['node_evidence']['properties']['out_HAS_SYMPTOM']),'No adjacency');
    $response=['routing'=>['endpoint'=>'node_property'],'node_answer'=>$a,'status'=>'success'];
    $tokens=''; $s=ResponseGenerator::generateStream('Describe Example Entity',$response,[],function($t)use(&$tokens){$tokens.=$t;});
    checkNode($s===ResponseGenerator::generate('Describe Example Entity',$response) && $tokens===$a['answer'],'Stream parity');
}
$node=['name'=>'Copper','inferred_type'=>'Compound'];
foreach (['What is the complete shodhana process for Copper?','What are the therapeutic uses of Copper?','What methods purify Copper?','What are the symptoms of Copper?','Which diseases are associated with Copper?'] as $q) checkNode(NodePropertyAnswer::requested($q,$node)===null,'Relationship request: '.$q);
checkNode(NodePropertyAnswer::requested('What is the molecular weight of Copper?',$node)==='molecular weight','Generic property');
checkNode(NodePropertyAnswer::requested('What is the property symptoms of Copper?',$node)==='symptoms','Explicit property separate from relationship');
checkNode(FAQMatcher::find('What is Shodhana?')['category']==='background','Curated background separate from node evidence');
$matcher=new ConversationEntities([['@rid'=>'#1:1','@type'=>'Compound','name'=>'Copper']]);
foreach (['Describe Copper','What is the molecular weight of Copper?','What is the description of coper?'] as $q) checkNode((QuestionRouter::route($q,[],$matcher)['routing']['endpoint']??'')==='node_property','Route '.$q);
checkNode(NodePropertyAnswer::requested('What is the compound type of Copper?',$node+['Compound_Type'=>'Metal'])==='compound type','Stored schema field beats relationship keyword');
checkNode(NodePropertyAnswer::subjectQuestion('What is its molecular weight?')==='it','Property does not replace pronoun subject');
checkNode(NodePropertyAnswer::requested('What is the property definition of Copper?',$node)==='definition','Explicit field is not a description alias');
echo "PASS $n node-property assertions\n";
