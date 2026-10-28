<?php
require_once __DIR__ . '/../core/ResultsPresentation.php';
function requireEvidence($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$report = json_decode(file_get_contents(__DIR__ . '/fixtures/legacy_evidence_benchmark.json'), true, 512, JSON_THROW_ON_ERROR);
$count = 0; $scopes = 0;
foreach ($report['cases'] as $sample) {
    $retrieval = $sample['actual'];
    if (($retrieval['status'] ?? '') !== 'success' || !isset($retrieval['evidence_paths'])) continue;
    $old = $retrieval; unset($old['evidence_paths']);
    $baseline = ResultsPresentation::build($old);
    $answer = ResultsPresentation::build($retrieval);
    requireEvidence($answer['status'] === 'success', $sample['id'] . ': invalid answer');
    requireEvidence($answer['answer_results'] === $baseline['answer_results'], 'Changed result/fact/citation IDs');
    foreach ($retrieval['evidence_paths'] as $entry) {
        $expected = [];
        foreach ($entry['paths'] as $path) foreach ($path['edges'] as $edge) $expected[$edge['id']] = $edge;
        $citation = $answer['citations'][$entry['result_index']];
        requireEvidence($citation['attribution'] === 'per_edge', 'Missing per-edge attribution');
        requireEvidence(count($citation['scopes']) === count($expected), 'Lost or extra edge scope');
        foreach ($citation['scopes'] as $scope) {
            $edge = $expected[$scope['edge_id']] ?? null;
            requireEvidence($edge && $scope['properties'] === $edge['properties'], 'Changed/dropped scope properties');
            requireEvidence($scope['source_id'] === $edge['source'] && $scope['target_id'] === $edge['target'], 'Changed direction');
            $scopes++;
        }
    }
    $bad = $retrieval;
    $bad['evidence_paths'][0]['paths'][0]['edges'][0]['evidence']['pubmed_id'] = 'invented';
    requireEvidence(ResultsPresentation::build($bad)['status'] === 'error', 'Accepted changed reference');
    $bad = $retrieval;
    array_pop($bad['evidence_paths']);
    requireEvidence(ResultsPresentation::build($bad)['status'] === 'error', 'Accepted missing row attribution');
    $count++;
}
echo "PASS $count legacy answers, $scopes exact edge scopes, stable result IDs, invalid attribution rejected\n";
