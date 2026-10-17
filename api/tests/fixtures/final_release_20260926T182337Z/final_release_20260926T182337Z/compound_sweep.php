<?php
// Post-hoc read-only diagnostic sweep; separate from the prespecified benchmark.
$root=dirname(__DIR__,4);
require_once $root.'/api/compound_therapeutic_use.php';
require_once $root.'/api/core/QuestionRouter.php';
$snapshot=json_decode(file_get_contents(__DIR__.'/release/benchmark/snapshot.json'),true);
$out=['scope'=>'Post-hoc diagnostic sweep, not held-out accuracy','cases'=>[]];
foreach ($snapshot['tables']['Compound'] as $node) {
    $name=vertex_label($node);
    $result=execute_compound_therapeutic_use($name);
    $question='What diseases are treated by the purified compound from '.$name.'?';
    $route=QuestionRouter::route($question);
    $out['cases'][]=['id'=>$node['@rid'],'entity'=>$name,'therapeutic_use_status'=>$result['status'],'message'=>$result['message']??null,'result_count'=>count($result['results']??[]),'question'=>$question,'routing'=>$route];
}
file_put_contents(__DIR__.'/compound_sweep.json',json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo count($out['cases'])." compounds examined\n";
