<?php
// mock_arcadedb.php
$input = file_get_contents('php://input');
$req = json_decode($input, true);

$params = isset($req['params']) ? $req['params'] : [];
$pattern = isset($params['name']) ? $params['name'] : (isset($params['pattern']) ? trim($params['pattern'], '%') : '');

// If it's empty or stopword chunk, return dummy data to simulate endpoints
if (strlen($pattern) < 3) {
    if (isset($req['command']) && (stripos($req['command'], 'MATCH') !== false || stripos($req['command'], 'edge_rid') !== false || stripos($req['command'], 'WHERE @out') !== false || stripos($req['command'], 'WHERE @in') !== false)) {
        echo json_encode([
            'result' => [
                [
                    'edge_rid' => '#2:1',
                    'method_rid' => '#1:2',
                    'media_rid' => '#1:3',
                    'study_rid' => '#1:4',
                    'compound_rid' => '#1:5',
                    'disease_rid' => '#1:6',
                    'therapy_rid' => '#1:7',
                    'symptom_rid' => '#1:8',
                    'formulation_rid' => '#1:9',
                    'dummy_key' => 'dummy_value', 
                    'description' => 'mocked', 
                    'name' => 'mocked',
                    'procedure' => 'mock procedure',
                    'result' => 'mock result',
                    'pubmedId' => '12345',
                    'doi' => '10.0/mock'
                ]
            ]
        ]);
    } else {
        echo json_encode(['result' => []]);
    }
    exit;
}
file_put_contents('mock_arcadedb.log', "pattern=$pattern typeMatch=" . (preg_match('/FROM `([^`]+)`/', $req['command'] ?? '', $m) ? $m[1] : '') . "\n", FILE_APPEND);

$known_entities = [
    'copper' => 'Compound',
    'croton tiglium linn.' => 'Compound',
    'croton tiglium' => 'Compound',
    'alopecia' => 'Disease',
    'fever' => 'Indication',
    'nirvapa' => 'Shodhana_Method',
    'mardana' => 'Shodhana_Method',
    'sesame oil' => 'Media',
    'aconitine' => 'Toxic_Substance',
    'shodhita gunja' => 'Purified_Compound',
    'kandu' => 'Symptoms',
    'vamana' => 'Therapy',
    'triphala guggulu' => 'Formulation'
];

$patternLower = strtolower($pattern);
$found = false;
$entityName = $pattern;

// Extract requested class type from SQL
$typeMatch = '';
if (preg_match('/FROM `([^`]+)`/', $req['command'], $m)) {
    $typeMatch = $m[1];
}

foreach ($known_entities as $name => $type) {
    if (strpos($patternLower, $name) !== false || strpos($name, $patternLower) !== false) {
        if ($typeMatch === $type || $typeMatch === '') {
            $found = true;
            $entityName = $name;
            break;
        }
    }
}

if ($found) {
    // Exact casing for some
    if ($entityName === 'croton tiglium linn.') $entityName = 'Croton tiglium Linn.';
    else $entityName = ucfirst($entityName);

    echo json_encode([
        'result' => [
            [
                '@rid' => '#1:1',
                '@class' => $typeMatch,
                'name' => $entityName
            ]
        ]
    ]);
} else {
    echo json_encode(['result' => []]);
}
