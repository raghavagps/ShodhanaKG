<?php
$file = 'api/core/QuestionRouter.php';
$content = file_get_contents($file);

$search = <<<EOD
        // Pronoun resolution from history
        if (preg_match('/\b(it|this|that|they|them|these|those)\b/i', \$originalQ) && !empty(\$history)) {
            for (\$i = count(\$history) - 1; \$i >= 0; \$i--) {
                if (isset(\$history[\$i]['role']) && \$history[\$i]['role'] === 'user') {
                    \$lastQ = \$history[\$i]['text'];
                    \$lastCandidates = self::extractEntityCandidates(\$lastQ);
                    
                    \$cleanLastQ = preg_replace('/[^\w\s]/', '', \$lastQ);
                    \$lastWords = array_values(array_filter(explode(' ', \$cleanLastQ)));
                    for (\$size = 4; \$size >= 1; \$size--) {
                        for (\$idx = 0; \$idx <= count(\$lastWords) - \$size; \$idx++) {
                            \$chunk = implode(' ', array_slice(\$lastWords, \$idx, \$size));
                            if (strlen(\$chunk) >= 3) {
                                \$lastCandidates[] = \$chunk;
                            }
                        }
                    }
                    \$candidates = array_merge(\$lastCandidates, \$candidates);
                    break;
                }
            }
        }
EOD;

$replace = <<<EOD
        // Pronoun resolution from history
        if (preg_match('/\b(it|this|that|they|them|these|those)\b/i', \$originalQ) && !empty(\$history)) {
            \$historyCandidates = [];
            // Go backwards through all user messages
            for (\$i = count(\$history) - 1; \$i >= 0; \$i--) {
                if (isset(\$history[\$i]['role']) && \$history[\$i]['role'] === 'user') {
                    \$lastQ = \$history[\$i]['text'];
                    \$extracted = self::extractEntityCandidates(\$lastQ);
                    
                    \$cleanLastQ = preg_replace('/[^\w\s]/', '', \$lastQ);
                    \$lastWords = array_values(array_filter(explode(' ', \$cleanLastQ)));
                    for (\$size = 4; \$size >= 1; \$size--) {
                        for (\$idx = 0; \$idx <= count(\$lastWords) - \$size; \$idx++) {
                            \$chunk = implode(' ', array_slice(\$lastWords, \$idx, \$size));
                            if (strlen(\$chunk) >= 3) {
                                \$extracted[] = \$chunk;
                            }
                        }
                    }
                    \$historyCandidates = array_merge(\$historyCandidates, \$extracted);
                }
            }
            \$candidates = array_merge(\$historyCandidates, \$candidates);
        }
EOD;

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Updated pronoun history loop!\n";
