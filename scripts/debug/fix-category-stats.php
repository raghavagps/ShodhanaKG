<?php
$file = 'api/category-stats.php';
$content = file_get_contents($file);

$search = <<<EOD
      $counts = count_edges_stats_cat($edgeType, $rids, $reverse);
      foreach ($counts as $rid => $cnt) {
        if (isset($rows[$rid])) {
          \$rows[\$rid][\$key] = \$cnt;
        }
      }
EOD;

$replace = <<<EOD
      $counts = count_edges_stats_cat($edgeType, $rids, $reverse);
      foreach ($counts as $rid => $cnt) {
        if (isset($rows[$rid])) {
          if (!isset(\$rows[\$rid][\$key])) {
             \$rows[\$rid][\$key] = 0;
          }
          \$rows[\$rid][\$key] += \$cnt;
        }
      }
EOD;

if (strpos($content, '$rows[$rid][$key] = $cnt;') !== false) {
    $content = str_replace($search, $replace, $content);
    file_put_contents($file, $content);
    echo "Fixed category-stats.php!\n";
} else {
    echo "Could not find target string.\n";
}
