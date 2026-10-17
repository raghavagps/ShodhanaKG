<?php
require 'api/core/EntityResolver.php';
$debug = [];
EntityResolver::fuzzyResolve('Indication', 'Ear Diseases', $debug);
print_r($debug);
