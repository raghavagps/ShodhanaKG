<?php
require 'api/core/GraphRetriever.php';
$res = GraphRetriever::execute("SELECT bothE().size() as cnt FROM Purified_Compound WHERE name = 'Abhraka Bhasma'", []);
print_r($res);
