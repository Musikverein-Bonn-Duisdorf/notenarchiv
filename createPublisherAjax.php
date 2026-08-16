<?php
/**
 * ARCHIV-48: Quick-create publisher from composition form (session JSON).
 */
require_once __DIR__.'/libs/sessionBootstrap.php';
archivConfigureSession();
include_once 'common/include.php';
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($GLOBALS['conn']));

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function archivQuickPublisherJsonOut($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if(!loggedIn()) {
    archivQuickPublisherJsonOut(array('ok' => false, 'error' => 'not_logged_in'), 403);
}
if(!empty($_SESSION['singleUsePW'])) {
    archivQuickPublisherJsonOut(array('ok' => false, 'error' => 'password_change_required'), 403);
}
if(empty($_SESSION['admin'])) {
    archivQuickPublisherJsonOut(array('ok' => false, 'error' => 'forbidden'), 403);
}
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    archivQuickPublisherJsonOut(array('ok' => false, 'error' => 'method'), 405);
}

$name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
if($name === '') {
    archivQuickPublisherJsonOut(array('ok' => false, 'error' => 'name_required'), 400);
}

$n = new Publisher;
$n->Name = $name;
$n->save();
$id = (int)$n->Index;
if($id < 1) {
    archivQuickPublisherJsonOut(array('ok' => false, 'error' => 'save_failed'), 500);
}

archivQuickPublisherJsonOut(array(
    'ok' => true,
    'id' => $id,
    'label' => archivPublisherOptionLabel($name),
));
