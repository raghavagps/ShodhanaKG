<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['domain'] = 'compound';
$_GET['category'] = 'Shodhana_Method';
$_GET['limit'] = 1;
require 'api/category-stats.php';
