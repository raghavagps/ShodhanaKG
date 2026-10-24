<?php
/** Evidence qualifiers refine a requested target; they must not replace it. */
require_once __DIR__.'/../core/QuestionRouter.php';
$checks=0; $failures=0;
foreach (['Copper','Lead','Sulphur'] as $entity) {
    $cases=[];
    foreach (['with evidence','with provenance','from the same study','with a shared reference'] as $qualifier) {
        $cases['What media are used for '.$entity.' '.$qualifier.'?']='provenance_compound_method_media';
    }
    $cases += [
        'What media are used for '.$entity.'?' => 'compound_media',
        'Which media are used in the purification method for '.$entity.'?' => 'compound_media',
        'What methods and media are used to purify '.$entity.' with evidence?' => 'compound_shodhana_process',
        'Give me the Shodhana procedure for '.$entity => 'compound_shodhana_process',
        'Which diseases are treated by purified compounds from '.$entity.' using media, with evidence?' => 'integrated_compound_disease',
        'Which purified compounds are produced from '.$entity.' using media, with evidence?' => 'compound_purified_forms',
        'Which methods purify '.$entity.' using media, with evidence?' => 'compound_methods',
    ];
    foreach ($cases as $question=>$endpoint) {
        $result=QuestionRouter::route($question); $checks++;
        $ok=($result['routing']['endpoint']??null)===$endpoint;
        if (!$ok) $failures++;
        echo ($ok?'PASS ':'FAIL ').$question.' -> '.($result['routing']['endpoint']??$result['status'])."\n";
    }
}
echo json_encode(['checks'=>$checks,'passed'=>$checks-$failures,'failed'=>$failures])."\n";
exit($failures?1:0);
