<?php
require_once __DIR__.'/endpoint_test_helper.php';
require_once __DIR__.'/legacy_contract_helper.php';
$checked=0;
foreach (['Croton tiglium Linn.','Copper','Datura metel L.','Zinc'] as $compound) {
    foreach (['compound_media','compound_validation'] as $endpoint) {
        $response=json_decode(test_endpoint_output(dirname(__DIR__).'/'.$endpoint.'.php', ['compound'=>$compound,'debug'=>'1']),true,512,JSON_THROW_ON_ERROR);
        legacy_assert_facts($endpoint,$compound,$response);
        $checked++;
    }
}
echo "PASS $checked independent media/validation comparisons (all original seeds)\n";
