<?php
require_once dirname(__DIR__) . '/core/EvidenceCorrelator.php';

$passed = true;
$total = 0;
$success = 0;

function assertCorrelate($name, $props1, $props2, $expected) {
    global $passed, $total, $success;
    $total++;
    
    // Convert to canonical first using the extractor
    $canon1 = EvidenceCorrelator::extractIdentifiers($props1);
    $canon2 = EvidenceCorrelator::extractIdentifiers($props2);
    
    $result = EvidenceCorrelator::correlate($canon1, $canon2);
    
    if ($result === $expected) {
        $success++;
        echo "PASS: $name\n";
    } else {
        $passed = false;
        echo "FAIL: $name\n";
        echo "  Props1: " . json_encode($canon1) . "\n";
        echo "  Props2: " . json_encode($canon2) . "\n";
        echo "  Expected: " . ($expected ? 'true' : 'false') . ", Got: " . ($result ? 'true' : 'false') . "\n";
    }
}

// 1. Same PubMed ID -> correlated
assertCorrelate("1. Same PubMed ID", 
    ['pubmedId' => '123'], 
    ['pubmedId' => '123'], 
    true);

// 2. Different PubMed IDs -> not correlated
assertCorrelate("2. Different PubMed IDs", 
    ['pubmedId' => '123'], 
    ['pubmedId' => '456'], 
    false);

// 3. Same DOI -> correlated
assertCorrelate("3. Same DOI", 
    ['doi' => '10.1234/xyz'], 
    ['doi' => '10.1234/xyz'], 
    true);

// 4. Different DOI -> not correlated
assertCorrelate("4. Different DOI", 
    ['doi' => '10.1234/xyz'], 
    ['doi' => '10.4567/abc'], 
    false);

// 5. PubMed ID missing but DOI matches
assertCorrelate("5. PubMed ID missing but DOI matches", 
    ['doi' => '10.1234/xyz'], 
    ['doi' => '10.1234/xyz', 'pubmedId' => ''], 
    true);

// 6. Both identifiers missing
assertCorrelate("6. Both identifiers missing", 
    [], 
    [], 
    false);

// 7. One uses "ref" and another uses "pubmedId"
assertCorrelate("7. 'ref' and 'pubmedId' match", 
    ['ref' => 'PMID:789'], 
    ['pubmedId' => '789'], 
    true);

// 8. Empty identifiers
assertCorrelate("8. Empty identifiers", 
    ['pubmedId' => '', 'doi' => '', 'ayush_id' => ''], 
    ['pubmedId' => '', 'doi' => '', 'ayush_id' => ''], 
    false);

// 9. Ayush ID matches
assertCorrelate("9. Same Ayush_ID", 
    ['Ayush_ID' => 'ARP_AYU000100'], 
    ['ayush_id' => 'ARP_AYU000100'], 
    true);

// 10. Different evidence completely
assertCorrelate("10. Completely disjoint identifiers", 
    ['pubmedId' => '111'], 
    ['doi' => '10.2222/foo'], 
    false);

assertCorrelate('DOI ref matches explicit DOI after URL/case normalization',
    ['ref' => ' https://doi.org/10.4103/ASL.ASL_89_24 '], ['DOI' => 'doi:10.4103/asl.asl_89_24'], true);
assertCorrelate('DOI ref never matches numeric PMID', ['ref' => '10.4103/asl.asl_89_24'], ['pubmedId' => '89424'], false);
assertCorrelate('Different PMIDs still reject a shared DOI',
    ['pubmed_id' => '123', 'doi' => '10.4103/same'], ['pubmed_id' => '456', 'doi' => '10.4103/same'], false);
assertCorrelate('Unknown references do not establish study identity', ['ref' => 'local-study'], ['ref' => 'local-study'], false);
assertCorrelate('PMID URL matches numeric ID', ['ref' => 'https://pubmed.ncbi.nlm.nih.gov/123/'], ['pubmedId' => 'PMID:123'], true);
foreach ([
    [['ref' => '10.4103/asl.asl_89_24'], '', ''],
    [['pubmedId' => 'DOI:10.4103/ASL.ASL_89_24'], '', ''],
    [['pubmedId' => '123', 'ref' => '10.4103/example'], '123', ''],
    [['doi' => '10.4103/explicit', 'ref' => '10.4103/other'], '', '10.4103/explicit'],
] as [$raw, $pmid, $doi]) {
    $ids = EvidenceCorrelator::extractIdentifiers($raw);
    if ($ids['pubmed_id'] !== $pmid || $ids['doi'] !== $doi) throw new RuntimeException('Incorrect identifier classification');
}
$unknown = EvidenceCorrelator::extractIdentifiers(['ref' => 'unresolved citation']);
if ($unknown['pubmed_id'] !== '' || ($unknown['unclassified_reference'] ?? '') !== 'unresolved citation') throw new RuntimeException('Unknown reference lost or mislabeled');
echo "\nCompleted EvidenceCorrelator tests. $success/$total passed, plus classification checks.\n";

if ($passed) {
    exit(0);
} else {
    exit(1);
}
