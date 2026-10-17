<?php
$endpoints = json_decode(file_get_contents('endpoints_config.json'), true);
$endpoints[2]['direction'] = 'out';
$endpoints[2]['targetVar'] = 'in';

$template = <<<'EOD'
<?php
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/core/EntityResolver.php';
require_once __DIR__ . '/core/EvidenceCorrelator.php';
require_once __DIR__ . '/core/GraphRetriever.php';
require_once __DIR__ . '/core/KnowledgeNormalizer.php';

function execute_{NAME}($entityName, $debugMode = false) {
    if ($entityName === '') { return ['status' => 'error', 'message' => 'Missing parameter']; }
    $debug = ['entity_resolution' => [], 'sql_queries' => [], 'raw_records' => 0, 'duplicates_filtered' => 0, 'final_count' => 0];

    try {
        $entityNode = EntityResolver::resolve('{ENTITY_TYPE}', $entityName, $debug);
        $entity = ['name' => isset($entityNode['name']) ? $entityNode['name'] : $entityName, 'type' => '{ENTITY_TYPE}'];
        if (!$entityNode) { return ['status' => 'entity_not_found', 'message' => 'Entity not found', 'entity' => $entity]; }
        
        $entityRid = $entityNode['rid'];

        $sql = "SELECT @rid as edge_rid, @{TARGET_VAR} as target_rid, pubmedId, doi, ayush_id, result FROM `{EDGE}` WHERE @{DIRECTION} = :entity_rid";
        $edges = GraphRetriever::execute($sql, ['entity_rid' => $entityRid], $debug);

        if (empty($edges)) {
            return KnowledgeNormalizer::formatCanonical($entity, [], [], $debugMode ? $debug : null);
        }
        
        $targetRids = array_unique(array_column($edges, 'target_rid'));
        $targetNameMap = GraphRetriever::fetchNodeNames($targetRids, $debug); // no explicit type to allow Process/Shodhana_Method mix

        $uniqueResults = [];
        $rawCount = 0;
        foreach ($edges as $edge) {
            $rawCount++;
            $targetRid = $edge['target_rid'];
            $targetName = isset($targetNameMap[$targetRid]) ? $targetNameMap[$targetRid] : 'Unknown';
            
            $props = [
                'pubmed_id' => isset($edge['pubmedId']) ? $edge['pubmedId'] : '',
                'doi' => isset($edge['doi']) ? $edge['doi'] : '',
                'ayush_id' => isset($edge['ayush_id']) ? $edge['ayush_id'] : ''
            ];
            
            $dedupKey = KnowledgeNormalizer::generateDeduplicationKey('{EDGE}', $targetName, $props);
            
            if (!isset($uniqueResults[$dedupKey])) {
                $uniqueResults[$dedupKey] = [
                    'related_entity' => $targetName,
                    'properties' => $props
                ];
            }
        }
        
        $debug['raw_records'] = $rawCount;
        $debug['duplicates_filtered'] = $rawCount - count($uniqueResults);
        $debug['final_count'] = count($uniqueResults);
        
        return KnowledgeNormalizer::formatCanonical(
            $entity, 
            ['{ENTITY_TYPE}', '{EDGE}', '{TARGET_TYPE}'], 
            array_values($uniqueResults), 
            $debugMode ? $debug : null
        );
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage(), 'debug' => $debug];
    }
}
EOD;

foreach ($endpoints as $ep) {
    $code = str_replace(
        ['{NAME}', '{ENTITY_TYPE}', '{EDGE}', '{DIRECTION}', '{TARGET_VAR}', '{TARGET_TYPE}'],
        [$ep['name'], $ep['entityType'], $ep['edge'], $ep['direction'], $ep['targetVar'], $ep['targetType']],
        $template
    );
    file_put_contents("api/" . $ep['name'] . ".php", $code);
    echo "Created {$ep['name']}.php\n";
}
