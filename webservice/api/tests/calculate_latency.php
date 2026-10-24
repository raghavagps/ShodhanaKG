<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/ChatbotOrchestrator.php';
require_once __DIR__ . '/../core/ResponseGenerator.php';

$queries = [
    "What media is used for Copper?",
    "What are the symptoms of Alopecia?",
    "Which compounds undergo Nirvapa?"
];

$graphLatencies = [];
$llmTTFTs = [];
$llmTotalTimes = [];

echo "Running Latency Benchmark...\n";

foreach ($queries as $i => $q) {
    echo "Query " . ($i+1) . ": $q\n";
    
    // 1. Measure Graph DB + Routing Latency
    $t0 = microtime(true);
    $orchResult = ChatbotOrchestrator::process($q, []);
    $t1 = microtime(true);
    
    $graphLatencyMs = ($t1 - $t0) * 1000;
    $graphLatencies[] = $graphLatencyMs;
    echo "  -> Graph/Routing Latency: " . round($graphLatencyMs, 2) . " ms\n";
    
    // 2. Measure LLM Latency
    $t2 = microtime(true);
    $firstTokenTime = null;
    
    ResponseGenerator::generateStream($q, $orchResult, [], function($token) use (&$firstTokenTime, $t2) {
        if ($firstTokenTime === null) {
            $firstTokenTime = (microtime(true) - $t2) * 1000;
        }
    });
    
    $t3 = microtime(true);
    if ($firstTokenTime === null) $firstTokenTime = ($t3 - $t2) * 1000; // fallback
    
    $ttft = $firstTokenTime;
    $totalTime = $t3 - $t2;
    
    $llmTTFTs[] = $ttft;
    $llmTotalTimes[] = $totalTime;
    
    echo "  -> Time-to-First-Token (TTFT): " . round($ttft, 2) . " ms\n";
    echo "  -> Total LLM Generation: " . round($totalTime, 2) . " sec\n";
}

echo "\n=== AVERAGE METRICS ===\n";
echo "Average Graph Latency: " . round(array_sum($graphLatencies) / count($graphLatencies), 2) . " ms\n";
echo "Average LLM TTFT: " . round(array_sum($llmTTFTs) / count($llmTTFTs), 2) . " ms\n";
echo "Average Total LLM Generation Time: " . round(array_sum($llmTotalTimes) / count($llmTotalTimes), 2) . " sec\n";
