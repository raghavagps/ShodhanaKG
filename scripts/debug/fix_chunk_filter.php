<?php
$file = 'api/core/QuestionRouter.php';
$content = file_get_contents($file);

$badChunkCheck = "if (strlen(\$chunk) < 3 || in_array(strtolower(trim(\$chunk)), \$stopWords)) continue;";

$goodChunkCheck = <<<EOD
                    if (strlen(\$chunk) < 3) continue;
                    \$chunkWords = explode(' ', strtolower(trim(\$chunk)));
                    \$allStop = true;
                    foreach (\$chunkWords as \$cw) {
                        if (!in_array(\$cw, \$stopWords)) {
                            \$allStop = false; break;
                        }
                    }
                    if (\$allStop) continue;
EOD;

$content = str_replace($badChunkCheck, $goodChunkCheck, $content);
file_put_contents($file, $content);
echo "Fixed chunk filtering\n";
