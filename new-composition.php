<?php
// Legacy alias: create/edit lives on composition.php (ARCHIV-31 / page merge).
$id = 0;
if(isset($_GET['id'])) {
    $id = (int)$_GET['id'];
} elseif(isset($_POST['Index'])) {
    $id = (int)$_POST['Index'];
} elseif(isset($_POST['id'])) {
    $id = (int)$_POST['id'];
}
$target = 'composition.php';
if($id > 0) {
    $target .= '?id='.$id;
}
header('Location: '.$target, true, 302);
exit;
