<?php
require 'api/core/EntityResolver.php';
print_r(EntityResolver::fuzzyResolve('Compound', 'Vanga'));
print_r(EntityResolver::fuzzyResolve('Purified_Compound', 'Vanga'));
