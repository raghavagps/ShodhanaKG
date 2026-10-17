<?php
require_once __DIR__.'/legacy_contract_helper.php';
$cases=[
 ['Name any 5 media used for Mardana Shodhana.','process_media','process_media','success'],
 ['Name any media used for Nirvapa.','process_media','process_media','success'],
 ['Which purified compound treats fever?','indication_compound','semantic_query','success'],
 ['Which purified compound treats Acute Prolapsed Inter-Vertebral Disc?','integrated_disease_compound','integrated_disease_compound','no_data'],
];
$checks=0;
foreach ($cases as [$q,$selection,$endpoint,$status]) {
    $response=legacy_http_answer($q,$selection);
    if ($response['status']!==$status || ($response['context']['routing']['endpoint'] ?? '')!==$endpoint) throw new RuntimeException($q.': wrong route/status');
    if ($status==='success' && (empty($response['answer_results']) || empty($response['citations']))) throw new RuntimeException('Missing supported inverse results');
    if ($status==='no_data' && (!empty($response['answer_results']) || !empty($response['citations']))) throw new RuntimeException('No-data isolation failed');
    echo "PASS $q\n"; $checks++;
}
echo "PASS $checks inverse HTTP contracts\n";
