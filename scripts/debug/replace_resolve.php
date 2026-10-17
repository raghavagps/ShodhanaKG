<?php
$file = 'api/core/EntityResolver.php';
$content = file_get_contents($file);

// Find the resolve method and replace it
$start = strpos($content, 'public static function resolve(');
$end = strpos($content, 'public static function fuzzyResolve(');
if ($start !== false && $end !== false) {
    $newResolve = <<<EOD
    public static function resolve(\$type, \$name, &\$debugCollector = null) {
        \$fields = label_fields_for_type(\$type);
        
        \$orConditions = [];
        foreach (\$fields as \$field) {
            \$orConditions[] = "{\$field}.toLowerCase() = :name";
        }
        \$whereClause = implode(" OR ", \$orConditions);
        
        \$sql = "SELECT FROM `{\$type}` WHERE {\$whereClause} LIMIT 1";
        \$params = ['name' => strtolower(trim(\$name))];
        
        if (is_array(\$debugCollector)) {
            if (!isset(\$debugCollector['sql_queries'])) \$debugCollector['sql_queries'] = [];
            \$debugCollector['sql_queries'][] = ['sql' => \$sql, 'params' => \$params];
        }
        
        \$res = arcade_command(\$sql, 'sql', \$params);
        
        if (empty(\$res['result'])) {
            return null;
        }
        
        \$node = \$res['result'][0];
        
        if (!isset(\$node['rid']) && isset(\$node['@rid'])) {
            \$node['rid'] = \$node['@rid'];
        }
        
        return \$node;
    }

    
EOD;
    $content = substr($content, 0, $start) . $newResolve . substr($content, $end);
    file_put_contents($file, $content);
    echo "Replaced resolve()\n";
} else {
    echo "Could not find resolve methods\n";
}
