<?php
require 'api/core/EntityResolver.php';
$debug = [];
print_r(EntityResolver::resolve('Compound', 'Abhraka', $debug));
print_r($debug);
