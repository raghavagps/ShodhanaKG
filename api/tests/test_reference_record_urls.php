<?php
require_once __DIR__.'/../core/ReferenceClassifier.php';
$audit=json_decode(file_get_contents(__DIR__.'/../../docs/chatbot/audits/identifier_rules_2026_09_24_v1/audit.json'),true);
$count=0;
foreach ($audit['rows'] as $row) {
 if ($row['assessed_type']!=='AYUSH') continue;
 $expected=$row['registry_metadata']['ayush']['url'];
 foreach ($row['edge_ids'] as $id) {
  $ref=ReferenceClassifier::resolve(['@rid'=>$id,'ref'=>$row['Reference']])[0];
  preg_match('~https://arp\.ayush\.gov\.in/View_Res_Landing_Url\?rp6=[0-9]+&rptypeReport=0&rpreportname=0(?:\s|$)~',$ref['source']??'', $m);
  if (trim($m[0]??'')!==$expected) throw new RuntimeException('Incorrect AYUSH source URL for '.$id);
 }
 $count++;
}
if ($count!==147) throw new RuntimeException('Incomplete AYUSH coverage');
if (isset(ReferenceClassifier::resolve(['ayush_id'=>'76448'])[0]['record_url'])) throw new RuntimeException('Guessed row ID');
echo "PASS $count AYUSH mappings across every bound edge against independent audit\n";
