<?php
// Focused live regressions. Expectations come from raw edges, not endpoint output.
require_once __DIR__.'/../core/ChatbotOrchestrator.php';
require_once __DIR__.'/../core/ResponseGenerator.php';
require_once __DIR__.'/../compound_therapeutic_use.php';
$failed=0; $checked=0;
function verifyFix($ok,$label) { global $failed,$checked; $checked++; if (!$ok) $failed++; echo ($ok?'PASS ':'FAIL ').$label."\n"; }
$tables=[];
foreach (['Compound','Shodhana_Method','Purified_Compound','Indication','UNDERGOES','PRODUCES','TREATS'] as $type) $tables[$type]=arcade_command('SELECT FROM `'.$type.'`')['result'];
$records=[]; foreach ($tables as $rows) foreach ($rows as $record) $records[$record['@rid']]=$record;
foreach ($tables['Compound'] as $node) {
    $expected=[];
    foreach ($tables['UNDERGOES'] as $u) if ($u['@out']===$node['@rid']) {
        foreach ($tables['PRODUCES'] as $p) if ($p['@out']===$u['@in'] && !empty($u['ref']) && ($u['ref']??null)===($p['ref']??null)) {
            foreach ($tables['TREATS'] as $t) if ($t['@out']===$p['@in']) $expected[implode('|',[$u['@rid'],$p['@rid'],$t['@rid']])]=true;
        }
    }
    $actual=execute_compound_therapeutic_use(vertex_label($node)); $paths=[];
    foreach ($actual['evidence_paths']??[] as $group) foreach ($group['paths'] as $path) $paths[implode('|',array_column($path['edges'],'id'))]=true;
    verifyFix(($actual['status']??'error')===($expected?'success':'no_data') && !array_diff_key($expected,$paths) && !array_diff_key($paths,$expected),'raw therapeutic paths: '.vertex_label($node));
    $associated=true;
    foreach ($actual['evidence_paths']??[] as $group) foreach ($group['paths'] as $path) {
        $row=$actual['results'][$group['result_index']];
        foreach (['shodhana_method'=>1,'purified_compound'=>2,'indication'=>3] as $field=>$position) $associated=$associated && $row[$field]===vertex_label($records[$path['nodes'][$position]['id']]);
        foreach ($path['edges'] as $edge) {
            $raw=$records[$edge['id']]; $properties=array_filter($raw,static function($key){return $key[0]!=='@';},ARRAY_FILTER_USE_KEY);
            $associated=$associated && $edge['source']===$raw['@out'] && $edge['target']===$raw['@in'] && $edge['properties']==$properties;
        }
    }
    verifyFix($associated,'independent row/edge property association: '.vertex_label($node));
    $route=QuestionRouter::route('What diseases are treated by the purified compound from '.vertex_label($node).'?');
    $ambiguous=$route['status']==='needs_clarification' && ($route['reason']??'')==='entity_type_ambiguous' && count($route['clarification']['candidates']??[])>1;
    verifyFix($ambiguous || ($route['routing']['endpoint']??'')==='integrated_compound_disease','disease result target: '.vertex_label($node));
}
foreach (['Copper','Lead','Sulphur'] as $name) foreach (['Which diseases are treated by the purified compound from %s?', 'What methods and media are used to purify %s?', 'What media and methods are used to purify %s?', 'Give me the Shodhana procedure for %s'] as $i=>$template) {
    $q=sprintf($template,$name); $r=ChatbotOrchestrator::process($q);
    verifyFix(($r['routing']['endpoint']??'')===($i===0?'integrated_compound_disease':'compound_shodhana_process') && !in_array($r['status'],['error','grounding_error'],true),$q);
    if (in_array($i,[1,2]) && $r['status']==='success') {
        $answer=ResponseGenerator::generate($q,$r);
        verifyFix($answer['status']==='success' && !empty($answer['answer_results']) && strpos($answer['answer_results'][0]['label'],' → ')!==false,'paired method/media presentation: '.$name);
    }
}
$r=QuestionRouter::route('Show the stored property Shodhana procedure of Copper');
verifyFix(($r['routing']['endpoint']??'')==='node_property','explicit node property stays node-only');
foreach (['Copper','Lead','Sulphur'] as $name) {
    foreach (['Which media are used in the purification method for %s?' => 'compound_media',
        'Which purified compounds are produced by the Shodhana method for %s?' => 'compound_purified_forms'] as $template=>$endpoint) {
        $q=sprintf($template,$name); $r=QuestionRouter::route($q);
        verifyFix(($r['routing']['endpoint']??'')===$endpoint,'output noun outranks intermediate entity: '.$q);
    }
}
$r=QuestionRouter::route('Which methods and diseases are associated with Copper?');
verifyFix($r['status']==='needs_clarification' && ($r['reason']??'')==='unsupported_result_combination','unsupported multiple outputs are not silently reduced');
$state=ConversationContext::validate(['version'=>2,'requested_result_types'=>['Shodhana_Method','Disease']]);
verifyFix(!empty($state['_invalid']),'unsupported client result combination is rejected');
require_once __DIR__.'/endpoint_test_helper.php';
foreach (['config.php','nonexistent_remediation_endpoint.php'] as $script) {
    $rejected=false;
    try { test_endpoint_output(__DIR__.'/../'.$script,[]); } catch (Throwable $e) { $rejected=true; }
    verifyFix($rejected,'harness rejects missing JSON or failed child: '.$script);
}
echo json_encode(['checks'=>$checked,'passed'=>$checked-$failed,'failed'=>$failed])."\n";
exit($failed?1:0);
