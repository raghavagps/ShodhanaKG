<?php
$file = 'api/provenance-subgraph.php';
$content = file_get_contents($file);

$search1 = <<<'EOD'
       = sprintf(
        "SELECT FROM %s WHERE @out = %s LIMIT %d",
        ,
        ,
         * 3
      );
EOD;

$replace1 = <<<'EOD'
       = sprintf(
        "SELECT FROM %s WHERE @out = %s LIMIT 1000",
        ,
        
      );
EOD;

$search2 = <<<'EOD'
       = sprintf(
        "SELECT FROM USES_FORMULATION WHERE @out IN [%s] LIMIT %d",
        ,
         * 3
      );
EOD;

$replace2 = <<<'EOD'
       = sprintf(
        "SELECT FROM USES_FORMULATION WHERE @out IN [%s] LIMIT 5000",
        
      );
EOD;

$content = str_replace($search1, $replace1, $content);
$content = str_replace($search2, $replace2, $content);
file_put_contents($file, $content);

$file2 = 'api/compound-provenance.php';
$content2 = file_get_contents($file2);

$search3 = <<<'EOD'
       = sprintf(
        "SELECT FROM UNDERGOES WHERE @out = %s LIMIT %d",
        ,
         * 3
      );
EOD;

$replace3 = <<<'EOD'
       = sprintf(
        "SELECT FROM UNDERGOES WHERE @out = %s LIMIT 1000",
        
      );
EOD;

$search4 = <<<'EOD'
       = sprintf(
        "SELECT FROM USES_MEDIA WHERE @out IN [%s] LIMIT %d",
        ,
         * 3
      );
EOD;

$replace4 = <<<'EOD'
       = sprintf(
        "SELECT FROM USES_MEDIA WHERE @out IN [%s] LIMIT 5000",
        
      );
EOD;

$content2 = str_replace($search3, $replace3, $content2);
$content2 = str_replace($search4, $replace4, $content2);
file_put_contents($file2, $content2);

echo "Updated provenance APIs\n";
