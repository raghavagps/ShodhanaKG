<?php
require_once __DIR__ . '/../core/EvidenceCorrelator.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$audit = json_decode(file_get_contents(__DIR__ . '/../../docs/chatbot/audits/identifier_rules_2026_09_24_v1/audit.json'), true);
$counts = []; $edges = [];
foreach ($audit['rows'] as $row) {
    $counts[$row['assessed_type']] = ($counts[$row['assessed_type']] ?? 0) + 1;
    foreach ($row['edge_ids'] as $id) {
        $refs = ReferenceClassifier::resolve(['@rid' => $id, 'ref' => $row['Reference']]);
        check(count($refs) === 1 && $refs[0]['namespace'] === $row['assessed_type'] && $refs[0]['status'] === 'verified', 'Wrong audited mapping: ' . $id);
        $edges[$id] = true;
    }
}
check($counts['PMID'] === 251 && $counts['AYUSH'] === 147 && $counts['DOI'] === 12 && count($edges) === 10123, 'Incomplete audit coverage');
foreach (['1234', '12345678', '123456789', 'letters123', '10.1000/182'] as $raw) {
    check(ReferenceClassifier::resolve(['ref' => $raw])[0]['namespace'] === null, 'Guessed untyped reference');
}
$pmid = EvidenceCorrelator::extractIdentifiers(['ref' => 'PMID: 76448']);
$ayush = EvidenceCorrelator::extractIdentifiers(['ayush_id' => '76448(Ay)']);
check($pmid['pubmed_id'] === '76448' && $ayush['ayush_id'] === '76448', 'Explicit short IDs');
check(!EvidenceCorrelator::correlate($pmid, $ayush), 'Cross-namespace join');
check(ReferenceClassifier::resolve(['ref' => 'ARP_AYU009603'])[0]['namespace'] === 'AYUSH', 'ARP mistaken for DOI');
check(EvidenceCorrelator::extractIdentifiers(['doi' => 'https://doi.org/10.1000/ABC'])['doi'] === '10.1000/abc', 'DOI normalization');
check(EvidenceCorrelator::extractIdentifiers(['pubmed_id' => '1200'])['pubmed_id'] === '1200', 'Truncated numeric ID');
$row = current(array_filter($audit['rows'], static function($r) { return $r['Reference'] === '76448'; }));
$conflict = EvidenceCorrelator::extractIdentifiers(['@rid' => $row['edge_ids'][0], 'ref' => '76448', 'pubmed_id' => '76448']);
check($conflict['pubmed_id'] === '' && $conflict['ayush_id'] === '', 'Conflicting namespaces accepted');
check(ReferenceClassifier::resolve(['@rid' => $row['edge_ids'][0], 'ref' => '99999999'])[0]['status'] === 'conflict', 'Changed binding accepted');
check(ReferenceClassifier::resolve(['@rid' => '#999:1', 'ref' => '76448'])[0]['namespace'] === null, 'Mapping leaked to new edge');
check(ReferenceClassifier::resolve(['doi' => 'nonsense'])[0]['namespace'] === null, 'Malformed DOI accepted');
echo json_encode(['references' => $counts, 'audited_edges' => count($edges), 'adversarial_cases' => 'passed']) . "\n";
