<?php
require 'api/db.php';
$edges = [
  'PRODUCES', 'VALIDATED_BY', 'CONFIRMS_SAFETY_OF', 'DERIEVED_FROM',
  'USED_IN_FORMULATION', 'HAS_AYURVEDIC_DIAGNOSIS', 'USES_FORMULATION',
  'ASSESS', 'DETOXIFIES', 'CLASSIFIED_AS'
];
foreach($edges as $e) {
  $res = arcade_command("SELECT @out.@type as out_type, @in.@type as in_type FROM `{$e}` LIMIT 1");
  print_r([$e => $res['result']]);
}
