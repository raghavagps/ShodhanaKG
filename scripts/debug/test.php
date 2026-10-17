<?php
require 'api/db.php';
$res = arcade_command("SELECT FROM V WHERE name.toLowerCase() = 'swedana'");
print_r($res);
