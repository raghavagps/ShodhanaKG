<?php
require 'api/db.php';
$res1 = arcade_command("SELECT FROM `Therapy` WHERE name.toLowerCase() = 'swedana'");
print_r($res1['result']);
$res2 = arcade_command("SELECT FROM `Shodhana_Method` WHERE name.toLowerCase() = 'swedana'");
print_r($res2['result']);
