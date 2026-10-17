<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['domain'] = 'compound';
$_GET['category'] = 'PurifiedCompound';
$_GET['limit'] = 1;
require 'api/category-stats.php';
