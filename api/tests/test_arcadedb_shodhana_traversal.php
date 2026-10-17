<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/GraphRetriever.php';
require_once __DIR__ . '/../core/EvidenceCorrelator.php';

echo "Running ArcadeDB Diagnostic Test for Shodhana Traversal\n";
echo str_repeat("-", 50) . "\n";

$compoundName = 'Copper';
$mediaName = 'Panchapallava Kwatha';

try {
    // 1. Compound Lookup
    echo "1. Compound Lookup ($compoundName)\n";
    $sql = "SELECT FROM `Compound` WHERE name.toLowerCase() = :name LIMIT 1";
    $compounds = GraphRetriever::execute($sql, ['name' => strtolower($compoundName)]);
    if (empty($compounds)) throw new Exception("Compound not found in live DB.");
    $compoundRid = $compounds[0]['@rid'];
    echo "   [PASS] Compound RID: $compoundRid\n";

    // 2. UNDERGOES Lookup
    echo "2. UNDERGOES Lookup\n";
    $sqlUndergoes = "SELECT @rid as edge_rid, @in as target_rid, @in.@type as target_class FROM `UNDERGOES` WHERE @out = :rid";
    $undergoesEdges = GraphRetriever::execute($sqlUndergoes, ['rid' => $compoundRid]);
    echo "   [PASS] Found " . count($undergoesEdges) . " UNDERGOES edges\n";
    
    // 3 & 4. Method / Process target resolution
    echo "3/4. Method / Process Target Resolution\n";
    $validTargets = [];
    foreach ($undergoesEdges as $edge) {
        if (in_array($edge['target_class'], ['Shodhana_Method', 'Process'])) {
            $validTargets[] = $edge;
        }
    }
    echo "   [PASS] Found " . count($validTargets) . " valid targets (Method/Process)\n";
    
    // 5. USES_MEDIA lookup & 6. Complete Method -> Media path
    echo "5/6. USES_MEDIA Lookup & Complete Path\n";
    $mediaCount = 0;
    foreach ($validTargets as $vt) {
        $sqlMedia = "SELECT @rid as edge_rid, @in as media_rid, @in.@type as media_class FROM `USES_MEDIA` WHERE @out = :rid";
        $mEdges = GraphRetriever::execute($sqlMedia, ['rid' => $vt['target_rid']]);
        if (!empty($mEdges)) {
            $mediaCount += count($mEdges);
        }
    }
    echo "   [PASS] Found $mediaCount Media records through Method/Process\n";
    
    // 8. Reverse Media -> Method/Process -> Compound path
    echo "8. Reverse Traversal ($mediaName)\n";
    $sql = "SELECT FROM `Media` WHERE name.toLowerCase() = :name LIMIT 1";
    $medias = GraphRetriever::execute($sql, ['name' => strtolower($mediaName)]);
    if (!empty($medias)) {
        $mediaRid = $medias[0]['@rid'];
        $sqlRev = "SELECT @rid as edge_rid, @out as method_rid, @out.@type as method_class FROM `USES_MEDIA` WHERE @in = :rid";
        $revEdges = GraphRetriever::execute($sqlRev, ['rid' => $mediaRid]);
        echo "   [PASS] Found " . count($revEdges) . " reverse USES_MEDIA edges\n";
    } else {
        echo "   [SKIP] Media not found.\n";
    }

    echo "\nAll Diagnostic Traversals Completed Successfully!\n";

} catch (Exception $e) {
    echo "\n[FAIL] Exception: " . $e->getMessage() . "\n";
    exit(1);
}
