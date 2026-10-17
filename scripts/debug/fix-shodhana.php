<?php
$file = 'api/category-stats.php';
$content = file_get_contents($file);

$search = <<<EOD
    array('edge' => 'CLASSIFIED_AS', 'key' => 'shodhanaTypes'),
    array('edge' => 'UNDERGOES', 'key' => 'compounds', 'reverse' => true),
    array('edge' => 'FOLLOWED_BY', 'key' => 'processes'),
    array('edge' => 'INVOLVES_PROCESS', 'key' => 'processes'),
  ),
EOD;

$replace = <<<EOD
    array('edge' => 'CLASSIFIED_AS', 'key' => 'shodhanaTypes'),
    array('edge' => 'UNDERGOES', 'key' => 'compounds', 'reverse' => true),
  ),
EOD;

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Cleaned up Shodhana_Method config!\n";
