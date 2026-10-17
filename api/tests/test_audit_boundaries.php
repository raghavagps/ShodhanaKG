<?php
require_once __DIR__ . '/../core/ChatRequest.php';
require_once __DIR__ . '/../core/ModelConfig.php';
require_once __DIR__ . '/../core/KgStatistics.php';
function checkBoundary($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$invalid = ['null', '[]', '{', '{"question":[]}', '{"question":12}', '{"question":" "}',
    '{"question":"q","stream":[]}', '{"question":"q","history":{}}',
    '{"question":"q","conversation_context":[]}', '{"question":"q","selected_entity":"x"}',
    '{"question":"q","page_offset":-1}', json_encode(['question'=>str_repeat('x',4001)])];
foreach ($invalid as $body) {
    try { ChatRequest::decode($body); throw new RuntimeException('Accepted invalid request'); }
    catch (InvalidArgumentException $expected) {}
}
checkBoundary(ChatRequest::decode('{"question":" q ","stream":true,"conversation_context":{"version":2}}')['question'] === 'q', 'Valid request preserved');
$saved = $_ENV;
$_ENV['OLLAMA_URL'] = 'http://primary/'; $_ENV['OLLAMA_BASE_URL'] = 'http://legacy/';
checkBoundary(ModelConfig::baseUrl() === 'http://primary', 'Documented setting wins');
unset($_ENV['OLLAMA_URL']);
checkBoundary(ModelConfig::baseUrl() === 'http://legacy', 'Legacy alias supported');
$_ENV = $saved;
$queries = [];
$query = function($sql) use (&$queries) {
    $queries[] = $sql;
    if (strpos($sql, 'schema:types') !== false) return ['result'=>[['name'=>'Disease','type'=>'vertex'],['name'=>'EDGE','type'=>'edge']]];
    if (strpos($sql,'COUNT(*)') !== false) return ['result'=>[['cnt'=>2]]];
    return ['result'=>[
        ['@rid'=>'test:1','pubmedId'=>'12345670'], ['@rid'=>'test:2','pubmedId'=>'1234567'],
        ['@rid'=>'test:3','ayush_id'=>'12345670'], ['@rid'=>'test:4','pubmedId'=>'12345670']]];
};
$stats = KgStatistics::collect($query, ['Disease']);
checkBoundary($stats['totalReferences'] === 3, 'Trailing zeros and namespace identity must survive; duplicates deduplicate');
checkBoundary($stats['totalRelations'] === 4 && $stats['totalNodes'] === 2, 'Measured totals without a floor');
try { KgStatistics::collect(function($sql) { throw new RuntimeException('offline'); }, ['Disease']); throw new LogicException('Hidden failure'); }
catch (RuntimeException $expected) { checkBoundary($expected->getMessage() === 'offline', 'Query failures propagate'); }
echo "PASS request validation, model configuration and reference statistics boundaries\n";

require_once __DIR__ . '/../core/GraphRead.php';
$queried = [];
$query = function($sql) use (&$queried) {
    $queried[] = $sql;
    if (strpos($sql, 'schema:types') !== false) return ['result'=>[['name'=>'HAS_SYMPTOM','type'=>'edge']]];
    throw new RuntimeException('edge query failed');
};
checkBoundary(GraphRead::edges(['#1:2'], ['RESOLVED_BY'], 5, $query) === [], 'Absent legacy type is not queried');
try { GraphRead::edges(['#1:2'], ['HAS_SYMPTOM'], 5, $query); throw new LogicException('Partial graph falsely succeeded'); }
catch (RuntimeException $expected) { checkBoundary($expected->getMessage() === 'edge query failed', 'Actual graph failure propagates'); }
echo "PASS graph failure boundaries\n";
