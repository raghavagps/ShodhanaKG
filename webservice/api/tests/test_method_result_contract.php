<?php
require_once __DIR__ . '/../core/QuestionRouter.php';
require_once __DIR__ . '/../core/ResultsPresentation.php';
$matcher = new ConversationEntities([
 ['@rid'=>'#1:0','@type'=>'Compound','name'=>'Copper'],
 ['@rid'=>'#1:1','@type'=>'Compound','name'=>'Lead'],
]);
$checked=0;
foreach (['Copper','Lead'] as $entity) foreach ([
 'What Shodhana method is used to purify %s?',
 'Which purification technique is used for %s?',
 'How can %s be purified using a Shodhana method?',
 'Name the methods used to purify %s.',
 'What methods purify %s?',
 'List purification techniques for %s.',
 'Shodhana method to purify %s.',
 'Purification methods for %s.',
 'Shodhana techniques used for %s.',
 'Method to purify %s.',
 'What about the Shodhana methods for %s?',
] as $pattern) {
 $q=sprintf($pattern,$entity); $r=QuestionRouter::route($q,[],$matcher);
 if (($r['routing']['endpoint']??'')!=='compound_methods') throw new RuntimeException($q.': '.json_encode($r));
 $checked++;
}
foreach (['Which purified compounds are produced from Copper?'=>'Purified_Compound',
 'What media are used with a Shodhana method for Copper?'=>'Media',
 'Which diseases are treated with Copper?'=>'Disease'] as $q=>$type) {
 if (QueryUnderstanding::target($q)!==$type) throw new RuntimeException('Wrong requested type: '.$q); $checked++;
}
$sample=json_decode(file_get_contents(__DIR__.'/fixtures/results_first_psoriasis.json'),true)['context']['retrieval'];
foreach (['Purify Lead.','Purification of Copper.'] as $q) {
 $r=QuestionRouter::route($q,[],$matcher);
 if ($r['status']!=='needs_clarification') throw new RuntimeException('Verb guessed result type: '.$q); $checked++;
}
$sample['query_plan']=['result_type'=>'Shodhana_Method'];
if ((ResultsPresentation::build($sample)['reason']??'')!=='result_type_mismatch') throw new RuntimeException('Presentation accepted wrong type');
echo 'PASS '.($checked+1)." method/result contract checks\n";
