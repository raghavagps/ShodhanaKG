<?php
/** Independent frozen-KG contract; historical response captures remain archival. */
require_once __DIR__.'/endpoint_test_helper.php';
require_once __DIR__.'/legacy_contract_helper.php';
$endpoint = 'compound_media';
$script = dirname(__DIR__).'/'.$endpoint.'.php';
$response = json_decode(test_endpoint_output($script, ['compound'=>'Croton tiglium Linn.']), true, 512, JSON_THROW_ON_ERROR);
if ($response['status'] !== 'success' || $response['entity']['name'] !== 'Croton tiglium Linn.') throw new RuntimeException('Wrong seed/status');
legacy_assert_facts($endpoint, 'Croton tiglium Linn.', $response);
$missing = json_decode(test_endpoint_output($script, ['compound'=>'Fake Compound 123']), true, 512, JSON_THROW_ON_ERROR);
if ($missing['status'] !== 'entity_not_found' || !empty($missing['results'])) throw new RuntimeException('Missing entity returned facts or wrong status');
echo "PASS independent results, exact attribution/properties, and missing-entity isolation\n";
