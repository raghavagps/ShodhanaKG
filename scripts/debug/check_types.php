<?php
require_once dirname(__DIR__, 2) . '/api/db.php';
print_r(arcade_command("SELECT name FROM schema:types WHERE type = 'vertex'", 'sql'));
