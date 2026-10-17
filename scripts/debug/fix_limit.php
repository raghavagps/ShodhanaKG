<?php
$files = ['api/provenance-subgraph.php', 'api/compound-provenance.php'];
foreach ($files as $f) {
    $c = file_get_contents($f);
    $c = preg_replace('/\$limit = min\(max\(isset\(\$_GET\[\'limit\'\]\) \? \(int\)\$_GET\[\'limit\'\] : 50, 1\), 200\);/', '$limit = min(max(isset($_GET[\'limit\']) ? (int)$_GET[\'limit\'] : 2000, 1), 5000);', $c);
    file_put_contents($f, $c);
}
echo "Replaced limit in PHP.\n";
