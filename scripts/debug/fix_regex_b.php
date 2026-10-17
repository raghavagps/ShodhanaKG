<?php
$file = 'api/core/QuestionRouter.php';
$content = file_get_contents($file);

$content = str_replace(
    "\b(purified compound|derived|derivatives|produce)\b",
    "\b(purified compound|derived|derivatives|produce)",
    $content
);
$content = str_replace(
    "\b(shodhana|method|process|detoxif|remove)\b",
    "\b(shodhana|method|process|detoxif|remove)",
    $content
);
$content = str_replace(
    "\b(classif|type|category)\b",
    "\b(classif|type|category)",
    $content
);
$content = str_replace(
    "\b(purified compound|produce|result)\b",
    "\b(purified compound|produce|result)",
    $content
);
$content = str_replace(
    "\b(compound|ingredient|purified|contain)\b",
    "\b(compound|ingredient|purified|contain)",
    $content
);
$content = str_replace(
    "\b(therap|treatment|used in)\b",
    "\b(therap|treatment|used in)",
    $content
);
$content = str_replace(
    "\b(method|process|shodhana)\b",
    "\b(method|process|shodhana)",
    $content
);
$content = str_replace(
    "\b(compound|purified|safe)\b",
    "\b(compound|purified|safe)",
    $content
);
$content = str_replace(
    "\b(disease|modern|match)\b",
    "\b(disease|modern|match)",
    $content
);
$content = str_replace(
    "\b(disease|assess)\b",
    "\b(disease|assess)",
    $content
);
$content = str_replace(
    "\b(method|process|classif)\b",
    "\b(method|process|classif)",
    $content
);

file_put_contents($file, $content);
echo "Fixed regex boundaries\n";
