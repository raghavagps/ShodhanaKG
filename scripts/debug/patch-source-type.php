<?php
$file = 'api/category-stats.php';
$content = file_get_contents($file);

$search1 = <<<EOD
      // NOTE: Both 'processes' and 'shodhanaMethods' count PRODUCES edges.
      // Since both Process and Shodhana_Method can PRODUCE Purified_Compound,
      // and the current counting logic cannot distinguish source vertex types,
      // both columns will show the same total count of all PRODUCES edges.
      array('edge' => 'PRODUCES', 'key' => 'processes', 'reverse' => true),
      array('edge' => 'PRODUCES', 'key' => 'shodhanaMethods', 'reverse' => true),
EOD;
$replace1 = <<<EOD
      array('edge' => 'PRODUCES', 'key' => 'processes', 'reverse' => true, 'sourceType' => 'Process'),
      array('edge' => 'PRODUCES', 'key' => 'shodhanaMethods', 'reverse' => true, 'sourceType' => 'Shodhana_Method'),
EOD;
$content = str_replace($search1, $replace1, $content);

$search2 = <<<EOD
    foreach (\$edges as \$ec) {
      \$edgeType = \$ec['edge'];
      \$key = \$ec['key'];
      \$reverse = isset(\$ec['reverse']) ? \$ec['reverse'] : false;
  
      \$counts = count_edges_stats_cat(\$edgeType, \$rids, \$reverse);
EOD;
$replace2 = <<<EOD
    foreach (\$edges as \$ec) {
      \$edgeType = \$ec['edge'];
      \$key = \$ec['key'];
      \$reverse = isset(\$ec['reverse']) ? \$ec['reverse'] : false;
      \$sourceType = isset(\$ec['sourceType']) ? \$ec['sourceType'] : null;
  
      \$counts = count_edges_stats_cat(\$edgeType, \$rids, \$reverse, \$sourceType);
EOD;
$content = str_replace($search2, $replace2, $content);

$search3 = <<<EOD
function count_edges_stats_cat(\$edgeType, \$rids, \$reverse) {
  if (empty(\$rids) || !\$edgeType) return array();
  \$ridCsv = implode(',', \$rids);
  \$field = \$reverse ? '@in' : '@out';
  \$sql = sprintf(
    "SELECT %s, COUNT(*) AS cnt FROM `%s` WHERE %s IN [%s] GROUP BY %s LIMIT 2000",
    \$field, \$edgeType, \$field, \$ridCsv, \$field
  );
EOD;
$replace3 = <<<EOD
function count_edges_stats_cat(\$edgeType, \$rids, \$reverse, \$sourceType = null) {
  if (empty(\$rids) || !\$edgeType) return array();
  \$ridCsv = implode(',', \$rids);
  \$field = \$reverse ? '@in' : '@out';
  \$otherField = \$reverse ? '@out' : '@in';
  
  if (\$sourceType) {
      \$sql = sprintf(
        "SELECT %s, COUNT(*) AS cnt FROM `%s` WHERE %s IN [%s] AND %s.@type = '%s' GROUP BY %s LIMIT 2000",
        \$field, \$edgeType, \$field, \$ridCsv, \$otherField, \$sourceType, \$field
      );
  } else {
      \$sql = sprintf(
        "SELECT %s, COUNT(*) AS cnt FROM `%s` WHERE %s IN [%s] GROUP BY %s LIMIT 2000",
        \$field, \$edgeType, \$field, \$ridCsv, \$field
      );
  }
EOD;
$content = str_replace($search3, $replace3, $content);

file_put_contents($file, $content);
echo "Added sourceType filter to category-stats.php!\n";
