<?php
require_once __DIR__ . '/../core/PhpCompatibility.php';
require_once __DIR__ . '/../core/QuestionRouter.php';
require_once __DIR__ . '/../core/ResultsPresentation.php';
$checks = 0;
function compatibilityCheck($ok, $message) { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
compatibilityCheck(PhpCompatibility::value('12', 'int') === 12, 'Scalar coercion');
compatibilityCheck(PhpCompatibility::value(null, 'array', true) === null, 'Nullable value');
foreach ([['value'=>[], 'type'=>'string'], ['value'=>'bad', 'type'=>'int'], ['value'=>null, 'type'=>'bool'], ['value'=>'missing_callback', 'type'=>'callable']] as $invalid) {
    $rejected = false;
    try { PhpCompatibility::value($invalid['value'], $invalid['type']); }
    catch (Exception $e) { $rejected = true; }
    catch (Throwable $e) { $rejected = true; }
    compatibilityCheck($rejected, 'Invalid typed value rejected: '.$invalid['type']);
}
compatibilityCheck(PhpCompatibility::jsonDecode('null') === null, 'Valid JSON null');
compatibilityCheck(PhpCompatibility::jsonDecode('{"n":3}', true)['n'] === 3, 'JSON object');
foreach (['{', str_repeat('[', 20)] as $json) {
    $rejected = false;
    try { PhpCompatibility::jsonDecode($json, true, 8); } catch (Exception $e) { $rejected = true; }
    compatibilityCheck($rejected, 'Malformed/deep JSON rejected');
}
$rejected = false;
try { PhpCompatibility::jsonEncode(["\xb1\x31"]); } catch (Exception $e) { $rejected = true; }
compatibilityCheck($rejected, 'Invalid UTF-8 rejected');
compatibilityCheck(PhpCompatibility::endsWith('compound_general', '_general'), 'Suffix');
compatibilityCheck(!PhpCompatibility::endsWith('compound', '_general'), 'Non-suffix');
compatibilityCheck(PhpCompatibility::compare([1,'a'], [1,'b']) === -1, 'Deterministic sorting');
foreach (['PRE_TREATED_BY'=>'pre-treated','TREATED_BY'=>'treated','POST_TREATED_BY'=>'post-treated'] as $edge=>$phase) compatibilityCheck(EdgePresentation::phase($edge) === $phase, 'Phase '.$edge);
$matcher = new ConversationEntities([
 ['@rid'=>'#1:0','@type'=>'Compound','name'=>'Copper'],
 ['@rid'=>'#1:1','@type'=>'Compound','name'=>'Lead']
]);
foreach (['Copper', 'Lead'] as $entity) foreach (['What Shodhana method is used to purify %s?', 'Shodhana method to purify %s.', 'List purification techniques for %s.'] as $pattern) {
    $route=QuestionRouter::route(sprintf($pattern,$entity), [], $matcher);
    compatibilityCheck($route['routing']['endpoint'] === 'compound_methods', 'Requested method result');
}
$sample=PhpCompatibility::jsonDecode(file_get_contents(__DIR__.'/fixtures/results_first_psoriasis.json'),true)['context']['retrieval'];
$presentation=ResultsPresentation::build($sample);
compatibilityCheck($presentation['status'] === 'success', 'Evidence presentation');
compatibilityCheck(count($presentation['answer_results']) === 19, 'All unique symptoms');
compatibilityCheck(count($presentation['citations']) === 38, 'All evidence records');
echo 'PASS '.$checks.' PHP compatibility assertions on '.PHP_VERSION."\n";
