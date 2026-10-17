<?php
/** Live regression: independently join raw edges, then compare the endpoint's tuples.
 * Requires ArcadeDB. No shelling into HTTP entrypoint guards, no fixed historical counts.
 */
require_once __DIR__ . '/../disease_formulation.php';

function rawNode(string $rid): array {
    static $cache = [];
    if (!preg_match('/^#\d+:\d+$/D', $rid)) throw new RuntimeException('Invalid RID');
    if (!isset($cache[$rid])) $cache[$rid] = arcade_command('SELECT FROM ' . $rid)['result'][0] ?? [];
    return $cache[$rid];
}

function referenceFields(array $edge): array {
    // Namespace comes from the independent reviewed audit, bound to this edge.
    static $bindings = null;
    if ($bindings === null) {
        $bindings = [];
        $audit = json_decode(file_get_contents(dirname(__DIR__,2).'/docs/chatbot/audits/identifier_rules_2026_09_24_v1/audit.json'), true);
        foreach ($audit['rows'] as $row) foreach ($row['edge_ids'] as $id) $bindings[$id] = $row;
    }
    if (!empty($edge['ref'])) {
        $binding = $bindings[$edge['@rid']] ?? null;
        if (!$binding || $binding['Reference'] !== $edge['ref']) throw new RuntimeException('Unreviewed reference in test oracle');
        $field = ['PMID'=>'pubmed_id','DOI'=>'doi','AYUSH'=>'ayush_id'][$binding['assessed_type']];
        $fields = ['pubmed_id'=>'','doi'=>'','ayush_id'=>''];
        $fields[$field] = $field === 'doi' ? strtolower($edge['ref']) : $edge['ref'];
        return $fields;
    }
    $fields = [];
    foreach (['pubmed_id' => ['pubmedId', 'pubmed_id', 'Pubmed ID'],
        'doi' => ['doi', 'DOI'], 'ayush_id' => ['ayush_id', 'Ayush_ID', 'ayushId']] as $key => $aliases) {
        $fields[$key] = '';
        foreach ($aliases as $alias) {
            if (!empty($edge[$alias])) { $fields[$key] = (string)$edge[$alias]; break; }
        }
    }
    return $fields;
}

function tupleKey(array $row): string {
    return json_encode([$row['therapy'], $row['formulation'], $row['properties']['phase'],
        $row['properties']['pubmed_id'], $row['properties']['doi'], $row['properties']['ayush_id']]);
}

$formEdges = arcade_command('SELECT FROM USES_FORMULATION')['result'];
$checked = 0;
foreach (['Alopecia', 'Ankylosing Spondylitis'] as $disease) {
    $seeds = arcade_command('SELECT FROM Disease WHERE name = :name', 'sql', ['name' => $disease])['result'];
    if (count($seeds) !== 1) throw new RuntimeException('Ambiguous or missing test seed: ' . $disease);
    $expected = [];
    $rejected = 0;
    foreach (['TREATED_BY', 'PRE_TREATED_BY', 'POST_TREATED_BY'] as $phase) {
        $treatmentEdges = arcade_command('SELECT FROM `' . $phase . '` WHERE @out = :rid', 'sql', ['rid' => $seeds[0]['@rid']])['result'];
        foreach ($treatmentEdges as $treatment) {
            $therapy = rawNode($treatment['@in']);
            if (($therapy['@type'] ?? '') !== 'Therapy') continue;
            foreach ($formEdges as $formEdge) {
                if ($formEdge['@out'] !== $treatment['@in']) continue;
                $formulation = rawNode($formEdge['@in']);
                if (($formulation['@type'] ?? '') !== 'Formulation') continue;
                $a = referenceFields($treatment); $b = referenceFields($formEdge);
                $matches = false;
                foreach (['pubmed_id', 'doi', 'ayush_id'] as $key) {
                    if ($a[$key] !== '' && $b[$key] !== '') { $matches = $a[$key] === $b[$key]; break; }
                }
                if (!$matches) { $rejected++; continue; }
                $row = ['therapy' => vertex_label($therapy), 'formulation' => vertex_label($formulation), 'properties' => $b + ['phase' => $phase]];
                $expected[tupleKey($row)] = true;
            }
        }
    }
    $actual = execute_disease_formulation($disease);
    $wantStatus = $expected ? 'success' : 'no_data';
    if ($actual['status'] !== $wantStatus) throw new RuntimeException("$disease: wrong status");
    $actualTuples = [];
    foreach ($actual['results'] as $row) $actualTuples[tupleKey($row)] = true;
    if (array_diff_key($expected, $actualTuples) || array_diff_key($actualTuples, $expected)) throw new RuntimeException("$disease: missing or unsupported relationship/evidence tuples");
    echo "PASS $disease: " . count($expected) . " independently verified tuples; $rejected uncorrelated candidates excluded\n";
    $checked++;
}
echo "PASS $checked live disease-formulation comparisons\n";
