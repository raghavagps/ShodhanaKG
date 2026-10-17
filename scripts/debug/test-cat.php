<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['category'] = 'Therapy';
$_GET['q'] = 'Swedana';
require 'api/category-stats.php';
