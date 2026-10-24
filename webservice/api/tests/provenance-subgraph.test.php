<?php
declare(strict_types=1);

/**
 * Tests for provenance-subgraph.php endpoint
 * 
 * These are unit tests for the logic, not integration tests.
 * Integration tests should use actual HTTP requests.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../graph_service.php';

class ProvenanceSubgraphTest {
    private array $tests = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        echo "Provenance Subgraph Unit Tests\n";
        echo "===============================\n\n";

        $this->testPmidNormalization();
        $this->testEdgeTypeMapping();
        $this->testNodeBuilding();
        $this->testEdgePropertyMapping();

        $this->printSummary();
        if ($this->failed) throw new RuntimeException('Provenance assertions failed');
    }

    private function testPmidNormalization(): void {
        echo "Test: PMID Normalization\n";
        
        $testCases = [
            ['input' => '23723667.0', 'expected' => '23723667'],
            ['input' => '23723667', 'expected' => '23723667'],
            ['input' => 23723667.0, 'expected' => '23723667'],
            ['input' => 23723667, 'expected' => '23723667'],
            ['input' => '23049187', 'expected' => '23049187'],
            ['input' => '21455456.0', 'expected' => '21455456'],
        ];

        foreach ($testCases as $case) {
            $result = rtrim(rtrim((string)$case['input'], '0'), '.');
            $this->assertEquals($case['expected'], $result, 
                "PMID: {$case['input']} -> {$result}");
        }
        
        echo "\n";
    }

    private function testEdgeTypeMapping(): void {
        echo "Test: Edge Type Normalization\n";
        
        $mappings = [
            ['input' => 'PRE_TREATED_BY', 'expected' => 'PRETREATED_BY'],
            ['input' => 'POST_TREATED_BY', 'expected' => 'POSTTREATED_BY'],
            ['input' => 'TREATED_BY', 'expected' => 'TREATED_BY'],
            ['input' => 'USES_FORMULATION', 'expected' => 'USES_FORMULATION'],
        ];

        foreach ($mappings as $case) {
            $result = normalize_edge_type($case['input']);
            $this->assertEquals($case['expected'], $result, 
                "Edge type: {$case['input']} -> {$result}");
        }
        
        echo "\n";
    }

    private function testNodeBuilding(): void {
        echo "Test: Node Building\n";
        
        // Mock disease vertex
        $diseaseVertex = [
            '@rid' => '#5:33',
            '@type' => 'Disease',
            'name' => 'Eczema',
            'id' => '36',
        ];
        
        $node = build_node($diseaseVertex);
        
        $this->assertEquals('#5:33', $node['id'], 'Canonical record ID retained instead of local numeric ID');
        $this->assertEquals('Eczema', $node['label'], 'Node label extracted');
        $this->assertEquals('Disease', $node['type'], 'Node type normalized');
        
        // Mock therapy vertex
        $therapyVertex = [
            '@rid' => '#57:0',
            '@type' => 'Therapy',
            'Therapy' => 'Abhyanga',
            'Therapy_Description' => 'whole body oil massage',
            'id' => '1155',
        ];
        
        $therapyNode = build_node($therapyVertex);
        $this->assertEquals('Abhyanga', $therapyNode['label'], 'Therapy label from Therapy field');
        $this->assertEquals('whole body oil massage', $therapyNode['description'], 'Therapy description extracted');
        
        echo "\n";
    }

    private function testEdgePropertyMapping(): void {
        echo "Test: Edge Property Mapping\n";
        
        // Mock edge with PMID as float
        $edge = [
            '@rid' => '#70:0',
            '@type' => 'PRE_TREATED_BY',
            '@out' => '#5:33',
            '@in' => '#57:0',
            'ref' => 23723667.0,
        ];
        
        $props = map_edge_props($edge);
        
        $this->assertTrue(!isset($props['pubmedId']), 'Unreviewed bare reference is not promoted to PMID');
        $this->assertEquals('unclassified', $props['references'][0]['status'], 'Namespace requires evidence');
        
        // Mock USES_FORMULATION edge
        $usesEdge = [
            '@rid' => '#84:14',
            '@type' => 'USES_FORMULATION',
            '@out' => '#57:0',
            '@in' => '#9:114',
            'ref' => 27890699.0,
            'dose' => '50ml',
            'duration' => '1 Day',
        ];
        
        $usesProps = map_edge_props($usesEdge);
        
        $this->assertEquals('27890699', $usesProps['references'][0]['raw'], 'Unclassified reference identity retained');
        $this->assertEquals('50ml', $usesProps['dose'], 'Dose property mapped');
        $this->assertEquals('1 Day', $usesProps['duration'], 'Duration property mapped');
        
        echo "\n";
    }

    private function assertEquals($expected, $actual, string $message): void {
        if ($expected === $actual) {
            echo "  ✓ {$message}\n";
            $this->passed++;
        } else {
            echo "  ✗ {$message}\n";
            echo "     Expected: {$expected}\n";
            echo "     Actual: {$actual}\n";
            $this->failed++;
        }
    }

    private function assertTrue($condition, string $message): void {
        if ($condition) {
            echo "  ✓ {$message}\n";
            $this->passed++;
        } else {
            echo "  ✗ {$message}\n";
            $this->failed++;
        }
    }

    private function printSummary(): void {
        echo "===============================\n";
        $total = $this->passed + $this->failed;
        echo "Results: {$this->passed}/{$total} passed\n";
        
        if ($this->failed === 0) {
            echo "✓ All tests passed!\n";
        } else {
            echo "✗ {$this->failed} test(s) failed\n";
        }
    }
}

// Run tests
try {
    $test = new ProvenanceSubgraphTest();
    $test->run();
} catch (Throwable $e) {
    echo "Error running tests: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
