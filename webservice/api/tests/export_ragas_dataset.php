<?php
// Script to generate a RAGAS-compatible evaluation dataset
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/EvaluationExport.php';
require_once __DIR__ . '/EvaluationAssertions.php';

$options = getopt('', ['fixture:', 'output:', 'filter:']);
$inputFile = $options['fixture'] ?? __DIR__ . '/fixtures/chatbot_evaluation_cases.json';
$outputFile = $options['output'] ?? __DIR__ . '/fixtures/ragas_dataset.json';

try {
    $cases = json_decode(file_get_contents($inputFile), true, 512, JSON_THROW_ON_ERROR);
    EvaluationAssertions::validateCases($cases);
    if (isset($options['filter'])) $cases = array_values(array_filter($cases, static function ($c) use ($options) { return strpos($c['id'], $options['filter']) === 0; }));
    if (!$cases) throw new InvalidArgumentException('Filter matched zero cases');
    if (realpath($outputFile) !== false && realpath($outputFile) === realpath($inputFile)) throw new InvalidArgumentException('Output must not overwrite the fixture');
} catch (Throwable $e) { fwrite(STDERR, 'SETUP ERROR: ' . $e->getMessage() . "\n"); exit(2); }
$dataset = [];

echo "Starting RAGAS dataset generation for " . count($cases) . " cases...\n";

$count = 0;
foreach ($cases as $case) {
    $count++;
    $question = $case['question'];
    echo "[$count/" . count($cases) . "] Processing: $question\n";
    
    // HTTP POST to local chatbot API (note the /api/ path)
    $ch = curl_init('http://localhost:8081/api/chatbot_response.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['question' => $question, 'history' => $case['history'] ?? []]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); 
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $row = evaluationExportRow($case, $httpCode, $response);
    $row['export_metadata'] = ['captured_at' => gmdate('c'), 'fixture_sha256' => hash_file('sha256', $inputFile),
        'http_status' => $httpCode, 'transport' => 'JSON',
        'model' => 'not reported by server',
        'local_generator_source_sha256' => hash_file('sha256', __DIR__ . '/../core/ResponseGenerator.php')];
    $dataset[] = $row;
    echo "  -> Status: " . $row['status'] . "\n";

    // Save intermediate progress
    file_put_contents($outputFile, json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

echo "\nDataset generated successfully at: $outputFile\n";
